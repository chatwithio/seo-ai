<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\SeoKeyword;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Process\PhpExecutableFinder;

class AutoGenerateSiteContent extends Command
{
    protected $signature = 'seo:auto-generate-content
        {--site-id= : Run one site only}
        {--force : Ignore the configured interval}
        {--dry-run : Show selected keywords without starting generation}';

    protected $description = 'Start scheduled content generation using each site content policy';

    public function handle(): int
    {
        $sites = GscSite::query()
            ->where('is_active', true)
            ->where('auto_content_enabled', true)
            ->when(
                filled($this->option('site-id')),
                fn (Builder $query): Builder => $query->whereKey((int) $this->option('site-id')),
            )
            ->get();

        if ($sites->isEmpty()) {
            $this->info('No sites have automatic content generation enabled.');

            return self::SUCCESS;
        }

        $started = 0;

        foreach ($sites as $site) {
            if (! $this->option('force') && ! $this->isDue($site)) {
                $this->line("Skipping {$site->site_url}: its interval is not due.");

                continue;
            }

            $keywords = $this->candidateKeywords($site)
                ->limit(max(1, min((int) $site->auto_content_count, 20)))
                ->get();

            if ($keywords->isEmpty()) {
                $this->line("No eligible keywords found for {$site->site_url}.");

                continue;
            }

            foreach ($keywords as $keyword) {
                $this->line("Selected {$keyword->query_text} for {$site->site_url}.");

                if ($this->option('dry-run')) {
                    continue;
                }

                $keyword->update([
                    'content_generation_status' => SeoKeyword::CONTENT_GENERATING,
                ]);

                $this->launch((int) $keyword->id);
                $started++;
            }

            if (! $this->option('dry-run')) {
                $site->update(['auto_content_last_run_at' => now()]);
            }
        }

        $this->info($this->option('dry-run')
            ? 'Dry run completed. No content was started.'
            : "{$started} content generation task(s) started.");

        return self::SUCCESS;
    }

    private function isDue(GscSite $site): bool
    {
        if (! $site->auto_content_last_run_at) {
            return true;
        }

        $days = max(1, (int) $site->auto_content_interval_days);

        return $site->auto_content_last_run_at->lte(now()->subDays($days));
    }

    private function candidateKeywords(GscSite $site): Builder
    {
        $averages = SeoKeyword::query()
            ->where('site_id', $site->id)
            ->where('user_id', $site->user_id)
            ->where('total_impressions', '>', 0)
            ->selectRaw('AVG(total_impressions) as impressions, AVG(total_clicks) as clicks')
            ->first();

        $query = SeoKeyword::query()
            ->where('site_id', $site->id)
            ->where('user_id', $site->user_id)
            ->whereIn('content_generation_status', [
                SeoKeyword::CONTENT_READY,
                SeoKeyword::CONTENT_FAILED,
            ])
            ->where('total_impressions', '>', 0);

        if ($site->auto_content_strategy === 'opportunities') {
            $query
                ->where('total_impressions', '>=', (float) ($averages?->impressions ?? 0))
                ->where('total_clicks', '<=', (float) ($averages?->clicks ?? 0));
        }

        if ($site->auto_content_strategy === 'top_clicks') {
            return $query
                ->orderByDesc('total_clicks')
                ->orderByDesc('total_impressions');
        }

        return $query
            ->orderByDesc('total_impressions')
            ->orderByDesc('total_clicks');
    }

    private function launch(int $keywordId): void
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';

        exec(
            'cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg($php)
            ." artisan seo:generate-content --keyword-ids={$keywordId} > /dev/null 2>&1 &",
        );
    }
}
