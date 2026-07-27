<?php

namespace App\Services;

use App\Models\GoogleOauthToken;
use App\Models\GscSite;
use App\Models\PublishingSetting;
use App\Models\SeoContentDraft;

class AccountOnboardingService
{
    public function totalSteps(): int
    {
        return max(1, (int) config('onboarding.total_steps', 6));
    }

    public function start(GoogleOauthToken $token): GoogleOauthToken
    {
        PublishingSetting::firstOrCreate(['user_id' => $token->user_id]);

        if ($token->wasRecentlyCreated) {
            $token->forceFill([
                'is_onboarding' => true,
                'onboarding_step' => 1,
                'onboarding_completed_at' => null,
            ])->save();
        }

        return $this->refresh($token);
    }

    public function markSitesSynced(GoogleOauthToken $token): GoogleOauthToken
    {
        $token->update(['onboarding_sites_synced_at' => now()]);
        $this->applySelectedSiteActivity($token);

        return $this->refresh($token);
    }

    /**
     * @param  array<int, int>  $siteIds
     */
    public function selectSites(GoogleOauthToken $token, array $siteIds): GoogleOauthToken
    {
        $siteIds = $token->sites()
            ->whereIn('id', array_values(array_unique(array_map('intval', $siteIds))))
            ->pluck('id')
            ->all();

        $token->update([
            'onboarding_selected_site_ids' => $siteIds,
            'onboarding_sites_selected_at' => now(),
            'onboarding_keywords_imported_at' => null,
        ]);
        $this->applySelectedSiteActivity($token);

        return $this->refresh($token);
    }

    public function markImportProgress(GscSite $site): void
    {
        $token = $site->googleOauthToken;

        if (! $token?->is_onboarding) {
            return;
        }

        $selectedIds = $token->onboarding_selected_site_ids ?? [];

        if ($selectedIds === []) {
            return;
        }

        $selectionTime = $token->onboarding_sites_selected_at;
        $allImported = $token->sites()
            ->whereIn('id', $selectedIds)
            ->get()
            ->every(fn (GscSite $selected): bool => $selected->last_imported_at !== null
                && (! $selectionTime || $selected->last_imported_at->gte($selectionTime)));

        if ($allImported) {
            $token->update(['onboarding_keywords_imported_at' => now()]);
            $this->refresh($token);
        }
    }

    public function markFirstContentGenerated(SeoContentDraft $draft): void
    {
        $token = $draft->group?->site?->googleOauthToken;

        if (! $token?->is_onboarding) {
            return;
        }

        $token->update(['onboarding_first_content_at' => now()]);
        $this->refresh($token);
    }

    public function skipFirstContent(GoogleOauthToken $token): GoogleOauthToken
    {
        $token->update(['onboarding_first_content_skipped_at' => now()]);

        return $this->refresh($token);
    }

    public function markPublishingReviewedForUser(int $userId): void
    {
        $this->updateActiveTokens($userId, 'onboarding_publishing_reviewed_at');
    }

    public function markContentSettingsReviewedForUser(int $userId): void
    {
        $this->updateActiveTokens($userId, 'onboarding_content_settings_reviewed_at');
    }

    public function refresh(GoogleOauthToken $token): GoogleOauthToken
    {
        $token->refresh();

        if ($token->onboarding_completed_at) {
            return $token;
        }

        $milestones = [
            true,
            $token->onboarding_sites_synced_at !== null,
            $token->onboarding_keywords_imported_at !== null,
            $token->onboarding_first_content_at !== null || $token->onboarding_first_content_skipped_at !== null,
            $token->onboarding_publishing_reviewed_at !== null,
            $token->onboarding_content_settings_reviewed_at !== null,
        ];
        $step = 0;

        foreach ($milestones as $completed) {
            if (! $completed) {
                break;
            }

            $step++;
        }

        $complete = $step >= $this->totalSteps();
        $token->forceFill([
            'onboarding_step' => $step,
            'is_onboarding' => ! $complete,
            'onboarding_completed_at' => $complete ? now() : null,
        ])->save();

        return $token->refresh();
    }

    private function updateActiveTokens(int $userId, string $timestampColumn): void
    {
        GoogleOauthToken::query()
            ->where('user_id', $userId)
            ->where('is_onboarding', true)
            ->get()
            ->each(function (GoogleOauthToken $token) use ($timestampColumn): void {
                $token->update([$timestampColumn => now()]);
                $this->refresh($token);
            });
    }

    private function applySelectedSiteActivity(GoogleOauthToken $token): void
    {
        $selectedIds = $token->onboarding_selected_site_ids ?? [];

        if ($selectedIds === []) {
            return;
        }

        $token->sites()->update(['is_active' => false]);
        $token->sites()->whereIn('id', $selectedIds)->update(['is_active' => true]);
    }
}
