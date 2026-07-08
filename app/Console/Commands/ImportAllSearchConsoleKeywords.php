<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\GscKeywordMetric;
use App\Models\SeoAuditLog;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ImportAllSearchConsoleKeywords extends Command
{
    protected $signature = 'seo:import-all-gsc';
    protected $description = 'Import GSC keywords for the last 1 year across all sites';

    public function handle(GoogleSearchConsoleService $gscService)
    {
        $lockKey = 'seo:import-all-gsc:lock';
        $lockData = \Illuminate\Support\Facades\Cache::get($lockKey);

        if ($lockData) {
            $pid = $lockData['pid'] ?? null;
            $startTime = $lockData['start_time'] ?? 0;
            $elapsed = time() - $startTime;

            if ($elapsed > 10800) { // 3 hours
                $this->warn("An old run (PID: {$pid}) has been running for {$elapsed} seconds (over 3 hours). Killing it and forcing lock release...");
                \App\Services\BackgroundTaskManager::kill($lockKey);
            } else {
                $this->error("Another import process is already active (PID: {$pid}, running for {$elapsed} seconds). Exiting.");
                return 1;
            }
        }

        // Acquire lock and register
        \App\Services\BackgroundTaskManager::register($lockKey, 'Import GSC Keywords (All Sites)', 'seo:import-all-gsc');

        try {
            $sites = GscSite::where('is_active', true)->get();
            
            if ($sites->isEmpty()) {
                $this->info("No active sites found to import.");
                return 0;
            }

            $startDate = now()->subYear()->format('Y-m-d');
            $endDate = now()->subDays(3)->format('Y-m-d');
            // We use a fixed report date for this 1-year aggregated import to prevent duplicate records
            $reportDate = now()->subDays(3)->format('Y-m-d');

            $this->info("Starting keyword import for all sites from {$startDate} to {$endDate}");

            foreach ($sites as $site) {
                $this->info("Importing GSC data for site: {$site->site_url}");
                
                SeoAuditLog::create([
                    'site_id' => $site->id,
                    'entity_type' => 'gsc_import',
                    'action' => 'gsc_import_started',
                    'message' => "Starting 1-year import from {$startDate} to {$endDate}",
                ]);

                // Clean up existing metrics for this site and report date to avoid duplicates
                GscKeywordMetric::where('site_id', $site->id)
                    ->where('report_date', $reportDate)
                    ->delete();

                $startRow = 0;
                $rowLimit = config('seo_agent.import_row_limit', 25000);
                $totalImported = 0;

                try {
                    if (!$site->googleOauthToken) {
                        throw new \Exception("No connected Google Account token found for site.");
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
                            GscKeywordMetric::updateOrInsert(
                                [
                                    'site_id' => $site->id,
                                    'report_date' => $reportDate,
                                    'query_text' => substr($row['query'], 0, 191),
                                    'page_url' => substr($row['page'], 0, 191),
                                    'country' => $row['country'],
                                    'device' => $row['device'],
                                ],
                                [
                                    'clicks' => $row['clicks'],
                                    'impressions' => $row['impressions'],
                                    'ctr' => $row['ctr'],
                                    'position' => $row['position'],
                                    'imported_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }

                        $fetched = count($rows);
                        $totalImported += $fetched;
                        $startRow += $fetched;
                        
                        if ($fetched < $rowLimit) {
                            break;
                        }
                    }
                    
                    $site->update(['last_imported_at' => now()]);

                    SeoAuditLog::create([
                        'site_id' => $site->id,
                        'entity_type' => 'gsc_import',
                        'action' => 'gsc_import_finished',
                        'message' => "Imported {$totalImported} rows for date range {$startDate} to {$endDate}",
                    ]);

                    $this->info("Successfully imported {$totalImported} rows.");

                    // Automatically trigger aggregation to update seo_keywords table
                    $this->info("Aggregating keywords into SEO Keywords table...");
                    Artisan::call('seo:aggregate-keywords', ['site_id' => $site->id]);

                } catch (\Exception $e) {
                    SeoAuditLog::create([
                        'site_id' => $site->id,
                        'entity_type' => 'gsc_import',
                        'action' => 'gsc_import_failed',
                        'message' => $e->getMessage(),
                    ]);
                    $this->error("Import failed for site {$site->site_url}: " . $e->getMessage());
                }
            }

            $this->info("Keyword import across all sites completed successfully.");
            return 0;
        } finally {
            \App\Services\BackgroundTaskManager::unregister($lockKey);
        }
    }
}
