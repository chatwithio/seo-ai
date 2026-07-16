<?php

namespace App\Console\Commands;

use App\Models\SeoAuditLog;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordGroup;
use App\Models\SeoKeywordGroupKeyword;
use App\Services\BackgroundTaskManager;
use App\Services\SeoContentGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class GenerateBulkContent extends Command
{
    protected $signature = 'seo:generate-content
        {--keyword-ids= : Generate from individual keyword IDs and create a group}
        {--group-id= : Generate for an existing keyword group}
        {--language=English}
        {--density=1.5}
        {--length=1000}
        {--hint=}';

    protected $description = 'Generate content from selected keywords or an existing keyword group';

    public function handle(SeoContentGenerationService $generationService)
    {
        $keywordIdsStr = $this->option('keyword-ids');
        $groupId = (int) $this->option('group-id');

        if (blank($keywordIdsStr) && $groupId < 1) {
            $this->error('Provide --keyword-ids or --group-id.');

            return 1;
        }

        $group = null;

        if ($groupId > 0) {
            $group = SeoKeywordGroup::with(['keywords', 'primaryKeyword', 'site'])->find($groupId);

            if (! $group) {
                $this->error('Keyword group not found.');

                return 1;
            }

            $keywords = $group->keywords;

            if ($group->primaryKeyword && ! $keywords->contains('id', $group->primaryKeyword->id)) {
                $keywords->push($group->primaryKeyword);
            }
        } else {
            $keywordIds = array_filter(array_map('intval', explode(',', $keywordIdsStr)));
            $keywords = SeoKeyword::whereIn('id', $keywordIds)->get();
        }

        if ($keywords->isEmpty()) {
            $this->error('No keywords were found for content generation.');

            return 1;
        }

        $site = $group?->site ?? $keywords->first()->site;

        if (! $site) {
            $this->error('The selected keywords are not associated with a valid site.');

            return 1;
        }

        $primaryModel = $group?->primaryKeyword ?? $keywords->sortByDesc('total_clicks')->first();

        $lockKey = $group
            ? 'seo:generate-content:group:'.$group->id
            : 'seo:generate-content:lock:'.$primaryModel->id;
        $lockData = Cache::get($lockKey);

        if ($lockData) {
            $pid = $lockData['pid'] ?? null;
            $startTime = $lockData['start_time'] ?? 0;
            $elapsed = time() - $startTime;

            if ($elapsed > 10800) { // 3 hours
                $this->warn("An old run (PID: {$pid}) has been running for {$elapsed} seconds (over 3 hours). Killing it and forcing lock release...");
                BackgroundTaskManager::kill($lockKey);
            } else {
                $this->error("Another generation process for keyword '{$primaryModel->query_text}' is already active (PID: {$pid}). Exiting.");

                return 1;
            }
        }

        BackgroundTaskManager::register(
            $lockKey,
            'Generate Content: '.$primaryModel->query_text,
            $group
                ? "seo:generate-content --group-id={$group->id}"
                : "seo:generate-content --keyword-ids={$keywordIdsStr}",
            $site->user_id,
            $site->id,
        );
        BackgroundTaskManager::update($lockKey, [
            'status_text' => 'Preparing content plan...',
            'progress_current' => 0,
            'progress_total' => 3,
            'progress_percent' => 0,
        ]);
        SeoKeyword::whereIn('id', $keywords->pluck('id'))->update([
            'content_generation_status' => SeoKeyword::CONTENT_GENERATING,
        ]);

        $this->info("Starting bulk content generation for primary keyword: {$primaryModel->query_text}");

        try {
            if (! $group) {
                $group = SeoKeywordGroup::create([
                    'user_id' => $site->user_id,
                    'site_id' => $site->id,
                    'group_name' => 'AI Generated: '.$primaryModel->query_text,
                    'slug' => Str::slug('ai-generated-'.$primaryModel->query_text),
                    'primary_keyword_id' => $primaryModel->id,
                    'group_intent' => $primaryModel->intent ?: 'unknown',
                    'content_type' => 'blog_article',
                    'recommended_action' => 'create_new_page',
                    'status' => 'new',
                ]);

                foreach ($keywords as $kw) {
                    SeoKeywordGroupKeyword::create([
                        'group_id' => $group->id,
                        'keyword_id' => $kw->id,
                        'role' => $kw->id === $primaryModel->id ? 'primary' : 'secondary',
                    ]);
                }
            }

            $this->info('Step 1: Generating brief...');
            BackgroundTaskManager::update($lockKey, [
                'status_text' => 'Creating the content plan...',
                'progress_current' => 1,
                'progress_percent' => 33,
            ]);
            $brief = $generationService->generateBrief($group);

            $this->info('Step 2: Generating draft...');
            BackgroundTaskManager::update($lockKey, [
                'status_text' => 'Writing the article...',
                'progress_current' => 2,
                'progress_percent' => 66,
            ]);
            $draft = $generationService->generateDraft($brief, [
                'density' => $this->option('density'),
                'length' => $this->option('length'),
                'hint' => $this->option('hint') ?? '',
                'language' => $this->option('language'),
            ]);

            $this->info('Step 3: Reviewing draft...');
            BackgroundTaskManager::update($lockKey, [
                'status_text' => 'Reviewing the article...',
                'progress_current' => 3,
                'progress_percent' => 90,
            ]);
            $generationService->reviewDraft($draft);

            SeoKeyword::whereIn('id', $keywords->pluck('id'))->update([
                'content_generation_status' => SeoKeyword::CONTENT_COMPLETED,
                'content_generated_at' => now(),
            ]);

            BackgroundTaskManager::update($lockKey, [
                'status_text' => 'Article ready',
                'progress_current' => 3,
                'progress_percent' => 100,
            ]);

            SeoAuditLog::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'entity_type' => 'content_generation',
                'action' => 'generated_successfully',
                'message' => "Successfully generated content draft for primary keyword '{$primaryModel->query_text}'",
                'context' => [
                    'group_id' => $group?->id,
                    'draft_id' => $draft->id,
                    'language' => $this->option('language'),
                    'density' => $this->option('density'),
                    'length' => $this->option('length'),
                ],
            ]);

            $this->info("Content generated successfully for draft ID: {$draft->id}");

            return 0;

        } catch (Throwable $e) {
            $this->error('Generation failed: '.$e->getMessage());

            SeoKeyword::whereIn('id', $keywords->pluck('id'))->update([
                'content_generation_status' => SeoKeyword::CONTENT_FAILED,
            ]);

            SeoAuditLog::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'entity_type' => 'content_generation',
                'action' => 'generation_failed',
                'message' => $e->getMessage(),
                'context' => [
                    'primary_keyword' => $primaryModel->query_text,
                    'keyword_ids' => $keywords->pluck('id')->all(),
                    'group_id' => $group?->id,
                ],
            ]);

            return 1;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
