<x-filament-panels::page>
    @php
        $tokens = $this->getTokens();
        $draftsCount = $this->getDraftsCount();
        $lowCtrCount = $this->getLowCtrKeywordsCount();
        $topKeywordsCount = $this->getTopKeywordsCount();
        $managedSitesCount = $this->getManagedSitesCount();
        $latestImportAt = $this->getLatestImportAt();
        $topKeywords = $this->getTopKeywords();
        $opportunityKeywords = $this->getOpportunityKeywords();
        $importTask = $this->getImportTask();
        $siteSyncTask = $this->getSiteSyncTask();
    @endphp

    <div class="space-y-8" wire:poll.3s>
        @if ($siteSyncTask || $importTask)
            <div class="space-y-3">
                @foreach (array_filter([$siteSyncTask, $importTask]) as $task)
                    @php($percent = max(0, min(100, (int) ($task['progress_percent'] ?? 0))))
                    <div class="rounded-xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-800 dark:bg-primary-950/40">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="font-semibold text-gray-950 dark:text-white">{{ $task['status_text'] ?? $task['name'] }}</div>
                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $task['progress_current'] ?? 0 }} of {{ $task['progress_total'] ?? 0 }} completed
                                    @if (! empty($task['imported_rows']))
                                        · {{ number_format($task['imported_rows']) }} keyword rows received
                                    @elseif (! empty($task['synced_count']))
                                        · {{ number_format($task['synced_count']) }} sites found
                                    @endif
                                </div>
                            </div>
                            <strong class="text-primary-700 dark:text-primary-300">{{ $percent }}%</strong>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900">
                            <div class="h-full rounded-full bg-primary-600 transition-all duration-500" style="width: {{ $percent }}%;"></div>
                        </div>
                        <a href="/admin/background-tasks" class="mt-3 inline-block text-sm font-medium text-primary-700 underline dark:text-primary-300">View Active Jobs</a>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @if ($tokens->isEmpty())
                <h2 class="text-xl font-bold text-gray-950 dark:text-white">Connect Google Search Console</h2>
                <p class="mt-3 text-gray-600 dark:text-gray-300">
                    Connect your Google account, load your managed sites, and import the latest keywords. We will then show your best-performing keywords and your biggest content opportunities.
                </p>
            @else
                <h2 class="text-xl font-bold text-gray-950 dark:text-white">Your SEO opportunities are ready</h2>
                <p class="mt-3 text-gray-600 dark:text-gray-300">
                    Review your strongest keywords, find pages receiving impressions but too few clicks, and create useful website content directly from any keyword.
                </p>
                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-3 text-sm font-medium">
                    <a href="{{ $this->getTopKeywordsUrl() }}" class="text-primary-600 underline dark:text-primary-400">Review your top impression keywords</a>
                    <a href="{{ $this->getOpportunityKeywordsUrl() }}" class="text-primary-600 underline dark:text-primary-400">Discover high impression, low-click keywords</a>
                    <a href="{{ $this->getContentUrl() }}" class="text-primary-600 underline dark:text-primary-400">Create fresh content for your website</a>
                    <a href="{{ $this->getAiInstructionsUrl() }}" class="text-primary-600 underline dark:text-primary-400">Create your custom AI instructions</a>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="/google/connect" class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-success-600 px-4 py-2.5 font-semibold text-white transition-colors duration-200 hover:bg-success-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success-500 focus-visible:ring-offset-2 motion-reduce:transition-none dark:focus-visible:ring-offset-gray-900">
                    <x-filament::icon icon="heroicon-o-link" class="h-5 w-5" />
                    Connect Google Account
                </a>
                @if ($tokens->isNotEmpty())
                    <a href="/admin/gsc-sites" class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 font-semibold text-white transition-colors duration-200 hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 motion-reduce:transition-none dark:focus-visible:ring-offset-gray-900">
                        <x-filament::icon icon="heroicon-o-globe-alt" class="h-5 w-5" />
                        Manage Sites and Import Keywords
                    </a>
                @endif
            </div>
        </div>

        @if ($tokens->isNotEmpty())
            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Managed sites</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($managedSitesCount) }}</div>
                </div>
                <a href="{{ $this->getTopKeywordsUrl() }}" class="block cursor-pointer rounded-xl border border-gray-200 bg-white p-5 transition-colors duration-200 hover:border-primary-300 hover:bg-primary-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transition-none dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700 dark:hover:bg-primary-950/20">
                    <div class="text-sm text-gray-500">Top impression keywords</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($topKeywordsCount) }}</div>
                </a>
                <a href="{{ $this->getOpportunityKeywordsUrl() }}" class="block cursor-pointer rounded-xl border border-gray-200 bg-white p-5 transition-colors duration-200 hover:border-primary-300 hover:bg-primary-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transition-none dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700 dark:hover:bg-primary-950/20">
                    <div class="text-sm text-gray-500">Content opportunities</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($lowCtrCount) }}</div>
                </a>
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Articles</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($draftsCount) }}</div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-5 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="font-bold text-gray-950 dark:text-white">Top Keywords by Impressions</h2>
                                <p class="mt-1 text-sm text-gray-500">Keywords receiving more impressions than your account average.</p>
                            </div>
                            <a href="{{ $this->getTopKeywordsUrl() }}" class="text-sm font-medium text-primary-600 underline dark:text-primary-400">View all</a>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($topKeywords as $keyword)
                            <div class="flex items-center justify-between gap-4 p-4">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-gray-950 dark:text-white">{{ $keyword->query_text }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ number_format($keyword->total_clicks) }} clicks · {{ number_format($keyword->total_impressions) }} impressions · {{ number_format($keyword->avg_ctr, 2) }}% CTR</div>
                                </div>
                                <button type="button" wire:click="mountAction('generateKeywordContent', { keywordId: {{ $keyword->id }} })" class="shrink-0 cursor-pointer rounded-lg bg-success-600 px-3 py-2 text-xs font-semibold text-white transition-colors duration-200 hover:bg-success-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success-500 disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none">
                                    Generate Content
                                </button>
                            </div>
                        @empty
                            <div class="p-6 text-sm text-gray-500">Import keywords to see your top performers.</div>
                        @endforelse
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-5 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="font-bold text-gray-950 dark:text-white">High Impressions, Low Clicks</h2>
                                <p class="mt-1 text-sm text-gray-500">Add or improve website text for searches where Google shows your pages but users rarely click.</p>
                            </div>
                            <a href="{{ $this->getOpportunityKeywordsUrl() }}" class="text-sm font-medium text-primary-600 underline dark:text-primary-400">View all</a>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($opportunityKeywords as $keyword)
                            <div class="flex items-center justify-between gap-4 p-4">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-gray-950 dark:text-white">{{ $keyword->query_text }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ number_format($keyword->total_impressions) }} impressions · {{ number_format($keyword->total_clicks) }} clicks · {{ number_format($keyword->avg_ctr, 2) }}% CTR</div>
                                </div>
                                <button type="button" wire:click="mountAction('generateKeywordContent', { keywordId: {{ $keyword->id }} })" class="shrink-0 cursor-pointer rounded-lg bg-success-600 px-3 py-2 text-xs font-semibold text-white transition-colors duration-200 hover:bg-success-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success-500 disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none">
                                    Generate Content
                                </button>
                            </div>
                        @empty
                            <div class="p-6 text-sm text-gray-500">No high-impression, low-click opportunities were found yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-950 dark:text-white">Connected Google accounts</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $tokens->count() }} connected
                            @if ($latestImportAt)
                                · Latest keyword import {{ \Carbon\Carbon::parse($latestImportAt)->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($tokens as $token)
                            <span class="rounded-full bg-success-50 px-3 py-1 text-sm text-success-700 dark:bg-success-950 dark:text-success-300">{{ $token->email }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            If you do not know what SEO is or you do not have a Search Console account, please
            <a href="https://chatwith.io/s/link-to-whatsapp" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary-600 underline dark:text-primary-400">contact us on WhatsApp</a>.
        </div>
    </div>
</x-filament-panels::page>
