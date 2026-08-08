<?php

namespace App\Console\Commands;

use App\Models\SeoContentImprovement;
use App\Services\ContentImprovementService;
use Illuminate\Console\Command;

class GenerateContentImprovementRecommendation extends Command
{
    protected $signature = 'seo:generate-improvement {improvement_id} {--force}';

    protected $description = 'Generate an AI recommendation for an existing page';

    public function handle(ContentImprovementService $service): int
    {
        $improvement = SeoContentImprovement::findOrFail((int) $this->argument('improvement_id'));
        $improvement->update(['status' => 'generating', 'last_error' => null]);

        try {
            $service->generateRecommendation($improvement, (bool) $this->option('force'));
            $this->info('Content recommendation generated.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $improvement->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
