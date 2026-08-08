<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    protected $signature = 'user:list
        {--search= : Filter by ID, name, or email}
        {--limit=100 : Maximum users to show}
        {--json : Return machine-readable JSON}';

    protected $description = 'List SaaS user accounts and their current site/content totals';

    public function handle(): int
    {
        $search = trim((string) $this->option('search'));
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $users = User::query()
            ->withCount(['sites', 'keywords', 'contentDrafts'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhereKey((int) $search);
                    }
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $rows = $users->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'sites' => $user->sites_count,
            'keywords' => $user->keywords_count,
            'articles' => $user->content_drafts_count,
            'created_at' => $user->created_at?->toDateTimeString(),
        ])->all();

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Email', 'Sites', 'Keywords', 'Articles', 'Created'], $rows);
        $this->newLine();
        $this->info(count($rows).' user(s) shown.');

        return self::SUCCESS;
    }
}
