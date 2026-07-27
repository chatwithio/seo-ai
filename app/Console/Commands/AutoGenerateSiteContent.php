<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\SeoKeyword;
use App\Services\AccountAutomationScheduleService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Process\PhpExecutableFinder;

class AutoGenerateSiteContent extends Command
{
    protected $signature = 'seo:auto-generate-content
        {--site-id= : Run one site only}
        {--limit= : Override the configured article count for this run}
        {--force : Ignore the configured interval}
        {--scheduled : Run only accounts scheduled for the current local minute}
        {--dry-run : Show selected keywords without starting generation}
        {--foreground : Run generation in this command and show its output}
        {--skip-auto-publish : Generate articles without automatic publishing}';

    protected $description = 'Start scheduled content generation using each site content policy';

    public function handle(AccountAutomationScheduleService $schedules): int
    {
        $sites = GscSite::query()
            ->with('user.publishingSetting')
            ->where('is_active', true)
            ->where('auto_content_enabled', true)
            ->when(
                filled($this->option('site-id')),
                fn (Builder $query): Builder => $query->whereKey((int) $this->option('site-id')),
            )
            ->get();

        if ($sites->isEmpty()) {
            if (! $this->option('scheduled')) {
                $this->info('No sites have automatic content generation enabled.');
            }

            return self::SUCCESS;
        }

        $started = 0;
        $failed = 0;
        $scheduledAccounts = 0;
        $now = now();

        foreach ($sites as $site) {
            $siteStarted = 0;
            $settings = $site->user?->publishingSetting;

            if ($this->option('scheduled')) {
                if (! $settings || ! $schedules->isScheduledNow($settings, $now)) {
                    continue;
                }

                $scheduledAccounts++;
            }

            if (! $this->option('force') && ! $this->isDue($site)) {
                if (! $this->option('scheduled')) {
                    $this->line("Skipping {$site->site_url}: its interval is not due.");
                }

                continue;
            }

            $limit = filled($this->option('limit'))
                ? (int) $this->option('limit')
                : (int) $site->auto_content_count;
            $keywords = $this->candidateKeywords($site)
                ->limit(max(1, min($limit, 20)))
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

                if ($this->option('foreground')) {
                    $exitCode = $this->call('seo:generate-content', array_filter([
                        '--keyword-ids' => (string) $keyword->id,
                        '--skip-auto-publish' => $this->option('skip-auto-publish') ?: null,
                    ]));

                    if ($exitCode === self::SUCCESS) {
                        $started++;
                        $siteStarted++;
                    } else {
                        $failed++;
                    }

                    continue;
                }

                $this->launch(
                    (int) $keyword->id,
                    (bool) $this->option('skip-auto-publish'),
                );
                $started++;
                $siteStarted++;
            }

            if (! $this->option('dry-run') && $siteStarted > 0) {
                $site->update(['auto_content_last_run_at' => now()]);
            }
        }

        if ($this->option('scheduled') && $scheduledAccounts === 0) {
            return self::SUCCESS;
        }

        $this->info($this->option('dry-run')
            ? 'Dry run completed. No content was started.'
            : "{$started} content generation task(s) completed or started; {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isDue(GscSite $site): bool
    {
        if (! $site->auto_content_last_run_at) {
            return true;
        }

        $days = max(1, (int) $site->auto_content_interval_days);
        $timezone = app(AccountAutomationScheduleService::class)->defaultTimezone();
        $lastRunDate = $site->auto_content_last_run_at
            ->copy()
            ->timezone($timezone)
            ->startOfDay();
        $dueDate = now($timezone)
            ->startOfDay()
            ->subDays($days);

        return $lastRunDate->lte($dueDate);
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

    private function launch(int $keywordId, bool $skipAutoPublish): void
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        $skipPublishing = $skipAutoPublish ? ' --skip-auto-publish' : '';
        $log = storage_path('logs/content-generation.log');

        exec(
            'cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg($php)
            ." artisan seo:generate-content --keyword-ids={$keywordId}{$skipPublishing}"
            .' >> '.escapeshellarg($log).' 2>&1 &',
        );
    }
}
