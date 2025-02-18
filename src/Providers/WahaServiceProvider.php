<?php

namespace Rosandi\WAHA\Providers;

use Illuminate\Support\Facades\Route;
use Rosandi\WAHA\Services\WahaService;
use Illuminate\Support\ServiceProvider;

class WahaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/waha.php', 'waha');
        $this->app->singleton('waha', function ($app) {
            return new WahaService();
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/waha.php' => config_path('waha.php'),
            ], 'waha-config');
        }

        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        // Tambahkan route sementara untuk debugging
        Route::get('/waha-test', function () {
            return response()->json(['message' => 'hello world']);
        });
    }
}
