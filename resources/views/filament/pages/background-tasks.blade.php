<x-filament-panels::page>
    @php
        $activeTasks = $this->getActiveTasks();
    @endphp

    <div class="space-y-6">
        <!-- Top Info Header Card -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Active Background Workers</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Monitor and manage all asynchronous processes running on the server. If a process is hung or blocked, you can manually terminate it to release its lock.
            </p>
        </div>

        @if (empty($activeTasks))
            <!-- Empty State Card -->
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                    <x-filament::icon
                        icon="heroicon-o-cpu-chip"
                        class="h-8 w-8 text-gray-400 dark:text-gray-500"
                    />
                </div>
                <h3 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No active background tasks</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All tasks are currently idle. Launch imports or generation processes to see them here.</p>
            </div>
        @else
            <!-- Active Tasks Table -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Task Name</th>
                                <th scope="col" class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Command</th>
                                <th scope="col" class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-center">Process ID</th>
                                <th scope="col" class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Started</th>
                                <th scope="col" class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Elapsed Time</th>
                                <th scope="col" class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
                            @foreach ($activeTasks as $lockKey => $task)
                                @php
                                    $elapsedSec = time() - $task['start_time'];
                                    $elapsedMin = round($elapsedSec / 60);
                                    
                                    if ($elapsedMin < 1) {
                                        $elapsedText = 'Just now';
                                    } elseif ($elapsedMin < 60) {
                                        $elapsedText = $elapsedMin . 'm ago';
                                    } else {
                                        $elapsedText = round($elapsedMin / 60) . 'h ago';
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $task['name'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <code class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800 dark:text-gray-300">
                                            {{ $task['command'] }}
                                        </code>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                            {{ $task['pid'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ date('H:i:s Y-m-d', $task['start_time']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <span class="{{ $elapsedSec > 10800 ? 'text-red-600 font-semibold dark:text-red-400' : 'text-gray-900 dark:text-gray-300' }}">
                                            {{ $elapsedText }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                                        <x-filament::button
                                            color="danger"
                                            size="sm"
                                            icon="heroicon-m-power"
                                            wire:click="killTask('{{ $lockKey }}')"
                                            wire:confirm="Are you sure you want to terminate this background task and release its lock?"
                                        >
                                            Kill Process
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
