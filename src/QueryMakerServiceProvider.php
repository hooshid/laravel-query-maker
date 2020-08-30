<?php

namespace Hooshid\QueryMaker;

use Illuminate\Support\ServiceProvider;

class QueryMakerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole() && function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/../config/query-maker.php' => config_path('query-maker.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/query-maker.php', 'query-maker');
    }
}
