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

class GenerateContentImprovementRecommendationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(public int $improvementId, public bool $force = false) {}

    public function uniqueId(): string
    {
        return 'content-improvement-recommendation:'.$this->improvementId;
    }

    public function handle(ContentImprovementService $service): void
    {
        $improvement = SeoContentImprovement::with('site')->findOrFail($this->improvementId);
        $lockKey = 'seo:content-improvement-recommendation:'.$improvement->id;
        BackgroundTaskManager::register(
            $lockKey,
            'Generate Content Idea',
            'generate-improvement '.$improvement->id,
            (int) $improvement->user_id,
            (int) $improvement->site_id,
        );

        try {
            $improvement->update(['status' => 'generating', 'last_error' => null]);
            $service->generateRecommendation($improvement, $this->force);
        } catch (Throwable $exception) {
            $improvement->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            throw $exception;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
