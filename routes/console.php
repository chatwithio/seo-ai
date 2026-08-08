<?php

use App\Jobs\ImportGscKeywordsJob;
use App\Models\GscSite;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $sites = GscSite::where('is_active', true)
        ->whereDoesntHave(
            'googleOauthToken',
            fn ($query) => $query->where('is_onboarding', true),
        )
        ->get();
    foreach ($sites as $site) {
        dispatch(new ImportGscKeywordsJob($site->id));
    }
})->dailyAt('02:00');

Schedule::call(function () {
    $sites = GscSite::where('is_active', true)->get();
    foreach ($sites as $site) {
        Artisan::call('seo:aggregate-keywords', ['site_id' => $site->id]);
    }
})->dailyAt('04:00');

Schedule::call(function () {
    $sites = GscSite::where('is_active', true)
        ->where('agent_enabled', true)
        ->where('auto_content_enabled', false)
        ->get();
    foreach ($sites as $site) {
        Artisan::call('seo:run-agent', ['site_id' => $site->id]);
    }
})->dailyAt('06:00');

Schedule::command('seo:auto-generate-content --scheduled')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/auto-content.log'));

Schedule::command('seo:send-weekly-emails')
    ->mondays()
    ->at('09:00')
    ->withoutOverlapping();

Schedule::command('seo:refresh-content-improvements')
    ->mondays()
    ->at('05:00')
    ->withoutOverlapping(180)
    ->appendOutputTo(storage_path('logs/content-improvements.log'));

Schedule::command('queue:work --stop-when-empty --sleep=1 --tries=1 --timeout=1800 --max-time=1740')
    ->everyMinute()
    ->withoutOverlapping(35)
    ->runInBackground();
