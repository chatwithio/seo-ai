<?php

namespace App\Console\Commands;

use App\Models\SeoKeywordGroup;
use App\Services\SeoContentGenerationService;
use Illuminate\Console\Command;

class GenerateSeoBrief extends Command
{
    protected $signature = 'seo:generate-brief {group_id}';
    protected $description = 'Generate a content brief for a keyword group';

    public function handle(SeoContentGenerationService $generationService)
    {
        $group = SeoKeywordGroup::findOrFail($this->argument('group_id'));
        $this->info("Generating brief for group: {$group->group_name}");
        
        try {
            $brief = $generationService->generateBrief($group);
            $this->info("Successfully generated brief ID: {$brief->id}");
        } catch (\Exception $e) {
            $this->error("Failed to generate brief: " . $e->getMessage());
        }
    }
}
