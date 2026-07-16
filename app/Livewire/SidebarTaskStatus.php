<?php

namespace App\Livewire;

use App\Services\BackgroundTaskManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SidebarTaskStatus extends Component
{
    public function getActiveTasks(): array
    {
        $userId = (int) auth()->id();

        if ($userId < 1) {
            return [];
        }

        return array_filter(
            BackgroundTaskManager::listActive(),
            fn (array $task): bool => (int) ($task['user_id'] ?? 0) === $userId,
        );
    }

    public function render(): View
    {
        return view('livewire.sidebar-task-status', [
            'activeTasks' => $this->getActiveTasks(),
        ]);
    }
}
