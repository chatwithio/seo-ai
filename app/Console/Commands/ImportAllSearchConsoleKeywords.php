<?php

namespace App\Console\Commands;

use App\Models\GscKeywordMetric;
use App\Models\GscSite;
use App\Models\SeoAuditLog;
use App\Services\BackgroundTaskManager;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImportAllSearchConsoleKeywords extends Command
{
    protected $signature = 'seo:import-all-gsc
        {--user-id= : Import all active sites owned by this user}
        {--site-id= : Import only this active site}
        {--token-id= : Import active sites linked to this Google account}
        {--days=365 : Number of available Search Console days to import}';

    protected $description = 'Import a configurable date range of GSC keywords for one user, Google account, or site';

    public function handle(GoogleSearchConsoleService $gscService)
    {
        $userId = $this->option('user-id');
        $siteId = $this->option('site-id');
        $tokenId = $this->option('token-id');
        $days = max(1, min((int) $this->option('days'), 486));

        if (! $userId && ! $siteId) {
            $this->error('Either --user-id or --site-id is required.');

            return 1;
        }

        $scope = $siteId
            ? 'site:'.(int) $siteId
            : ($tokenId
                ? 'user:'.(int) $userId.':token:'.(int) $tokenId
                : 'user:'.(int) $userId);
        $lockKey = 'seo:import-all-gsc:lock:'.$scope;
        $lockData = Cache::get($lockKey);

        if ($lockData) {
            $pid = $lockData['pid'] ?? null;
            $startTime = $lockData['start_time'] ?? 0;
            $elapsed = time() - $startTime;

            $this->error("Another import process is already active (PID: {$pid}, running for {$elapsed} seconds). Use Background Tasks to terminate it if needed.");

            return 1;
        }

        // Acquire lock and register
        $command = $siteId
            ? 'seo:import-all-gsc --site-id='.(int) $siteId
            : 'seo:import-all-gsc --user-id='.(int) $userId;

        if ($tokenId) {
            $command .= ' --token-id='.(int) $tokenId;
        }

        $command .= ' --days='.$days;

        $taskSiteId = $siteId ? (int) $siteId : null;
        $taskUserId = $userId
            ? (int) $userId
            : GscSite::whereKey($taskSiteId)->value('user_id');
        BackgroundTaskManager::register($lockKey, 'Import GSC Keywords', $command, $taskUserId, $taskSiteId);

        try {
            $sitesQuery = GscSite::where('is_active', true);

            if ($siteId) {
                $sitesQuery->whereKey((int) $siteId);
            }

            if ($userId) {
                $sitesQuery->where('user_id', (int) $userId);
            }

            if ($tokenId) {
                $sitesQuery->where('google_oauth_token_id', (int) $tokenId);
            }

            $sites = $sitesQuery->get();

            if ($sites->isEmpty()) {
                $this->info('No active sites found to import.');

                return 0;
            }

            BackgroundTaskManager::update($lockKey, [
                'status_text' => "Preparing the latest {$days} days of Search Console data...",
                'progress_current' => 0,
                'progress_total' => $sites->count(),
                'progress_percent' => 0,
                'imported_rows' => 0,
            ]);

            $delayDays = config('seo_agent.import_delay_days', 3);
            $endDateValue = now()->subDays($delayDays)->startOfDay();
            $startDate = $endDateValue->copy()->subDays($days - 1)->format('Y-m-d');
            $endDate = $endDateValue->format('Y-m-d');
            // Use the last available Search Console date as the aggregated
            // report date so rerunning the same range replaces that snapshot.
            $reportDate = $endDate;

            $this->info("Starting {$days}-day keyword import from {$startDate} to {$endDate}");

            $failedSites = 0;

            $allSitesImportedRows = 0;

            foreach ($sites as $siteIndex => $site) {
                $this->info("Importing GSC data for site: {$site->site_url}");

                BackgroundTaskManager::update($lockKey, [
                    'status_text' => 'Importing '.($site->name ?: $site->site_url),
                    'progress_current' => $siteIndex,
                    'current_site' => $site->site_url,
                    'progress_percent' => (int) floor(($siteIndex / $sites->count()) * 100),
                ]);

                SeoAuditLog::create([
                    'user_id' => $site->user_id,
                    'site_id' => $site->id,
                    'entity_type' => 'gsc_import',
                    'action' => 'gsc_import_started',
                    'message' => "Starting {$days}-day import from {$startDate} to {$endDate}",
                ]);

                $startRow = 0;
                $rowLimit = config('seo_agent.import_row_limit', 25000);
                $totalImported = 0;
                $allRows = [];

                try {
                    if (! $site->googleOauthToken) {
                        throw new \Exception('No connected Google Account token found for site.');
                    }

                    while (true) {
                        $this->info("Fetching rows starting at {$startRow}...");

                        $rows = $gscService->fetchSearchAnalyticsRowsForRange(
                            $site->site_url,
                            $startDate,
                            $endDate,
                            $startRow,
                            $rowLimit,
                            $site->googleOauthToken
                        );

                        if (empty($rows)) {
                            break;
                        }

                        foreach ($rows as $row) {
                            $allRows[] = $row;
                        }

                        $fetched = count($rows);
                        $totalImported += $fetched;
                        $startRow += $fetched;

                        BackgroundTaskManager::update($lockKey, [
                            'status_text' => 'Importing '.($site->name ?: $site->site_url)." — {$totalImported} rows received",
                            'imported_rows' => $allSitesImportedRows + $totalImported,
                        ]);

                        if ($fetched < $rowLimit) {
                            break;
                        }
                    }

                    // Do not remove the previous successful import until every
                    // Google API page has been fetched successfully. An empty
                    // result is a valid successful import and clears this date.
                    DB::transaction(function () use ($allRows, $site, $reportDate) {
                        GscKeywordMetric::where('site_id', $site->id)
                            ->where('report_date', $reportDate)
                            ->delete();

                        $timestamp = now();

                        foreach (array_chunk($allRows, 1000) as $rows) {
                            $records = array_map(
                                fn (array $row): array => [
                                    'site_id' => $site->id,
                                    'report_date' => $reportDate,
                                    'query_text' => substr($row['query'], 0, 191),
                                    'page_url' => substr($row['page'], 0, 191),
                                    'country' => $row['country'],
                                    'device' => $row['device'],
                                    'clicks' => $row['clicks'],
                                    'impressions' => $row['impressions'],
                                    'ctr' => $row['ctr'],
                                    'position' => $row['position'],
                                    'imported_at' => $timestamp,
                                    'created_at' => $timestamp,
                                    'updated_at' => $timestamp,
                                ],
                                $rows,
                            );

                            GscKeywordMetric::upsert(
                                $records,
                                ['site_id', 'report_date', 'query_text', 'page_url', 'country', 'device'],
                                ['clicks', 'impressions', 'ctr', 'position', 'imported_at', 'updated_at'],
                            );
                        }
                    });

                    $site->update(['last_imported_at' => now()]);

                    $allSitesImportedRows += $totalImported;

                    // Automatically trigger aggregation to update seo_keywords table
                    $this->info('Aggregating keywords into SEO Keywords table...');
                    Artisan::call('seo:aggregate-keywords', ['site_id' => $site->id]);

                    SeoAuditLog::create([
                        'user_id' => $site->user_id,
                        'site_id' => $site->id,
                        'entity_type' => 'gsc_import',
                        'action' => 'gsc_import_finished',
                        'message' => "Imported {$totalImported} rows for date range {$startDate} to {$endDate}",
                    ]);

                    $this->info("Successfully imported {$totalImported} rows.");

                } catch (\Exception $e) {
                    $failedSites++;
                    SeoAuditLog::create([
                        'user_id' => $site->user_id,
                        'site_id' => $site->id,
                        'entity_type' => 'gsc_import',
                        'action' => 'gsc_import_failed',
                        'message' => $e->getMessage(),
                    ]);
                    $this->error("Import failed for site {$site->site_url}: ".$e->getMessage());
                }

                BackgroundTaskManager::update($lockKey, [
                    'status_text' => 'Completed '.($siteIndex + 1).' of '.$sites->count().' sites',
                    'progress_current' => $siteIndex + 1,
                    'progress_percent' => (int) floor((($siteIndex + 1) / $sites->count()) * 100),
                    'imported_rows' => $allSitesImportedRows,
                    'failed_sites' => $failedSites,
                ]);
            }

            if ($failedSites > 0) {
                $this->error("Keyword import completed with {$failedSites} failed site(s).");

                return 1;
            }

            $this->info('Keyword import completed successfully.');

            return 0;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
