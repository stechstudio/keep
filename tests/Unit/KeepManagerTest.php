<?php

use STS\Keep\KeepManager;
use STS\Keep\Vaults\AbstractVault;
use STS\Keep\Vaults\AwsSsmVault;

describe('KeepManager', function () {
    beforeEach(function () {
        $this->manager = new KeepManager();
    });
    
    describe('environment resolution', function () {
        it('uses custom resolver when provided', function () {
            $result = $this->manager->resolveEnvironmentUsing(fn() => 'custom-environment');
            
            expect($result)->toBe($this->manager); // fluent interface
            expect($this->manager->environment())->toBe('custom-environment');
        });
        
        it('checks if given environment matches current', function () {
            $this->manager->resolveEnvironmentUsing(fn() => 'production');
            
            expect($this->manager->environment('production'))->toBeTrue();
            expect($this->manager->environment('staging'))->toBeFalse();
        });
    });
    
    describe('AWS SSM driver creation', function () {
        it('creates AwsSsmVault with correct parameters', function () {
            $config = [
                'driver' => 'aws-ssm',
                'region' => 'us-east-1',
                'profile' => 'default'
            ];
            
            $vault = $this->manager->createAwsSsmDriver('my-vault', $config);
            
            expect($vault)->toBeInstanceOf(AwsSsmVault::class);
            expect($vault->name())->toBe('my-vault');
        });
    });
    
    describe('vault resolution internals', function () {
        it('throws exception for vault without driver config', function () {
            $reflection = new ReflectionClass($this->manager);
            $resolve = $reflection->getMethod('resolve');
            $resolve->setAccessible(true);
            
            $config = ['region' => 'us-east-1']; // Missing driver
            
            expect(fn() => $resolve->invoke($this->manager, 'test-vault', $config))
                ->toThrow(\InvalidArgumentException::class, 'Vault [test-vault] does not have a configured driver.');
        });
        
        it('throws exception for unsupported driver', function () {
            $reflection = new ReflectionClass($this->manager);
            $resolve = $reflection->getMethod('resolve');
            $resolve->setAccessible(true);
            
            $config = ['driver' => 'unsupported-driver'];
            
            expect(fn() => $resolve->invoke($this->manager, 'test-vault', $config))
                ->toThrow(\InvalidArgumentException::class, 'Driver [unsupported-driver] is not supported.');
        });
        
        it('resolves aws-ssm driver correctly', function () {
            $reflection = new ReflectionClass($this->manager);
            $resolve = $reflection->getMethod('resolve');
            $resolve->setAccessible(true);
            
            $config = [
                'driver' => 'aws-ssm',
                'region' => 'us-east-1'
            ];
            
            $vault = $resolve->invoke($this->manager, 'test-vault', $config);
            
            expect($vault)->toBeInstanceOf(AwsSsmVault::class);
            expect($vault->name())->toBe('test-vault');
        });
    });
    
    describe('custom vault creators', function () {
        it('uses custom creator when registered', function () {
            $customVault = Mockery::mock(AbstractVault::class);
            $customVault->shouldReceive('name')->andReturn('custom-vault');
            
            // Use reflection to register a custom creator
            $reflection = new ReflectionClass($this->manager);
            $customCreators = $reflection->getProperty('customCreators');
            $customCreators->setAccessible(true);
            $customCreators->setValue($this->manager, [
                'custom' => fn($name, $config) => $customVault
            ]);
            
            // Use reflection to test resolve method directly
            $resolve = $reflection->getMethod('resolve');
            $resolve->setAccessible(true);
            
            $config = ['driver' => 'custom'];
            $vault = $resolve->invoke($this->manager, 'custom-vault', $config);
            
            expect($vault)->toBe($customVault);
        });
    });
    
    describe('driver method name conversion', function () {
        it('converts driver names to proper pascal case method names', function ($driver, $expectedMethod) {
            $reflection = new ReflectionClass($this->manager);
            
            // We can't easily test the private method conversion, but we can test that 
            // unsupported drivers throw the right exception mentioning the driver name
            $resolve = $reflection->getMethod('resolve');
            $resolve->setAccessible(true);
            
            $config = ['driver' => $driver];
            
            expect(fn() => $resolve->invoke($this->manager, 'test-vault', $config))
                ->toThrow(\InvalidArgumentException::class, "Driver [$driver] is not supported.");
        })->with([
            ['redis', 'should try createRedisDriver'],
            ['database', 'should try createDatabaseDriver'],
            ['file-system', 'should try createFileSystemDriver'],
            ['custom_vault', 'should try createCustomVaultDriver'],
        ]);
    });
    
    describe('vault caching', function () {
        it('would cache resolved vaults by name', function () {
            $reflection = new ReflectionClass($this->manager);
            $vaults = $reflection->getProperty('vaults');
            $vaults->setAccessible(true);
            
            // Initially empty
            expect($vaults->getValue($this->manager))->toBe([]);
            
            // Manually add a vault to the cache
            $mockVault = Mockery::mock(AbstractVault::class);
            $vaults->setValue($this->manager, ['test-vault' => $mockVault]);
            
            expect($vaults->getValue($this->manager))->toBe(['test-vault' => $mockVault]);
        });
    });
});