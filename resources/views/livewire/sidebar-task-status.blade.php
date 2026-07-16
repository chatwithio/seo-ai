<div wire:poll.3s aria-live="polite" aria-atomic="true">
    @if ($activeTasks !== [])
        @php
            $task = reset($activeTasks);
            $taskCount = count($activeTasks);
            $hasProgress = isset($task['progress_total']);
            $progress = max(0, min(100, (int) ($task['progress_percent'] ?? 0)));
            $statusText = $task['status_text'] ?? $task['name'] ?? 'Working in background...';
        @endphp

        <div class="px-3 pb-3 pt-2">
            <a
                href="{{ route('filament.admin.pages.background-tasks') }}"
                class="group block cursor-pointer rounded-xl border border-amber-200 bg-amber-50 p-3 shadow-sm transition-colors duration-200 hover:border-amber-300 hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 motion-reduce:transition-none dark:border-amber-800/70 dark:bg-amber-950/40 dark:hover:border-amber-700 dark:hover:bg-amber-950/70 dark:focus-visible:ring-offset-gray-900"
                aria-label="View {{ $taskCount }} active background {{ \Illuminate\Support\Str::plural('job', $taskCount) }}"
            >
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-500 opacity-60 motion-reduce:animate-none"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-600 dark:bg-amber-400"></span>
                    </span>
                    <span class="min-w-0 flex-1 truncate text-xs font-semibold text-gray-950 dark:text-white">
                        {{ $taskCount === 1 ? 'Background job running' : $taskCount.' background jobs running' }}
                    </span>
                    @if ($hasProgress)
                        <span class="shrink-0 text-xs font-bold text-amber-800 dark:text-amber-300">{{ $progress }}%</span>
                    @endif
                </div>

                <div class="mt-2 truncate text-xs text-gray-600 dark:text-gray-300" title="{{ $statusText }}">
                    {{ $statusText }}
                </div>

                <div
                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-amber-200 dark:bg-amber-900"
                    role="progressbar"
                    aria-label="Background job progress"
                    @if ($hasProgress)
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $progress }}"
                    @endif
                >
                    <div
                        class="h-full rounded-full bg-amber-600 transition-all duration-300 motion-reduce:transition-none dark:bg-amber-400"
                        style="width: {{ $hasProgress ? max(4, $progress) : 35 }}%;"
                    ></div>
                </div>

                <div class="mt-2 flex items-center justify-between text-xs">
                    <span class="font-medium text-amber-800 dark:text-amber-300">View Active Jobs</span>
                    @if ($taskCount > 1)
                        <span class="text-gray-500 dark:text-gray-400">+{{ $taskCount - 1 }} more</span>
                    @endif
                </div>
            </a>
        </div>
    @else
        <div class="px-3 pb-3 pt-2">
            <a
                href="{{ route('filament.admin.pages.background-tasks') }}"
                class="group flex cursor-pointer items-center gap-2.5 rounded-xl border border-gray-200 bg-white px-3 py-2.5 shadow-sm transition-colors duration-200 hover:border-gray-300 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600 dark:hover:bg-gray-800 dark:focus-visible:ring-offset-gray-900"
                aria-label="No background jobs are running. Open Active Jobs."
            >
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <span class="block text-xs font-semibold text-gray-950 dark:text-white">Background tasks</span>
                    <span class="block text-xs text-gray-600 dark:text-gray-300">No jobs running</span>
                </span>
                <x-filament::icon
                    icon="heroicon-m-chevron-right"
                    class="h-4 w-4 shrink-0 text-gray-400 transition-colors duration-200 group-hover:text-gray-600 motion-reduce:transition-none dark:group-hover:text-gray-200"
                    aria-hidden="true"
                />
            </a>
        </div>
    @endif
</div>
