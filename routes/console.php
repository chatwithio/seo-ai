<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\GscSite;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $sites = GscSite::where('is_active', true)->get();
    foreach ($sites as $site) {
        dispatch(new \App\Jobs\ImportGscKeywordsJob($site->id));
    }
})->dailyAt('02:00');

Schedule::call(function () {
    $sites = GscSite::where('is_active', true)->get();
    foreach ($sites as $site) {
        Artisan::call('seo:aggregate-keywords', ['site_id' => $site->id]);
    }
})->dailyAt('04:00');
