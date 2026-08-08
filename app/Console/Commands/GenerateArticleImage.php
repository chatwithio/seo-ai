<?php

namespace App\Console\Commands;

use App\Models\SeoContentDraft;
use App\Services\ArticleImageService;
use Illuminate\Console\Command;

class GenerateArticleImage extends Command
{
    protected $signature = 'seo:generate-article-image {draft_id} {--replace}';

    protected $description = 'Generate or replace an article featured image';

    public function handle(ArticleImageService $images): int
    {
        $draft = SeoContentDraft::findOrFail((int) $this->argument('draft_id'));
        $success = $images->generate($draft, (bool) $this->option('replace'));
        $success ? $this->info('Featured image is ready.') : $this->error($draft->fresh()->featured_image_error);

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
