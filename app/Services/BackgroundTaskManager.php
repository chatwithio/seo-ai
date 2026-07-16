<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class BackgroundTaskManager
{
    protected static string $registryKey = 'seo:active-tasks';

    public static function register(
        string $lockKey,
        string $name,
        string $command,
        ?int $userId = null,
        ?int $siteId = null,
    ): void {
        self::updateRegistry(function (array $tasks) use ($lockKey, $name, $command, $userId, $siteId) {
            $tasks[$lockKey] = [
                'pid' => getmypid(),
                'user_id' => $userId,
                'site_id' => $siteId,
                'name' => $name,
                'command' => $command,
                'start_time' => time(),
            ];

            return $tasks;
        });

        Cache::forever($lockKey, [
            'pid' => getmypid(),
            'start_time' => time(),
        ]);
    }

    public static function unregister(string $lockKey): void
    {
        self::updateRegistry(function (array $tasks) use ($lockKey) {
            unset($tasks[$lockKey]);

            return $tasks;
        });

        Cache::forget($lockKey);
    }

    public static function update(string $lockKey, array $attributes): void
    {
        self::updateRegistry(function (array $tasks) use ($lockKey, $attributes) {
            if (isset($tasks[$lockKey])) {
                $tasks[$lockKey] = array_merge($tasks[$lockKey], $attributes, [
                    'updated_at' => time(),
                ]);
            }

            return $tasks;
        });
    }

    public static function findActiveForUser(int $userId, string $name): ?array
    {
        foreach (self::listActive() as $lockKey => $task) {
            if ((int) ($task['user_id'] ?? 0) !== $userId || ($task['name'] ?? '') !== $name) {
                continue;
            }

            return array_merge($task, ['lock_key' => $lockKey]);
        }

        return null;
    }

    public static function listActive(): array
    {
        $active = [];

        self::updateRegistry(function (array $tasks) use (&$active) {
            foreach ($tasks as $lockKey => $data) {
                // The cache lock is the cross-process source of truth. PID
                // visibility can differ between PHP-FPM and CLI namespaces,
                // which previously made real tasks disappear from the page.
                if (Cache::has($lockKey)) {
                    $active[$lockKey] = $data;
                } else {
                    unset($tasks[$lockKey]);
                }
            }

            return $tasks;
        });

        return $active;
    }

    public static function kill(string $lockKey): bool
    {
        $tasks = Cache::get(self::$registryKey, []);
        if (! isset($tasks[$lockKey])) {
            Cache::forget($lockKey);

            return false;
        }

        $pid = $tasks[$lockKey]['pid'] ?? null;
        if ($pid) {
            if (self::isProcessRunning($pid)) {
                if (function_exists('posix_kill')) {
                    posix_kill($pid, 9);
                } else {
                    exec("kill -9 {$pid}");
                }
            }
        }

        self::unregister($lockKey);

        return true;
    }

    public static function isProcessRunning(int $pid): bool
    {
        if (is_dir("/proc/{$pid}")) {
            return true;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }
        $output = [];
        exec("ps -p {$pid}", $output);

        return count($output) > 1;
    }

    private static function updateRegistry(callable $callback): void
    {
        Cache::lock(self::$registryKey.':mutex', 10)->block(5, function () use ($callback) {
            $tasks = Cache::get(self::$registryKey, []);
            Cache::forever(self::$registryKey, $callback($tasks));
        });
    }
}
