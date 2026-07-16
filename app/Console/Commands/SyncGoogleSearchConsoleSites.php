<?php

namespace App\Console\Commands;

use App\Models\GoogleOauthToken;
use App\Models\GscSite;
use App\Models\SeoAuditLog;
use App\Services\BackgroundTaskManager;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncGoogleSearchConsoleSites extends Command
{
    protected $signature = 'seo:sync-gsc-sites {--user-id= : Sync sites for this user}';

    protected $description = 'Sync Google Search Console sites for one user';

    public function handle(GoogleSearchConsoleService $service): int
    {
        $userId = (int) $this->option('user-id');

        if ($userId < 1) {
            $this->error('--user-id is required.');

            return self::FAILURE;
        }

        $lockKey = 'seo:sync-gsc-sites:lock:user:'.$userId;

        if (Cache::has($lockKey)) {
            $this->error('A site sync is already running for this user.');

            return self::FAILURE;
        }

        $tokens = GoogleOauthToken::where('provider', 'google')
            ->where('user_id', $userId)
            ->get();

        if ($tokens->isEmpty()) {
            $this->error('No Google accounts are connected.');

            return self::FAILURE;
        }

        BackgroundTaskManager::register(
            $lockKey,
            'Sync Google Sites',
            'seo:sync-gsc-sites --user-id='.$userId,
            $userId,
        );
        BackgroundTaskManager::update($lockKey, [
            'status_text' => 'Connecting to Google Search Console...',
            'progress_current' => 0,
            'progress_total' => $tokens->count(),
            'progress_percent' => 0,
        ]);

        $syncedCount = 0;
        $failedAccounts = 0;

        try {
            foreach ($tokens as $index => $token) {
                BackgroundTaskManager::update($lockKey, [
                    'status_text' => 'Loading sites for '.$token->email,
                    'progress_current' => $index,
                    'synced_count' => $syncedCount,
                    'progress_percent' => (int) floor(($index / $tokens->count()) * 100),
                ]);

                try {
                    if (! str_contains($token->scope ?? '', 'https://www.googleapis.com/auth/webmasters.readonly')) {
                        throw new \RuntimeException('Search Console permission is missing. Reconnect this Google account and approve Search Console access.');
                    }

                    foreach ($service->listSites($token) as $siteData) {
                        GscSite::updateOrCreate(
                            [
                                'user_id' => $userId,
                                'site_url' => $siteData['siteUrl'],
                            ],
                            [
                                'google_oauth_token_id' => $token->id,
                                'name' => parse_url($siteData['siteUrl'], PHP_URL_HOST) ?: $siteData['siteUrl'],
                                'permission_level' => $siteData['permissionLevel'],
                                'is_active' => true,
                            ],
                        );
                        $syncedCount++;

                        BackgroundTaskManager::update($lockKey, [
                            'synced_count' => $syncedCount,
                            'status_text' => "Found {$syncedCount} site(s)...",
                        ]);
                    }
                } catch (\Throwable $exception) {
                    $failedAccounts++;

                    SeoAuditLog::create([
                        'user_id' => $userId,
                        'entity_type' => 'gsc_site_sync',
                        'action' => 'gsc_site_sync_failed',
                        'message' => "{$token->email}: {$exception->getMessage()}",
                    ]);
                }

                BackgroundTaskManager::update($lockKey, [
                    'progress_current' => $index + 1,
                    'synced_count' => $syncedCount,
                    'progress_percent' => (int) floor((($index + 1) / $tokens->count()) * 100),
                ]);
            }

            SeoAuditLog::create([
                'user_id' => $userId,
                'entity_type' => 'gsc_site_sync',
                'action' => $failedAccounts > 0 ? 'gsc_site_sync_partial' : 'gsc_site_sync_finished',
                'message' => "Synced {$syncedCount} site(s); {$failedAccounts} account(s) failed.",
            ]);

            if ($failedAccounts === $tokens->count()) {
                $this->error('Site sync failed for every connected account.');

                return self::FAILURE;
            }

            $this->info("Synced {$syncedCount} site(s).");

            return self::SUCCESS;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }
}
