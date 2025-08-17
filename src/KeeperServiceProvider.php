<?php

namespace STS\Keeper;

use Illuminate\Support\ServiceProvider;
use STS\Keeper\Commands\ExportSecretsCommand;
use STS\Keeper\Commands\GetSecretCommand;
use STS\Keeper\Commands\ListSecretsCommand;
use STS\Keeper\Commands\MergeSecretsCommand;
use STS\Keeper\Commands\SetSecretCommand;

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
                SetSecretCommand::class,
                GetSecretCommand::class,
                ListSecretsCommand::class,
                ExportSecretsCommand::class,
                MergeSecretsCommand::class,
            ]);
        }
    }
}
