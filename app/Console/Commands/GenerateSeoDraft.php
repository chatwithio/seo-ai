<?php

namespace App\Console\Commands;

use App\Models\SeoContentBrief;
use App\Services\SeoContentGenerationService;
use Illuminate\Console\Command;

class GenerateSeoDraft extends Command
{
    protected $signature = 'seo:generate-draft {brief_id}';
    protected $description = 'Generate a content draft from a brief';

    public function handle(SeoContentGenerationService $generationService)
    {
        $brief = SeoContentBrief::findOrFail($this->argument('brief_id'));
        $this->info("Generating draft for brief: {$brief->title}");
        
        try {
            $draft = $generationService->generateDraft($brief);
            $this->info("Successfully generated draft ID: {$draft->id}");
        } catch (\Exception $e) {
            $this->error("Failed to generate draft: " . $e->getMessage());
        }
    }
}
