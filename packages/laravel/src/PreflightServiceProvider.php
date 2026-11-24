<?php

declare(strict_types=1);

namespace Preflight\Laravel;

use Illuminate\Support\ServiceProvider;
use Preflight\Laravel\Commands\CheckCommand;
use Preflight\Laravel\Commands\StatsCommand;

final class PreflightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/preflight.php', 'preflight');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/preflight.php' => config_path('preflight.php')], 'preflight-config');

        $this->commands([CheckCommand::class, StatsCommand::class]);
    }
}
