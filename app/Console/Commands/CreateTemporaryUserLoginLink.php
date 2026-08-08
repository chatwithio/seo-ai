<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TemporaryUserLoginService;
use Illuminate\Console\Command;

class CreateTemporaryUserLoginLink extends Command
{
    protected $signature = 'user:login-link
        {user : User ID or exact email address}
        {--minutes=15 : Link lifetime from 1 to 60 minutes}
        {--redirect=/admin : Local path after login}';

    protected $description = 'Create a one-time temporary customer login URL for debugging';

    public function handle(TemporaryUserLoginService $links): int
    {
        $selector = trim((string) $this->argument('user'));
        $user = User::query()
            ->when(
                ctype_digit($selector),
                fn ($query) => $query->whereKey((int) $selector),
                fn ($query) => $query->where('email', $selector),
            )
            ->first();

        if (! $user) {
            $this->error('User not found. Use user:list to find the account.');

            return self::FAILURE;
        }

        $minutes = (int) $this->option('minutes');

        if ($minutes < 1 || $minutes > 60) {
            $this->error('--minutes must be between 1 and 60.');

            return self::FAILURE;
        }

        try {
            $issued = $links->issue($user, $minutes, (string) $this->option('redirect'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->warn('This URL is single-use. Treat it like a password and do not put it in tickets or logs.');
        $this->line($issued['url']);
        $this->newLine();
        $this->info('Expires: '.$issued['link']->expires_at->toDateTimeString().' ('.config('app.timezone').')');

        return self::SUCCESS;
    }
}
