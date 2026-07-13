<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\SeoAuditLog;
use App\Services\BackgroundTaskManager;
use App\Services\KeywordGroupingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GroupSeoKeywords extends Command
{
    protected $signature = 'seo:group-keywords {site_id} {--limit=50}';

    protected $description = 'Group unassigned SEO keywords using LLM';

    public function handle(KeywordGroupingService $groupingService)
    {
        $siteId = $this->argument('site_id');
        $site = GscSite::findOrFail($siteId);
        $limit = (int) $this->option('limit');

        $lockKey = "seo:group-keywords:lock:site:{$siteId}";
        $lockData = Cache::get($lockKey);

        if ($lockData) {
            $pid = $lockData['pid'] ?? null;
            $startTime = $lockData['start_time'] ?? 0;
            $elapsed = time() - $startTime;

            if ($elapsed > 10800) { // 3 hours
                $this->warn("An old run (PID: {$pid}) has been running for {$elapsed} seconds (over 3 hours). Killing it and forcing lock release...");
                BackgroundTaskManager::kill($lockKey);
            } else {
                $this->error("Another grouping process is already active (PID: {$pid}, running for {$elapsed} seconds). Exiting.");

                return 1;
            }
        }

        // Acquire lock and register
        BackgroundTaskManager::register(
            $lockKey,
            "Auto Group Keywords (Site ID: {$siteId})",
            "seo:group-keywords {$siteId}",
            $site->user_id,
            $site->id,
        );

        $this->info("Starting keyword grouping for site: {$site->site_url}");

        SeoAuditLog::create([
            'user_id' => $site->user_id,
            'site_id' => $site->id,
            'entity_type' => 'keyword_grouping',
            'action' => 'grouping_started',
            'message' => "Starting auto keyword grouping with limit of {$limit} keywords.",
        ]);

        try {
            // A manual grouping run must use the exact user-selected batch
            // maximum. Agent strategy thresholds only apply to automated runs.
            $groupsCreated = $groupingService->groupKeywordsForSite($site, $limit, false);
            $this->info("Successfully created {$groupsCreated} new keyword groups.");

            SeoAuditLog::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'entity_type' => 'keyword_grouping',
                'action' => 'grouping_finished',
                'message' => "Successfully created {$groupsCreated} new keyword groups.",
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->error('Grouping failed: '.$e->getMessage());

            SeoAuditLog::create([
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'entity_type' => 'keyword_grouping',
                'action' => 'grouping_failed',
                'message' => $e->getMessage(),
            ]);

            return 1;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
