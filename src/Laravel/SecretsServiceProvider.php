<?php

namespace STS\Keep\Laravel;

use Illuminate\Support\ServiceProvider;
use STS\Keep\Contracts\KeepRepositoryInterface;
use STS\Keep\Data\KeepRepository;

class SecretsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config from package
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
        // Publish config file
        $this->publishes([
            __DIR__.'/config/keep.php' => config_path('keep.php'),
        ], 'keep-config');

        $integrationMode = config('keep.integration_mode', 'helper');

        if ($integrationMode === 'helper') {
            $this->registerHelperFunction();
        } elseif ($integrationMode === 'dotenv') {
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
        $currentEnvironment = app()->environment();
        $stageMapping = config('keep.stage_environment_mapping', []);
        
        // Find the stage that maps to current environment
        $stage = array_search($currentEnvironment, $stageMapping);
        
        if ($stage === false) {
            $availableStages = implode(', ', array_keys($stageMapping));
            $availableEnvironments = implode(', ', array_values($stageMapping));
            
            throw new \RuntimeException(
                "No Keep stage mapped to Laravel environment '{$currentEnvironment}'. " .
                "Available stage mappings: {$availableStages} → {$availableEnvironments}. " .
                "Please configure 'stage_environment_mapping' in config/keep.php."
            );
        }
        
        return storage_path("cache/{$stage}.keep.php");
    }

    protected function getAppKey(): string
    {
        $appKey = config('app.key');

        if (empty($appKey)) {
            throw new \RuntimeException(
                'APP_KEY is required for Keep secrets decryption. Please ensure APP_KEY is set in your Laravel configuration.'
            );
        }

        return $appKey;
    }
}