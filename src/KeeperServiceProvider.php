<?php

namespace STS\Keeper;

use Illuminate\Support\ServiceProvider;
use STS\Keeper\Commands\ExportCommand;
use STS\Keeper\Commands\GetCommand;
use STS\Keeper\Commands\ImportCommand;
use STS\Keeper\Commands\ListCommand;
use STS\Keeper\Commands\MergeCommand;
use STS\Keeper\Commands\SetCommand;

class KeeperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeeperManager::class, function ($app) {
            return new KeeperManager;
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/keeper.php', 'keeper'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/keeper.php' => config_path('keeper.php'),
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
