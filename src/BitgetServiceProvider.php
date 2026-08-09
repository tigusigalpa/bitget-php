<?php

declare(strict_types=1);

namespace Tigusigalpa\Bitget;

use Illuminate\Support\ServiceProvider;

/**
 * Laravel 10-13 integration: binds a Client singleton from config/bitget.php
 * and publishes that config file.
 */
class BitgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bitget.php', 'bitget');

        $this->app->singleton(Client::class, function () {
            return new Client(
                apiKey: (string) config('bitget.api_key', ''),
                secretKey: (string) config('bitget.secret_key', ''),
                passphrase: (string) config('bitget.passphrase', ''),
                demoTrading: (bool) config('bitget.demo', false),
                baseUrl: (string) config('bitget.base_url', Client::DEFAULT_BASE_URL),
                locale: (string) config('bitget.locale', 'en-US'),
            );
        });

        $this->app->alias(Client::class, 'bitget');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bitget.php' => config_path('bitget.php'),
            ], 'bitget-config');
        }
    }
}
