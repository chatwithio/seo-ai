<?php

namespace App\Jobs;

use App\Models\SeoContentImprovement;
use App\Services\BackgroundTaskManager;
use App\Services\ContentImprovementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateContentImprovementDraftJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(public int $improvementId, public array $options = []) {}

    public function uniqueId(): string
    {
        return 'content-improvement-draft:'.$this->improvementId;
    }

    public function handle(ContentImprovementService $service): void
    {
        $improvement = SeoContentImprovement::with('site')->findOrFail($this->improvementId);
        $lockKey = 'seo:content-improvement-draft:'.$improvement->id;
        BackgroundTaskManager::register(
            $lockKey,
            'Generate Content Rewrite',
            'generate-improvement-draft '.$improvement->id,
            (int) $improvement->user_id,
            (int) $improvement->site_id,
        );

        try {
            $service->generateRewriteDraft($improvement, $this->options);
        } catch (Throwable $exception) {
            $improvement->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            throw $exception;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
