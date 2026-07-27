<x-filament-panels::page>
    @php
        $token = $this->token();
        $sites = $this->sites();
        $step = $token?->onboarding_step ?? 0;
        $total = $this->totalSteps();
        $progress = min(100, (int) round(($step / max(1, $total)) * 100));
    @endphp

    <div wire:poll.5s="refreshProgress" class="mx-auto w-full max-w-4xl space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Step {{ min($step + 1, $total) }} of {{ $total }}</p>
                    <h2 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">Set up your SEO workspace</h2>
                </div>
                <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $progress }}%</div>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" aria-label="Onboarding progress">
                <div class="h-full rounded-full bg-primary-600 transition-all duration-200 motion-reduce:transition-none" style="width: {{ $progress }}%"></div>
            </div>
        </section>

        @if (! $token)
            <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Connect Google Search Console</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Connect the Google account that has access to the sites you want to manage.</p>
                <x-filament::button class="mt-5" tag="a" href="/google/connect" icon="heroicon-o-link">Connect Google account</x-filament::button>
            </section>
        @elseif ($step < 2)
            <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Finding your Search Console sites</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Google is checking which properties are available to {{ $token->email }}.</p>
                <x-filament::button class="mt-5" wire:click="syncSites" icon="heroicon-o-arrow-path">Check again</x-filament::button>
            </section>
        @elseif ($step < 3)
            <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                @if ($sites->isEmpty())
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">No Search Console sites found</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Confirm this Google account has Search Console access, then check again. No keyword import will run until a site is available.</p>
                    <x-filament::button class="mt-5" wire:click="syncSites" icon="heroicon-o-arrow-path">Sync sites again</x-filament::button>
                @elseif ($sites->count() === 1)
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Importing your first site</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">We found {{ $sites->first()->site_url }} and are importing its latest {{ config('onboarding.initial_import_days') }} days automatically.</p>
                    <x-filament::button class="mt-5" wire:click="importSelectedSites" icon="heroicon-o-cloud-arrow-down">Restart import</x-filament::button>
                @else
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Choose which sites to import</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">This Google account has {{ $sites->count() }} sites. Only selected sites will have keywords imported.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach ($sites as $site)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4 transition-colors duration-200 hover:border-primary-400 dark:border-white/10">
                                <input type="checkbox" wire:model="selectedSiteIds" value="{{ $site->id }}" class="mt-1 rounded border-gray-300 text-primary-600 focus-visible:ring-2 focus-visible:ring-primary-500">
                                <span class="min-w-0">
                                    <span class="block font-medium text-gray-950 dark:text-white">{{ $site->name ?: $site->site_url }}</span>
                                    <span class="block truncate text-sm text-gray-600 dark:text-gray-300">{{ $site->site_url }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-filament::button class="mt-5" wire:click="importSelectedSites" icon="heroicon-o-cloud-arrow-down">Import selected sites</x-filament::button>
                @endif
            </section>
        @elseif ($step < 4)
            <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Generate your first article</h3>
                @if ($this->selectedKeywordCount() > 0)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Your keywords are ready. Choose one and generate your first article using the saved site defaults.</p>
                    <x-filament::button class="mt-5" tag="a" :href="$this->keywordsUrl()" icon="heroicon-o-sparkles">Choose a keyword</x-filament::button>
                @else
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">The import completed successfully but Google returned no keywords for the selected sites. You can continue and generate content later.</p>
                    <x-filament::button class="mt-5" wire:click="skipFirstContent" color="gray">Continue without an article</x-filament::button>
                @endif
            </section>
        @elseif ($step < 5)
            <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Review automatic publishing</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Choose whether new articles should be emailed, sent to WordPress, delivered to a webhook, or kept for manual review.</p>
                <x-filament::button class="mt-5" tag="a" :href="$this->publishingSettingsUrl()" icon="heroicon-o-paper-airplane">Review publishing settings</x-filament::button>
            </section>
        @elseif ($step < 6)
            <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Review automatic content generation</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Confirm how many articles each site may generate, the keyword strategy, schedule interval, language, and article defaults.</p>
                <x-filament::button class="mt-5" tag="a" :href="$this->contentSettingsUrl()" icon="heroicon-o-cog-6-tooth">Review content settings</x-filament::button>
            </section>
        @else
            <section class="rounded-xl border border-success-200 bg-success-50 p-6 dark:border-success-500/20 dark:bg-success-500/10">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Your SEO workspace is ready</h3>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">Google, keywords, content, publishing, and automation settings have been reviewed.</p>
                <x-filament::button class="mt-5" tag="a" href="/admin" icon="heroicon-o-arrow-right">Open dashboard</x-filament::button>
            </section>
        @endif
    </div>
</x-filament-panels::page>
