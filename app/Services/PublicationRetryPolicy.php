<?php

namespace App\Services;

class PublicationRetryPolicy
{
    public function canRun(string $draftStatus, bool $autoPublishEnabled, bool $multipleChannels): bool
    {
        if (! $autoPublishEnabled) {
            return false;
        }

        return $draftStatus === 'approved'
            || ($draftStatus === 'published' && $multipleChannels);
    }

    /**
     * @param  array<int, string>  $configuredChannels
     * @param  array<int, string>  $successfulChannels
     * @return array<int, string>
     */
    public function pendingChannels(array $configuredChannels, array $successfulChannels, bool $multipleChannels): array
    {
        if (! $multipleChannels) {
            return $configuredChannels;
        }

        return array_values(array_diff($configuredChannels, $successfulChannels));
    }
}
