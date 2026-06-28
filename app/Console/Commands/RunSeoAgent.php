<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\SeoKeywordGroup;
use App\Models\SeoContentBrief;
use App\Models\SeoContentDraft;
use App\Services\KeywordGroupingService;
use App\Services\SeoContentGenerationService;
use Illuminate\Console\Command;

class RunSeoAgent extends Command
{
    protected $signature = 'seo:run-agent {site_id}';
    protected $description = 'Run the full automated AI Agent loop for a site (Group -> Brief -> Draft -> Review)';

    public function handle(
        KeywordGroupingService $groupingService,
        SeoContentGenerationService $generationService
    ) {
        $siteId = $this->argument('site_id');
        $site = GscSite::findOrFail($siteId);

        if (!$site->agent_enabled) {
            $this->warn("AI Agent is not enabled for site: {$site->site_url}. Enable it in GSC Sites config.");
            return;
        }

        $this->info("Running AI Agent loop for site: {$site->site_url} (Strategy: {$site->agent_strategy})");

        // 1. Group Keywords
        $this->info("Step 1: Grouping keywords...");
        $groupsCreated = $groupingService->groupKeywordsForSite($site);
        $this->info("Created {$groupsCreated} new keyword groups.");

        // 2. Find new groups and generate briefs
        $newGroups = SeoKeywordGroup::where('site_id', $site->id)
            ->where('status', 'new')
            ->get();

        $this->info("Step 2: Generating briefs for " . $newGroups->count() . " groups...");
        foreach ($newGroups as $group) {
            try {
                $brief = $generationService->generateBrief($group);
                $this->info("Brief generated: {$brief->title}");
            } catch (\Exception $e) {
                $this->error("Failed to generate brief for group {$group->group_name}: " . $e->getMessage());
            }
        }

        // 3. Find new briefs and generate drafts
        $newBriefs = SeoContentBrief::whereIn('keyword_group_id', $newGroups->pluck('id'))
            ->where('status', 'draft')
            ->get();

        $this->info("Step 3: Generating drafts for " . $newBriefs->count() . " briefs...");
        foreach ($newBriefs as $brief) {
            try {
                $draft = $generationService->generateDraft($brief);
                $this->info("Draft generated: {$draft->title}");

                // 4. Audit/Review draft immediately
                $this->info("Step 4: Auditing draft ID {$draft->id}...");
                $reviewed = $generationService->reviewDraft($draft);
                $this->info("Draft audited. Status: {$reviewed->status}");
            } catch (\Exception $e) {
                $this->error("Failed to process draft/review for brief {$brief->title}: " . $e->getMessage());
            }
        }

        $this->info("AI Agent loop finished successfully.");
    }
}
