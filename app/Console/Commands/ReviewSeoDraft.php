<?php

namespace App\Console\Commands;

use App\Models\SeoContentDraft;
use App\Services\SeoContentGenerationService;
use Illuminate\Console\Command;

class ReviewSeoDraft extends Command
{
    protected $signature = 'seo:review-draft {draft_id}';
    protected $description = 'Review a content draft using AI';

    public function handle(SeoContentGenerationService $generationService)
    {
        $draft = SeoContentDraft::findOrFail($this->argument('draft_id'));
        $this->info("Reviewing draft ID: {$draft->id}");
        
        try {
            $reviewedDraft = $generationService->reviewDraft($draft);
            $this->info("Successfully reviewed draft ID: {$reviewedDraft->id}. Status: {$reviewedDraft->status}");
        } catch (\Exception $e) {
            $this->error("Failed to review draft: " . $e->getMessage());
        }
    }
}
