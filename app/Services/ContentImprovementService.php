<?php

namespace App\Services;

use App\Models\GscKeywordMetric;
use App\Models\GscSite;
use App\Models\SeoContentDraft;
use App\Models\SeoContentImprovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ContentImprovementService
{
    public function __construct(
        protected GoogleSearchConsoleService $searchConsole,
        protected PageContentExtractor $extractor,
        protected SeoPromptService $prompts,
        protected LlmContentService $llm,
        protected SeoContentGenerationService $contentGeneration,
        protected ArticleImageService $articleImages,
    ) {}

    public function refreshSite(GscSite $site): int
    {
        $site->loadMissing('googleOauthToken');

        if (! $site->user_id || ! $site->googleOauthToken) {
            throw new RuntimeException('The site does not have an owned Google connection.');
        }

        $end = CarbonImmutable::today()->subDays((int) config('seo_agent.import_delay_days', 3));
        $start = $end->subDays(max(1, (int) config('seo_agent.improvements.days', 90)) - 1);
        $rows = $this->searchConsole->fetchPagePerformanceForRange(
            $site->site_url,
            $start->toDateString(),
            $end->toDateString(),
            0,
            max(1, (int) config('seo_agent.improvements.page_limit', 20)),
            $site->googleOauthToken,
        );

        usort($rows, fn (array $left, array $right): int => ($right['clicks'] <=> $left['clicks']) ?: ($right['impressions'] <=> $left['impressions']));
        $rows = array_slice($rows, 0, max(1, (int) config('seo_agent.improvements.page_limit', 20)));
        $scannedAt = now();

        DB::transaction(function () use ($site, $rows, $start, $end, $scannedAt): void {
            SeoContentImprovement::query()
                ->where('user_id', $site->user_id)
                ->where('site_id', $site->id)
                ->update(['is_current' => false]);

            foreach ($rows as $row) {
                $url = trim((string) ($row['page'] ?? ''));

                if ($url === '') {
                    continue;
                }

                SeoContentImprovement::updateOrCreate(
                    ['site_id' => $site->id, 'page_hash' => hash('sha256', $url)],
                    [
                        'user_id' => $site->user_id,
                        'page_url' => $url,
                        'clicks' => max(0, (int) round($row['clicks'] ?? 0)),
                        'impressions' => max(0, (int) round($row['impressions'] ?? 0)),
                        'ctr' => max(0, (float) ($row['ctr'] ?? 0)),
                        'position' => max(0, (float) ($row['position'] ?? 0)),
                        'period_start' => $start,
                        'period_end' => $end,
                        'scanned_at' => $scannedAt,
                        'is_current' => true,
                    ],
                );
            }
        });

        return count($rows);
    }

    public function generateRecommendation(SeoContentImprovement $improvement, bool $force = false): SeoContentImprovement
    {
        $improvement->loadMissing('site');
        $this->assertOwnership($improvement);
        $page = $this->extractor->extract($improvement->page_url);

        $recommendationIsForCurrentScan = $improvement->recommendation_generated_at
            && $improvement->scanned_at
            && $improvement->recommendation_generated_at->greaterThanOrEqualTo($improvement->scanned_at);

        if (! $force && $recommendationIsForCurrentScan && $improvement->source_content_hash === $page['hash']) {
            return $improvement;
        }

        $keywords = GscKeywordMetric::query()
            ->where('site_id', $improvement->site_id)
            ->where('page_url', $improvement->page_url)
            ->whereBetween('report_date', [$improvement->period_start, $improvement->period_end])
            ->selectRaw('query_text, SUM(impressions) as total_impressions')
            ->groupBy('query_text')
            ->orderByDesc('total_impressions')
            ->limit(12)
            ->pluck('query_text')
            ->filter()
            ->values()
            ->all();

        $prompt = $this->prompts->getPrompt('improve_content_recommendation', [
            'site_url' => $improvement->site->site_url,
            'page_url' => $improvement->page_url,
            'target_keywords' => implode(', ', $keywords),
            'page_content' => json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        if (! $prompt) {
            throw new RuntimeException('Prompt config missing for improve_content_recommendation.');
        }

        $data = json_decode((string) $this->llm->call($prompt, $improvement->user_id, $improvement->site_id), true);

        if (! is_array($data) || blank($data['suggested_paragraph'] ?? null)) {
            throw new RuntimeException('The AI did not return a valid content recommendation.');
        }

        $improvement->update([
            'page_title' => $page['title'] ?: $improvement->page_title,
            'status' => 'recommendation_ready',
            'suggested_paragraph' => $data['suggested_paragraph'],
            'rationale' => $data['rationale'] ?? null,
            'target_keywords' => array_values(array_filter($data['target_keywords'] ?? $keywords)),
            'source_content_hash' => $page['hash'],
            'last_error' => null,
            'recommendation_generated_at' => now(),
        ]);

        return $improvement->refresh();
    }

    public function generateRewriteDraft(SeoContentImprovement $improvement, array $options = []): SeoContentDraft
    {
        $improvement->loadMissing('site', 'generatedDraft');
        $this->assertOwnership($improvement);

        if (! $improvement->suggested_paragraph) {
            $improvement = $this->generateRecommendation($improvement);
        }

        $existingDraftId = DB::transaction(function () use ($improvement): ?int {
            $locked = SeoContentImprovement::query()->lockForUpdate()->findOrFail($improvement->id);

            if ($locked->generated_draft_id) {
                return (int) $locked->generated_draft_id;
            }

            if ($locked->status === 'generating_draft' && $locked->updated_at?->greaterThan(now()->subMinutes(30))) {
                throw new RuntimeException('A rewrite is already being generated for this page.');
            }

            $locked->update(['status' => 'generating_draft', 'last_error' => null]);

            return null;
        });

        if ($existingDraftId) {
            return SeoContentDraft::findOrFail($existingDraftId);
        }

        $page = $this->extractor->extract($improvement->page_url);
        $language = (string) ($options['language'] ?? $improvement->site->content_language ?? 'English');
        $prompt = $this->prompts->getPrompt('rewrite_existing_content', [
            'site_url' => $improvement->site->site_url,
            'page_url' => $improvement->page_url,
            'language' => $language,
            'length' => (string) ($options['length'] ?? $improvement->site->content_length ?? 1000),
            'target_keywords' => implode(', ', $improvement->target_keywords ?? []),
            'recommendation' => $improvement->suggested_paragraph,
            'page_content' => json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        if (! $prompt) {
            throw new RuntimeException('Prompt config missing for rewrite_existing_content.');
        }

        $data = json_decode((string) $this->llm->call($prompt, $improvement->user_id, $improvement->site_id), true);

        if (! is_array($data) || blank($data['html'] ?? null)) {
            throw new RuntimeException('The AI did not return a valid rewritten article.');
        }

        $draft = DB::transaction(function () use ($improvement, $data, $language): SeoContentDraft {
            $locked = SeoContentImprovement::query()->lockForUpdate()->findOrFail($improvement->id);

            if ($locked->generated_draft_id) {
                return SeoContentDraft::findOrFail($locked->generated_draft_id);
            }

            $draft = SeoContentDraft::create([
                'user_id' => $improvement->user_id,
                'site_id' => $improvement->site_id,
                'content_improvement_id' => $improvement->id,
                'source_url' => $improvement->page_url,
                'title' => Str::limit((string) ($data['title'] ?? $improvement->page_title ?? 'Improved content'), 255, ''),
                'slug' => Str::slug((string) ($data['title'] ?? $improvement->page_title ?? 'improved-content')),
                'meta_title' => Str::limit((string) ($data['meta_title'] ?? ''), 255, ''),
                'meta_description' => Str::limit((string) ($data['meta_description'] ?? ''), 500, ''),
                'html' => trim((string) $data['html']),
                'language' => $language,
                'status' => 'draft',
                'featured_image_status' => 'pending',
            ]);

            $locked->update([
                'generated_draft_id' => $draft->id,
                'status' => 'draft_generated',
                'last_error' => null,
            ]);

            return $draft;
        });

        $this->articleImages->generate($draft);

        return $this->contentGeneration->reviewDraft($draft->fresh());
    }

    private function assertOwnership(SeoContentImprovement $improvement): void
    {
        if (! $improvement->site || (int) $improvement->site->user_id !== (int) $improvement->user_id) {
            throw new RuntimeException('The content opportunity does not belong to its site owner.');
        }
    }
}
