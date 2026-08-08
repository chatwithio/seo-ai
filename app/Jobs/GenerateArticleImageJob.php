<?php

namespace App\Jobs;

use App\Models\SeoContentDraft;
use App\Services\ArticleImageService;
use App\Services\BackgroundTaskManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class GenerateArticleImageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct(public int $draftId, public bool $replace = false) {}

    public function uniqueId(): string
    {
        return 'article-image:'.$this->draftId;
    }

    public function handle(ArticleImageService $images): void
    {
        $draft = SeoContentDraft::findOrFail($this->draftId);
        $lockKey = 'seo:article-image:'.$draft->id;
        BackgroundTaskManager::register(
            $lockKey,
            'Generate Article Image',
            'generate-article-image '.$draft->id,
            (int) $draft->user_id,
            $draft->site_id ? (int) $draft->site_id : null,
        );

        try {
            if (! $images->generate($draft, $this->replace)) {
                throw new RuntimeException((string) $draft->fresh()->featured_image_error);
            }
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
