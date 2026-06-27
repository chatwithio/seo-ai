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

        $this->info("Starting keyword grouping for site: {$site->site_url}");

        try {
            $groupsCreated = $groupingService->groupKeywordsForSite($site, $limit);
            $this->info("Successfully created {$groupsCreated} new keyword groups.");
        } catch (\Exception $e) {
            $this->error("Grouping failed: " . $e->getMessage());
        }
    }
}
