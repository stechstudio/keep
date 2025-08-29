<?php

namespace STS\Keep\Laravel;

use Illuminate\Support\ServiceProvider;
use STS\Keep\Contracts\KeepRepositoryInterface;
use STS\Keep\Data\KeepRepository;

class SecretsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/keep.php', 'keep'
        );

        $this->app->singleton(KeepRepositoryInterface::class, function ($app) {
            $cacheFilePath = $this->getCacheFilePath();
            $appKey = $this->getAppKey();

            return new KeepRepository($cacheFilePath, $appKey);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/keep.php' => config_path('keep.php'),
        ], 'keep-config');

        $integrationMode = $this->app['config']->get('keep.integration_mode', 'env');

        if ($integrationMode === 'helper') {
            $this->registerHelperFunction();
        } elseif ($integrationMode === 'env') {
            $this->registerDotenvIntegration();
        }
    }

    protected function registerHelperFunction(): void
    {
        if (!function_exists('keep')) {
            /**
             * Get a secret value from the Keep cache
             */
            function keep(string $key, mixed $default = null): mixed
            {
                return app(KeepRepositoryInterface::class)->get($key, $default);
            }
        }
    }

    protected function registerDotenvIntegration(): void
    {
        // TODO: Implement Dotenv integration
        // This would hook into Laravel's env() function to check Keep secrets first
        throw new \RuntimeException('Dotenv integration mode is not yet implemented. Use "helper" mode for now.');
    }

    protected function getCacheFilePath(): string
    {
        $currentEnvironment = $this->app->environment();

        // Get custom stage mapping from config, default to current environment
        $stage = $this->app['config']->get(
            "keep.stage_environment_mapping.$currentEnvironment",
            $currentEnvironment
        );

        return base_path(".keep/cache/{$stage}.keep.php");
    }

    protected function getAppKey(): string
    {
        $appKey = $this->app['config']->get('app.key');

        if (empty($appKey)) {
            throw new \RuntimeException(
                'APP_KEY is required for Keep secrets decryption. Please ensure APP_KEY is set in your Laravel configuration.'
            );
        }

        return $appKey;
    }
}