<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Symfony\Component\Mailer\Transport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->bound(\BladeUI\Icons\Factory::class)) {
            $this->app->make(\BladeUI\Icons\Factory::class)->add('brand', [
                'path' => resource_path('svg'),
                'prefix' => 'brand',
            ]);
        }

        Mail::extend('dsn', function (array $config) {
            $dsn = $config['dsn'] ?? null;

            if (blank($dsn)) {
                throw new InvalidArgumentException('MAILER_DSN is required for the DSN mail transport.');
            }

            return Transport::fromDsn($dsn);
        });
    }
}
