<?php

namespace STS\Keep;

use Illuminate\Support\ServiceProvider;
use STS\Keep\Commands\ExportCommand;
use STS\Keep\Commands\GetCommand;
use STS\Keep\Commands\ImportCommand;
use STS\Keep\Commands\ListCommand;
use STS\Keep\Commands\MergeCommand;
use STS\Keep\Commands\SetCommand;

class KeepServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeepManager::class, function ($app) {
            return new KeepManager;
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/keep.php', 'keep'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/keep.php' => config_path('keep.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SetCommand::class,
                GetCommand::class,
                ListCommand::class,
                MergeCommand::class,
                ExportCommand::class,
                ImportCommand::class,
            ]);
        }
    }
}
