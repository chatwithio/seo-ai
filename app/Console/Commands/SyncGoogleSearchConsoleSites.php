<?php

namespace App\Console\Commands;

use App\Models\GoogleOauthToken;
use App\Models\GscSite;
use App\Models\SeoAuditLog;
use App\Services\AccountOnboardingService;
use App\Services\BackgroundTaskManager;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\PhpExecutableFinder;

class SyncGoogleSearchConsoleSites extends Command
{
    protected $signature = 'seo:sync-gsc-sites
        {--user-id= : Sync sites for this user}
        {--token-id= : Sync only this connected Google account}
        {--import-days= : Start a keyword import for this many days after syncing}';

    protected $description = 'Sync Google Search Console sites for one user';

    public function handle(
        GoogleSearchConsoleService $service,
        AccountOnboardingService $onboarding,
    ): int {
        $userId = (int) $this->option('user-id');

        if ($userId < 1) {
            $this->error('--user-id is required.');

            return self::FAILURE;
        }

        $tokenId = (int) $this->option('token-id');
        $importDays = filled($this->option('import-days'))
            ? max(1, min((int) $this->option('import-days'), 486))
            : null;
        $lockKey = 'seo:sync-gsc-sites:lock:user:'.$userId;

        if (Cache::has($lockKey)) {
            $this->error('A site sync is already running for this user.');

            return self::FAILURE;
        }

        $tokens = GoogleOauthToken::where('provider', 'google')
            ->where('user_id', $userId)
            ->when($tokenId > 0, fn ($query) => $query->whereKey($tokenId))
            ->get();

        if ($tokens->isEmpty()) {
            $this->error($tokenId > 0
                ? 'The selected Google account is not connected to this user.'
                : 'No Google accounts are connected.');

            return self::FAILURE;
        }

        $command = 'seo:sync-gsc-sites --user-id='.$userId;

        if ($tokenId > 0) {
            $command .= ' --token-id='.$tokenId;
        }

        if ($importDays !== null) {
            $command .= ' --import-days='.$importDays;
        }

        BackgroundTaskManager::register(
            $lockKey,
            'Sync Google Sites',
            $command,
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
        $successfulTokens = collect();

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
                    $successfulTokens->push($token);
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

            foreach ($successfulTokens as $token) {
                $onboarding->markSitesSynced($token);

                if (! $token->fresh()->is_onboarding) {
                    continue;
                }

                $sites = $token->sites()->where('is_active', true)->get();

                if ($sites->count() === 1) {
                    $site = $sites->first();
                    $onboarding->selectSites($token, [$site->id]);
                    $this->startSiteKeywordImport(
                        $userId,
                        (int) $site->id,
                        (int) config('onboarding.initial_import_days', 90),
                    );
                }
            }

            if ($importDays !== null) {
                BackgroundTaskManager::update($lockKey, [
                    'status_text' => "Sites synced. Starting {$importDays}-day keyword import...",
                    'progress_current' => $tokens->count(),
                    'progress_percent' => 100,
                ]);

                $this->startKeywordImport($userId, $tokenId, $importDays);
            }

            $this->info("Synced {$syncedCount} site(s).");

            return self::SUCCESS;
        } finally {
            BackgroundTaskManager::unregister($lockKey);
        }
    }

    private function startSiteKeywordImport(int $userId, int $siteId, int $days): void
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';

        exec(
            'cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg($php)
            ." artisan seo:import-all-gsc --user-id={$userId} --site-id={$siteId} --days={$days}"
            .' >> '.escapeshellarg(storage_path('logs/onboarding-import.log')).' 2>&1 &',
        );
    }

    private function startKeywordImport(int $userId, int $tokenId, int $days): void
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        $command = 'cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg($php)
            ." artisan seo:import-all-gsc --user-id={$userId} --days={$days}";

        if ($tokenId > 0) {
            $command .= " --token-id={$tokenId}";
        }

        exec($command.' > /dev/null 2>&1 &');
    }
}
