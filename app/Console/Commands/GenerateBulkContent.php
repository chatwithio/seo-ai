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
    protected $signature = 'seo:generate-content {--keyword-ids=} {--language=English} {--density=1.5} {--length=1000} {--hint=}';

    protected $description = 'Generate bulk content from a list of selected keyword IDs';

    public function handle(SeoContentGenerationService $generationService)
    {
        $keywordIdsStr = $this->option('keyword-ids');
        if (blank($keywordIdsStr)) {
            $this->error('No keyword IDs provided.');

            return 1;
        }

        $keywordIds = array_filter(array_map('intval', explode(',', $keywordIdsStr)));
        $keywords = SeoKeyword::whereIn('id', $keywordIds)->get();

        if ($keywords->isEmpty()) {
            $this->error('No valid keywords found for the provided IDs.');

            return 1;
        }

        $firstKeyword = $keywords->first();
        $site = $firstKeyword->site;

        if (! $site) {
            $this->error('The selected keywords are not associated with a valid site.');

            return 1;
        }

        $primaryModel = $keywords->sortByDesc('total_clicks')->first();

        $lockKey = 'seo:generate-content:lock:'.$primaryModel->id;
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
            "seo:generate-content --keyword-ids={$keywordIdsStr}",
            $site->user_id,
            $site->id,
        );

        $this->info("Starting bulk content generation for primary keyword: {$primaryModel->query_text}");

        try {
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

            $this->info('Step 1: Generating brief...');
            $brief = $generationService->generateBrief($group);

            $this->info('Step 2: Generating draft...');
            $draft = $generationService->generateDraft($brief, [
                'density' => $this->option('density'),
                'length' => $this->option('length'),
                'hint' => $this->option('hint') ?? '',
                'language' => $this->option('language'),
            ]);

            $this->info('Step 3: Reviewing draft...');
            $generationService->reviewDraft($draft);

            SeoAuditLog::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'entity_type' => 'content_generation',
                'action' => 'generated_successfully',
                'message' => "Successfully generated content draft for primary keyword '{$primaryModel->query_text}'",
                'context' => [
                    'group_id' => $group->id,
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

            SeoAuditLog::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'entity_type' => 'content_generation',
                'action' => 'generation_failed',
                'message' => $e->getMessage(),
                'context' => [
                    'primary_keyword' => $primaryModel->query_text,
                    'keyword_ids' => $keywordIds,
                ],
            ]);

            return 1;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
