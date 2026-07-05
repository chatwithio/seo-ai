<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\GscKeywordMetric;
use App\Models\SeoAuditLog;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Command;

class ImportSearchConsoleKeywords extends Command
{
    protected $signature = 'seo:import-gsc {site_id} {--date=}';
    protected $description = 'Import keyword data from Google Search Console';

    public function handle(GoogleSearchConsoleService $gscService)
    {
        $siteId = $this->argument('site_id');
        $site = GscSite::findOrFail($siteId);
        
        $delayDays = config('seo_agent.import_delay_days', 3);
        $date = $this->option('date') ?: now()->subDays($delayDays)->format('Y-m-d');
        
        $this->info("Importing GSC data for site: {$site->site_url} on date: {$date}");
        
        SeoAuditLog::create([
            'site_id' => $site->id,
            'entity_type' => 'gsc_import',
            'action' => 'gsc_import_started',
            'message' => "Starting import for {$date}",
        ]);

        $startRow = 0;
        $rowLimit = config('seo_agent.import_row_limit', 25000);
        $totalImported = 0;

        try {
            while (true) {
                $this->info("Fetching rows starting at {$startRow}...");
                $rows = $gscService->fetchSearchAnalyticsRows(
                    $site->site_url,
                    $date,
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
                            'report_date' => $date,
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
                'message' => "Imported {$totalImported} rows for {$date}",
            ]);

            $this->info("Successfully imported {$totalImported} rows.");
            
        } catch (\Exception $e) {
            SeoAuditLog::create([
                'site_id' => $site->id,
                'entity_type' => 'gsc_import',
                'action' => 'gsc_import_failed',
                'message' => $e->getMessage(),
            ]);
            $this->error("Import failed: " . $e->getMessage());
        }
    }
}
