<?php

namespace STS\Keep\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use STS\Keep\KeepServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
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
}