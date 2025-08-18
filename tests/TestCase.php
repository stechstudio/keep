<?php

namespace STS\Keep\Tests;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase as Orchestra;
use STS\Keep\KeepServiceProvider;
use STS\Keep\Tests\Support\TestVault;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear all test vault storage before each test for proper isolation
        TestVault::clearAll();
        
        // Register test vault driver
        app(\STS\Keep\KeepManager::class)->extend('test', function ($name, $config) {
            return new \STS\Keep\Tests\Support\TestVault($name, $config);
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            KeepServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        
        config()->set('keep.default', 'test');
        config()->set('keep.namespace', 'test-app');
        config()->set('keep.environment', 'testing');
        config()->set('keep.environments', ['testing', 'staging', 'production']);
        config()->set('keep.vaults.test', [
            'driver' => 'test',
        ]);
    }

    protected function tearDown(): void
    {
        // Reset all command instances after each test for isolation
        foreach (Artisan::all() as $command) {
            if (method_exists($command, 'resetInput')) {
                $command->resetInput();
            }
            
            if (method_exists($command, 'resetVault')) {
                $command->resetVault();
            }
        }
        
        parent::tearDown();
    }

    /**
     * Manually reset command state within a test for multiple calls with different parameters
     */
    protected function resetCommandState(): void
    {
        foreach (Artisan::all() as $command) {
            if (method_exists($command, 'resetInput')) {
                $command->resetInput();
            }
            
            if (method_exists($command, 'resetVault')) {
                $command->resetVault();
            }
        }
    }
}