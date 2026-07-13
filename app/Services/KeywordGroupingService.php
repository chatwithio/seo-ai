<?php

namespace App\Services;

use App\Models\GscSite;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordGroup;
use App\Models\SeoKeywordGroupKeyword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KeywordGroupingService
{
    public function __construct(
        protected SeoPromptService $promptService,
        protected LlmContentService $llmService
    ) {}

    public function groupKeywordsForSite(GscSite $site, ?int $limit = null, bool $applyAgentStrategy = true)
    {
        $query = SeoKeyword::where('site_id', $site->id)
            ->whereNotIn('id', function ($q) {
                $q->select('keyword_id')->from('seo_keyword_group_keywords');
            });

        $limit = max(1, min($limit ?? ($site->grouping_limit ?: 50), 200));

        if ($applyAgentStrategy && $site->agent_strategy === 'low_ctr') {
            $query
                ->where('total_impressions', '>=', $site->min_impressions ?: 100)
                ->where('total_clicks', '<=', $site->max_clicks ?: 10)
                ->orderByDesc('total_impressions');
        } else {
            $query
                ->orderByDesc('total_impressions')
                ->orderByDesc('total_clicks');
        }

        $keywords = $query->limit($limit)->get();

        if ($keywords->isEmpty()) {
            return 0;
        }

        $keywordListText = $keywords->map(function ($k) {
            return "- {$k->query_text} (Clicks: {$k->total_clicks}, Impressions: {$k->total_impressions})";
        })->implode("\n")."\n\nMANDATORY GROUPING RULES:\n"
            ."- Group every keyword in this list. Do not omit any keyword.\n"
            ."- Every keyword must appear exactly once as either primary_keyword or in secondary_keywords.\n"
            ."- Use the keyword text exactly as provided. Do not rewrite, invent, or merge keyword text.\n"
            .'- Return as many topic groups as needed to cover the complete batch.';

        $promptData = $this->promptService->getPrompt('group_keywords', [
            'site_url' => $site->site_url,
            'keywords' => $keywordListText,
        ]);

        if (! $promptData) {
            throw new \Exception('Prompt config missing for group_keywords');
        }

        $response = $this->llmService->call($promptData, $site->user_id, $site->id);
        $data = json_decode($response, true);

        if (! isset($data['groups']) || ! is_array($data['groups'])) {
            throw new \Exception('Invalid LLM response format: '.$response);
        }

        $keywordsByText = $keywords->keyBy(fn (SeoKeyword $keyword) => $this->matchKey($keyword->query_text));

        return DB::transaction(function () use ($data, $keywords, $keywordsByText, $site) {
            $groupsCreated = 0;
            $assignedKeywordIds = [];

            foreach ($data['groups'] as $groupData) {
                $requestedTexts = array_merge(
                    [$groupData['primary_keyword'] ?? ''],
                    is_array($groupData['secondary_keywords'] ?? null) ? $groupData['secondary_keywords'] : [],
                );

                $groupKeywords = collect($requestedTexts)
                    ->map(fn ($text) => $keywordsByText->get($this->matchKey((string) $text)))
                    ->filter()
                    ->unique('id')
                    ->reject(fn (SeoKeyword $keyword) => isset($assignedKeywordIds[$keyword->id]))
                    ->values();

                if ($groupKeywords->isEmpty()) {
                    continue;
                }

                $primaryModel = $keywordsByText->get($this->matchKey((string) ($groupData['primary_keyword'] ?? '')));
                if (! $primaryModel || ! $groupKeywords->contains('id', $primaryModel->id)) {
                    $primaryModel = $groupKeywords->first();
                }

                $group = $this->createGroup($site, $primaryModel, $groupData);

                foreach ($groupKeywords as $keyword) {
                    $assignedKeywordIds[$keyword->id] = true;

                    if ($keyword->id !== $primaryModel->id) {
                        SeoKeywordGroupKeyword::create([
                            'group_id' => $group->id,
                            'keyword_id' => $keyword->id,
                            'role' => 'secondary',
                        ]);
                    }
                }

                $this->recalculateGroupMetrics($group);
                $groupsCreated++;
            }

            // AI providers can occasionally omit an item despite explicit
            // instructions. Keep the batch complete by creating one fallback
            // group containing every omitted keyword rather than silently
            // leaving those keywords ungrouped.
            $omittedKeywords = $keywords
                ->reject(fn (SeoKeyword $keyword) => isset($assignedKeywordIds[$keyword->id]))
                ->values();

            if ($omittedKeywords->isNotEmpty()) {
                $primaryModel = $omittedKeywords->first();
                $group = $this->createGroup($site, $primaryModel, [
                    'group_name' => 'Additional keywords: '.$primaryModel->query_text,
                    'group_intent' => 'unknown',
                    'content_type' => 'blog_article',
                    'recommended_action' => 'create_new_page',
                    'ai_summary' => 'Fallback group for keywords omitted from the AI grouping response.',
                ]);

                foreach ($omittedKeywords->skip(1) as $keyword) {
                    SeoKeywordGroupKeyword::create([
                        'group_id' => $group->id,
                        'keyword_id' => $keyword->id,
                        'role' => 'secondary',
                    ]);
                }

                $this->recalculateGroupMetrics($group);
                $groupsCreated++;
            }

            return $groupsCreated;
        });
    }

    private function createGroup(GscSite $site, SeoKeyword $primaryModel, array $groupData): SeoKeywordGroup
    {
        $groupName = $groupData['group_name'] ?? $primaryModel->query_text;
        $group = SeoKeywordGroup::create([
            'user_id' => $site->user_id,
            'site_id' => $site->id,
            'group_name' => $groupName,
            'slug' => Str::slug($groupName),
            'primary_keyword_id' => $primaryModel->id,
            'group_intent' => $groupData['group_intent'] ?? 'unknown',
            'content_type' => $groupData['content_type'] ?? 'blog_article',
            'recommended_action' => $groupData['recommended_action'] ?? 'create_new_page',
            'ai_summary' => $groupData['ai_summary'] ?? null,
            'status' => 'new',
        ]);

        SeoKeywordGroupKeyword::create([
            'group_id' => $group->id,
            'keyword_id' => $primaryModel->id,
            'role' => 'primary',
        ]);

        return $group;
    }

    private function matchKey(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }

    private function recalculateGroupMetrics(SeoKeywordGroup $group)
    {
        $metrics = SeoKeywordGroupKeyword::where('group_id', $group->id)
            ->join('seo_keywords', 'seo_keywords.id', '=', 'seo_keyword_group_keywords.keyword_id')
            ->selectRaw('SUM(total_clicks) as sum_clicks, SUM(total_impressions) as sum_imp, AVG(avg_position) as avg_pos')
            ->first();

        if ($metrics) {
            $ctr = $metrics->sum_imp > 0 ? ($metrics->sum_clicks / $metrics->sum_imp) * 100 : 0;
            $group->update([
                'total_clicks' => $metrics->sum_clicks ?? 0,
                'total_impressions' => $metrics->sum_imp ?? 0,
                'avg_ctr' => $ctr,
                'avg_position' => $metrics->avg_pos ?? 0,
            ]);
        }
    }
}
