<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class BackgroundTaskManager
{
    protected static string $registryKey = 'seo:active-tasks';

    public static function register(string $lockKey, string $name, string $command): void
    {
        $pid = getmypid();
        $tasks = Cache::get(self::$registryKey, []);
        $tasks[$lockKey] = [
            'pid' => $pid,
            'name' => $name,
            'command' => $command,
            'start_time' => time(),
        ];
        Cache::put(self::$registryKey, $tasks, 10800);
        Cache::put($lockKey, [
            'pid' => $pid,
            'start_time' => time(),
        ], 10800);
    }

    public static function unregister(string $lockKey): void
    {
        $tasks = Cache::get(self::$registryKey, []);
        unset($tasks[$lockKey]);
        Cache::put(self::$registryKey, $tasks, 10800);
        Cache::forget($lockKey);
    }

    public static function listActive(): array
    {
        $tasks = Cache::get(self::$registryKey, []);
        $active = [];
        $dirty = false;

        foreach ($tasks as $lockKey => $data) {
            $pid = $data['pid'] ?? null;
            if ($pid && self::isProcessRunning($pid)) {
                $active[$lockKey] = $data;
            } else {
                unset($tasks[$lockKey]);
                Cache::forget($lockKey);
                $dirty = true;
            }
        }

        if ($dirty) {
            Cache::put(self::$registryKey, $tasks, 10800);
        }

        return $active;
    }

    public static function kill(string $lockKey): bool
    {
        $tasks = Cache::get(self::$registryKey, []);
        if (!isset($tasks[$lockKey])) {
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
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }
        $output = [];
        exec("ps -p {$pid}", $output);
        return count($output) > 1;
    }
}
