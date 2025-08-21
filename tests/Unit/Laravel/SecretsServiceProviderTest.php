<?php

use Illuminate\Container\Container;
use STS\Keep\Contracts\KeepRepositoryInterface;
use STS\Keep\Laravel\SecretsServiceProvider;

beforeEach(function () {
    $this->app = new Container();
    
    // Mock config
    $this->config = new class {
        protected array $data = [];
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
        public function set($key, $value) { $this->data[$key] = $value; }
    };
    
    $this->app->singleton('config', fn() => $this->config);
    
    // Set required APP_KEY config
    $this->config->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    
    $this->provider = new SecretsServiceProvider($this->app);
});

it('registers KeepRepositoryInterface as singleton', function () {
    $this->provider->register();
    
    expect($this->app->bound(KeepRepositoryInterface::class))->toBeTrue();
    
    $instance1 = $this->app->make(KeepRepositoryInterface::class);
    $instance2 = $this->app->make(KeepRepositoryInterface::class);
    
    expect($instance1)->toBe($instance2); // Same instance (singleton)
});

it('merges default config during registration', function () {
    $this->provider->register();
    
    expect($this->config->get('keep.integration_mode'))->toBe('helper');
});

it('throws exception when APP_KEY is missing', function () {
    $this->config->set('app.key', '');
    
    expect(fn() => $this->provider->register())
        ->toThrow(RuntimeException::class, 'APP_KEY is required for Keep secrets decryption');
});

it('registers keep helper function in helper mode', function () {
    // Mock storage_path function for testing
    if (!function_exists('storage_path')) {
        function storage_path($path = '') {
            return '/tmp/storage/' . $path;
        }
    }
    
    $this->config->set('keep.integration_mode', 'helper');
    
    $this->provider->register();
    $this->provider->boot();
    
    expect(function_exists('keep'))->toBeTrue();
});

it('throws exception for unimplemented dotenv mode', function () {
    $this->config->set('keep.integration_mode', 'dotenv');
    
    $this->provider->register();
    
    expect(fn() => $this->provider->boot())
        ->toThrow(RuntimeException::class, 'Dotenv integration mode is not yet implemented');
});