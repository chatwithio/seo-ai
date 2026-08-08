<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\SeoAuditLog;
use App\Services\ContentImprovementService;
use Illuminate\Console\Command;
use Throwable;

class RefreshContentImprovements extends Command
{
    protected $signature = 'seo:refresh-content-improvements {--user-id=} {--site-id=}';

    protected $description = 'Refresh 90-day page-level content improvement opportunities';

    public function handle(ContentImprovementService $service): int
    {
        $query = GscSite::query()
            ->where('is_active', true)
            ->with('googleOauthToken')
            ->when($this->option('user-id'), fn ($query, $userId) => $query->where('user_id', (int) $userId))
            ->when($this->option('site-id'), fn ($query, $siteId) => $query->whereKey((int) $siteId));

        $sites = $query->get();

        if ($sites->isEmpty()) {
            $this->warn('No matching active sites were found.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($sites as $site) {
            try {
                $count = $service->refreshSite($site);
                $this->info("{$site->site_url}: refreshed {$count} pages.");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$site->site_url}: {$exception->getMessage()}");
                SeoAuditLog::create([
                    'user_id' => $site->user_id,
                    'site_id' => $site->id,
                    'entity_type' => 'content_improvement',
                    'action' => 'refresh_failed',
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $failed === $sites->count() ? self::FAILURE : self::SUCCESS;
    }
}
