<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SeoKeywords\SeoKeywordResource;
use App\Models\GoogleOauthToken;
use App\Models\GscSite;
use App\Models\SeoKeyword;
use App\Services\AccountOnboardingService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\Process\PhpExecutableFinder;

class Onboarding extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Set up your SEO workspace';

    protected static ?string $slug = 'onboarding';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected string $view = 'filament.pages.onboarding';

    /** @var array<int, int|string> */
    public array $selectedSiteIds = [];

    public function mount(): void
    {
        $this->selectedSiteIds = $this->token()?->onboarding_selected_site_ids ?? [];
    }

    public function token(): ?GoogleOauthToken
    {
        return GoogleOauthToken::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('is_onboarding')
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, GscSite>
     */
    public function sites(): Collection
    {
        $token = $this->token();

        return $token
            ? $token->sites()->orderByRaw('COALESCE(name, site_url)')->get()
            : collect();
    }

    public function selectedKeywordCount(): int
    {
        $token = $this->token();
        $siteIds = $token?->onboarding_selected_site_ids ?? [];

        return SeoKeyword::query()
            ->where('user_id', auth()->id())
            ->whereIn('site_id', $siteIds)
            ->count();
    }

    public function totalSteps(): int
    {
        return app(AccountOnboardingService::class)->totalSteps();
    }

    public function syncSites(): void
    {
        $token = $this->token();

        if (! $token) {
            return;
        }

        $this->launch(
            "seo:sync-gsc-sites --user-id={$token->user_id} --token-id={$token->id}",
            'onboarding-sync.log',
        );

        Notification::make()->title('Site sync started')->success()->send();
    }

    public function importSelectedSites(): void
    {
        $token = $this->token();

        if (! $token) {
            return;
        }

        $siteIds = $token->sites()
            ->whereIn('id', array_map('intval', $this->selectedSiteIds))
            ->pluck('id')
            ->all();

        if ($siteIds === [] && $token->sites()->count() === 1) {
            $siteIds = [(int) $token->sites()->value('id')];
            $this->selectedSiteIds = $siteIds;
        }

        if ($siteIds === []) {
            Notification::make()
                ->title('Select at least one site')
                ->warning()
                ->send();

            return;
        }

        app(AccountOnboardingService::class)->selectSites($token, $siteIds);
        $days = (int) config('onboarding.initial_import_days', 90);

        foreach ($siteIds as $siteId) {
            $this->launch(
                "seo:import-all-gsc --user-id={$token->user_id} --site-id={$siteId} --days={$days}",
                'onboarding-import.log',
            );
        }

        Notification::make()
            ->title('Initial keyword import started')
            ->body("Importing the latest {$days} days for ".count($siteIds).' selected site(s).')
            ->success()
            ->send();
    }

    public function skipFirstContent(): void
    {
        $token = $this->token();

        if (! $token || ! $token->onboarding_keywords_imported_at || $this->selectedKeywordCount() > 0) {
            return;
        }

        app(AccountOnboardingService::class)->skipFirstContent($token);
        Notification::make()
            ->title('Content step skipped')
            ->body('You can generate content later after keywords become available.')
            ->success()
            ->send();
    }

    public function refreshProgress(): void
    {
        $token = $this->token();

        if ($token) {
            app(AccountOnboardingService::class)->refresh($token);
        }
    }

    public function publishingSettingsUrl(): string
    {
        return PublishingSettings::getUrl();
    }

    public function contentSettingsUrl(): string
    {
        return ContentSettings::getUrl();
    }

    public function keywordsUrl(): string
    {
        return SeoKeywordResource::getUrl('index');
    }

    private function launch(string $artisanCommand, string $logFile): void
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';

        exec(
            'cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg($php).' artisan '.$artisanCommand
            .' >> '.escapeshellarg(storage_path('logs/'.$logFile)).' 2>&1 &',
        );
    }
}
