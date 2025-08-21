<?php

use Illuminate\Container\Container;
use Illuminate\Config\Repository as ConfigRepository;
use STS\Keep\Laravel\SecretsServiceProvider;

beforeEach(function () {
    $this->app = new Container();
    $this->app->singleton('config', fn() => new ConfigRepository());
    
    // Set required APP_KEY config
    $this->app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    
    $this->provider = new SecretsServiceProvider($this->app);
});

it('generates correct cache file path for mapped environments', function () {
    // Mock app()->environment() call
    $this->app->singleton('env', fn() => 'local');
    $this->app->bind('app', fn() => new class {
        public function environment() { return 'local'; }
    });
    
    $this->app['config']->set('keep.stage_environment_mapping', [
        'development' => 'local',
        'production' => 'production',
    ]);
    
    // Mock storage_path function
    if (!function_exists('storage_path')) {
        function storage_path($path = '') {
            return '/tmp/storage/' . $path;
        }
    }
    
    $this->provider->register();
    
    // Use reflection to test protected method
    $reflection = new ReflectionClass($this->provider);
    $method = $reflection->getMethod('getCacheFilePath');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->provider);
    
    expect($result)->toBe('/tmp/storage/cache/development.keep.php');
});

it('throws exception when current environment has no stage mapping', function () {
    // Mock app()->environment() to return unmapped environment
    $this->app->bind('app', fn() => new class {
        public function environment() { return 'testing'; }
    });
    
    $this->app['config']->set('keep.stage_environment_mapping', [
        'development' => 'local',
        'production' => 'production',
    ]);
    
    $this->provider->register();
    
    // Use reflection to test protected method
    $reflection = new ReflectionClass($this->provider);
    $method = $reflection->getMethod('getCacheFilePath');
    $method->setAccessible(true);
    
    expect(fn() => $method->invoke($this->provider))
        ->toThrow(RuntimeException::class, "No Keep stage mapped to Laravel environment 'testing'");
});

it('uses correct stage mapping for different environments', function () {
    $testCases = [
        ['environment' => 'local', 'expected_stage' => 'development'],
        ['environment' => 'staging', 'expected_stage' => 'staging'],
        ['environment' => 'production', 'expected_stage' => 'production'],
    ];
    
    foreach ($testCases as $case) {
        // Mock app()->environment()
        $this->app->bind('app', fn() => new class($case['environment']) {
            public function __construct(private string $env) {}
            public function environment() { return $this->env; }
        });
        
        $this->app['config']->set('keep.stage_environment_mapping', [
            'development' => 'local',
            'staging' => 'staging', 
            'production' => 'production',
        ]);
        
        $provider = new SecretsServiceProvider($this->app);
        $provider->register();
        
        // Use reflection to test protected method
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCacheFilePath');
        $method->setAccessible(true);
        
        $result = $method->invoke($provider);
        
        expect($result)->toBe("/tmp/storage/cache/{$case['expected_stage']}.keep.php");
    }
});