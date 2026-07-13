<?php

namespace App\Jobs;

use App\Models\GscSite;
use App\Models\SeoAuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ImportGscKeywordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $siteId;

    public $date;

    public $tries = 3;

    // Allow large Search Console imports to run for up to one hour.
    public $timeout = 3600;

    public function __construct($siteId, $date = null)
    {
        $this->siteId = $siteId;
        $this->date = $date;
    }

    public function handle(): void
    {
        $params = ['site_id' => $this->siteId];
        if ($this->date) {
            $params['--date'] = $this->date;
        }

        Artisan::call('seo:import-gsc', $params);
    }

    public function failed(Throwable $exception): void
    {
        $site = GscSite::find($this->siteId);

        SeoAuditLog::create([
            'user_id' => $site?->user_id,
            'site_id' => $this->siteId,
            'entity_type' => 'job_failed',
            'action' => 'import_job_failed',
            'message' => $exception->getMessage(),
        ]);
    }
}
