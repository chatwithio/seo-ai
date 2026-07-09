<x-filament-panels::page>
    @php
        $tokens = $this->getTokens();
        $draftsCount = $this->getDraftsCount();
        $lowCtrCount = $this->getLowCtrKeywordsCount();
    @endphp

    <div class="space-y-8 w-full">
        @if ($tokens->isEmpty())
            <!-- STATE A: First-time users (No connected accounts) -->
            <div class="text-gray-950 dark:text-white" style="font-size: 1.25rem; line-height: 1.8; margin-bottom: 2rem;">
                <p style="margin-bottom: 2rem;">
                    <a href="/google/connect" style="color: #3b82f6; text-decoration: underline;">Connect your Search Console account</a> and our AI Agents will help you get <a href="/admin/seo-content-drafts" style="color: #3b82f6; text-decoration: underline;">fresh content</a> for your website and get a clear view of your <a href="/admin/seo-keywords" style="color: #3b82f6; text-decoration: underline;">more relevant keywords</a>.
                </p>
                
                <div style="margin-bottom: 2rem;">
                    <a href="/google/connect" style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: #22c55e; color: #ffffff; font-weight: 500; padding: 0.625rem 1.25rem; border-radius: 0.5rem; text-decoration: none; font-size: 1rem; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#16a34a'" onmouseout="this.style.backgroundColor='#22c55e'">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        <span>Connect Google Account</span>
                    </a>
                </div>
                
                <p>
                    <a href="/google/connect" style="color: #3b82f6; text-decoration: underline;">Connect your Search Console Account</a> to start generating content and insights.
                </p>
            </div>

        @elseif ($draftsCount === 0)
            <!-- STATE B: Accounts connected but no drafts/articles yet -->
            <div class="text-gray-950 dark:text-white" style="font-size: 1.25rem; line-height: 1.8; margin-bottom: 2rem;">
                <p style="margin-bottom: 1.5rem;">
                    Congratulations. Your account is connected…
                </p>
                <p style="margin-bottom: 2rem;">
                    Now launch your AI Agent to 
                    <a href="/admin/seo-keywords" style="color: #3b82f6; text-decoration: underline;">Review your top performing keywords</a>, 
                    <a href="/admin/seo-keywords" style="color: #3b82f6; text-decoration: underline;">Discover high impression but low CTR keywords</a>, 
                    <a href="/admin/seo-content-drafts" style="color: #3b82f6; text-decoration: underline;">Create fresh content for your website</a> 
                    or create your custom AI Agent.
                </p>

                <div style="margin-bottom: 2rem;">
                    <a href="/google/connect" style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: #22c55e; color: #ffffff; font-weight: 500; padding: 0.625rem 1.25rem; border-radius: 0.5rem; text-decoration: none; font-size: 1rem; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#16a34a'" onmouseout="this.style.backgroundColor='#22c55e'">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        <span>Connect Google Account</span>
                    </a>
                </div>
            </div>

            <!-- Connected Accounts Table -->
            <div style="margin-top: 3rem;">
                <div class="border rounded-xl dark:border-gray-700 overflow-hidden">
                    <table class="w-full text-left text-sm divide-y dark:divide-gray-700 bg-white dark:bg-gray-900">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400">Email Address</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400">Status</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400 hidden sm:table-cell">Provider</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400 hidden md:table-cell">Expires at</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            @foreach ($tokens as $token)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                    <td style="padding: 1rem 1.5rem;" class="font-semibold text-gray-900 dark:text-white">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                                <x-filament::icon icon="heroicon-s-user" class="w-4 h-4 text-gray-500" />
                                            </div>
                                            {{ $token->email }}
                                        </div>
                                    </td>
                                    <td style="padding: 1rem 1.5rem;">
                                        <x-filament::badge color="success" icon="heroicon-m-check-circle">Connected</x-filament::badge>
                                    </td>
                                    <td style="padding: 1rem 1.5rem;" class="text-gray-500 hidden sm:table-cell">{{ ucfirst($token->provider) }}</td>
                                    <td style="padding: 1rem 1.5rem;" class="text-gray-500 hidden md:table-cell">
                                        {{ $token->expires_at ? \Carbon\Carbon::parse($token->expires_at)->format('M j, Y') : 'N/A' }}
                                    </td>
                                    <td style="padding: 1rem 1.5rem;" class="text-right">
                                        <x-filament::button 
                                            color="danger" 
                                            size="sm" 
                                            variant="text"
                                            wire:click="deleteToken({{ $token->id }})"
                                            wire:confirm="Are you sure you want to disconnect this Google account?"
                                        >
                                            Delete
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        @else
            <!-- STATE C: Connected and has generated content -->
            <div class="text-gray-950 dark:text-white" style="font-size: 1.25rem; line-height: 1.8; margin-bottom: 2rem;">
                <p style="margin-bottom: 1.5rem;">
                    Hi {{ explode(' ', auth()->user()->name)[0] ?? 'User' }},
                </p>
                <p style="margin-bottom: 1.5rem;">
                    Today we have created for you <strong>{{ $draftsCount }} new articles</strong> for High Intent Keywords we discover in your Google Search Console. 
                    <a href="/admin/seo-content-drafts" style="color: #3b82f6; text-decoration: underline;">Please review them here</a>.
                </p>
                <p style="margin-bottom: 2rem;">
                    Also, I found a list of keywords with high volume of impression, with low CTR. Please take a look and let me know if you want to 
                    <a href="/admin/seo-keywords" style="color: #3b82f6; text-decoration: underline;">improve the content for those keywords</a>.
                </p>

                <div style="margin-bottom: 2rem;">
                    <a href="/google/connect" style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: #22c55e; color: #ffffff; font-weight: 500; padding: 0.625rem 1.25rem; border-radius: 0.5rem; text-decoration: none; font-size: 1rem; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#16a34a'" onmouseout="this.style.backgroundColor='#22c55e'">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        <span>Connect Google Account</span>
                    </a>
                </div>
            </div>

            <!-- Connected Accounts Table -->
            <div style="margin-top: 3rem;">
                <div class="border rounded-xl dark:border-gray-700 overflow-hidden">
                    <table class="w-full text-left text-sm divide-y dark:divide-gray-700 bg-white dark:bg-gray-900">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400">Email Address</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400">Status</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400 hidden sm:table-cell">Provider</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400 hidden md:table-cell">Expires at</th>
                                <th style="padding: 0.75rem 1.5rem;" class="font-semibold text-gray-500 dark:text-gray-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            @foreach ($tokens as $token)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                    <td style="padding: 1rem 1.5rem;" class="font-semibold text-gray-900 dark:text-white">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                                <x-filament::icon icon="heroicon-s-user" class="w-4 h-4 text-gray-500" />
                                            </div>
                                            {{ $token->email }}
                                        </div>
                                    </td>
                                    <td style="padding: 1rem 1.5rem;">
                                        <x-filament::badge color="success" icon="heroicon-m-check-circle">Connected</x-filament::badge>
                                    </td>
                                    <td style="padding: 1rem 1.5rem;" class="text-gray-500 hidden sm:table-cell">{{ ucfirst($token->provider) }}</td>
                                    <td style="padding: 1rem 1.5rem;" class="text-gray-500 hidden md:table-cell">
                                        {{ $token->expires_at ? \Carbon\Carbon::parse($token->expires_at)->format('M j, Y') : 'N/A' }}
                                    </td>
                                    <td style="padding: 1rem 1.5rem;" class="text-right">
                                        <x-filament::button 
                                            color="danger" 
                                            size="sm" 
                                            variant="text"
                                            wire:click="deleteToken({{ $token->id }})"
                                            wire:confirm="Are you sure you want to disconnect this Google account?"
                                        >
                                            Delete
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
