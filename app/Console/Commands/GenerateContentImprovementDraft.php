<?php

namespace App\Console\Commands;

use App\Models\SeoContentImprovement;
use App\Services\ContentImprovementService;
use Illuminate\Console\Command;

class GenerateContentImprovementDraft extends Command
{
    protected $signature = 'seo:generate-improvement-draft {improvement_id} {--language=} {--length=}';

    protected $description = 'Generate a complete rewrite draft from a content recommendation';

    public function handle(ContentImprovementService $service): int
    {
        $improvement = SeoContentImprovement::findOrFail((int) $this->argument('improvement_id'));
        try {
            $draft = $service->generateRewriteDraft($improvement, array_filter([
                'language' => $this->option('language'),
                'length' => $this->option('length'),
            ]));
            $this->info("Rewrite draft #{$draft->id} generated.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $improvement->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
