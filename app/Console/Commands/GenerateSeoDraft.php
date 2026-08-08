<?php

namespace App\Console\Commands;

use App\Models\SeoContentBrief;
use App\Services\ArticleImageService;
use App\Services\SeoContentGenerationService;
use Illuminate\Console\Command;

class GenerateSeoDraft extends Command
{
    protected $signature = 'seo:generate-draft {brief_id}';

    protected $description = 'Generate a content draft from a brief';

    public function handle(SeoContentGenerationService $generationService, ArticleImageService $images): int
    {
        $brief = SeoContentBrief::findOrFail($this->argument('brief_id'));
        $this->info("Generating draft for brief: {$brief->title}");

        try {
            $draft = $generationService->generateDraft($brief);
            $images->generate($draft);
            $draft = $generationService->reviewDraft($draft->fresh());
            $this->info("Successfully generated and reviewed draft ID: {$draft->id}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate draft: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
