<?php

namespace App\Services;

use App\Models\SeoAuditLog;
use App\Models\TemporaryUserLoginLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TemporaryUserLoginService
{
    /**
     * @return array{link: TemporaryUserLoginLink, token: string, url: string}
     */
    public function issue(User $user, int $minutes = 15, string $redirectPath = '/admin'): array
    {
        $minutes = max(1, min($minutes, 60));
        $redirectPath = $this->safeRedirectPath($redirectPath);
        $token = Str::random(64);

        $link = DB::transaction(function () use ($user, $minutes, $redirectPath, $token): TemporaryUserLoginLink {
            TemporaryUserLoginLink::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            return TemporaryUserLoginLink::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'redirect_path' => $redirectPath,
                'created_by' => 'console',
                'expires_at' => now()->addMinutes($minutes),
            ]);
        });

        SeoAuditLog::create([
            'user_id' => $user->id,
            'entity_type' => 'temporary_login',
            'entity_id' => $link->id,
            'action' => 'temporary_login_issued',
            'message' => "A temporary debug login link was issued for {$minutes} minutes.",
            'context' => [
                'expires_at' => $link->expires_at->toIso8601String(),
                'created_by' => 'console',
            ],
        ]);

        return [
            'link' => $link,
            'token' => $token,
            'url' => route('users.temporary-login', ['token' => $token]),
        ];
    }

    public function consume(string $token, ?string $ip, ?string $userAgent): ?TemporaryUserLoginLink
    {
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use ($tokenHash, $ip, $userAgent): ?TemporaryUserLoginLink {
            $link = TemporaryUserLoginLink::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $link || ! $link->isUsable() || ! $link->user()->exists()) {
                return null;
            }

            $link->update([
                'used_at' => now(),
                'used_ip' => $ip,
                'used_user_agent' => Str::limit((string) $userAgent, 1000, ''),
            ]);

            return $link->load('user');
        });
    }

    private function safeRedirectPath(string $path): string
    {
        $path = trim($path);

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new InvalidArgumentException('The redirect must be a local path beginning with one slash.');
        }

        return Str::limit($path, 500, '');
    }
}
