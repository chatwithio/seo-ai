<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Services\KeywordGroupingService;
use Illuminate\Console\Command;

class GroupSeoKeywords extends Command
{
    protected $signature = 'seo:group-keywords {site_id} {--limit=50}';
    protected $description = 'Group unassigned SEO keywords using LLM';

    public function handle(KeywordGroupingService $groupingService)
    {
        $siteId = $this->argument('site_id');
        $site = GscSite::findOrFail($siteId);
        $limit = (int) $this->option('limit');

        $lockKey = 'seo:group-keywords:lock';
        $lockData = \Illuminate\Support\Facades\Cache::get($lockKey);

        if ($lockData) {
            $pid = $lockData['pid'] ?? null;
            $startTime = $lockData['start_time'] ?? 0;
            $elapsed = time() - $startTime;

            if ($elapsed > 10800) { // 3 hours
                $this->warn("An old run (PID: {$pid}) has been running for {$elapsed} seconds (over 3 hours). Killing it and forcing lock release...");
                \App\Services\BackgroundTaskManager::kill($lockKey);
            } else {
                $this->error("Another grouping process is already active (PID: {$pid}, running for {$elapsed} seconds). Exiting.");
                return 1;
            }
        }

        // Acquire lock and register
        \App\Services\BackgroundTaskManager::register($lockKey, "Auto Group Keywords (Site ID: {$siteId})", "seo:group-keywords {$siteId}");

        $this->info("Starting keyword grouping for site: {$site->site_url}");

        \App\Models\SeoAuditLog::create([
            'site_id' => $site->id,
            'entity_type' => 'keyword_grouping',
            'action' => 'grouping_started',
            'message' => "Starting auto keyword grouping with limit of {$limit} keywords.",
        ]);

        try {
            $groupsCreated = $groupingService->groupKeywordsForSite($site, $limit);
            $this->info("Successfully created {$groupsCreated} new keyword groups.");

            \App\Models\SeoAuditLog::create([
                'site_id' => $site->id,
                'entity_type' => 'keyword_grouping',
                'action' => 'grouping_finished',
                'message' => "Successfully created {$groupsCreated} new keyword groups.",
            ]);
            return 0;
        } catch (\Exception $e) {
            $this->error("Grouping failed: " . $e->getMessage());

            \App\Models\SeoAuditLog::create([
                'site_id' => $site->id,
                'entity_type' => 'keyword_grouping',
                'action' => 'grouping_failed',
                'message' => $e->getMessage(),
            ]);
            return 1;
        } finally {
            \App\Services\BackgroundTaskManager::unregister($lockKey);
        }
    }
}
