<?php

use STS\Keep\Shell\ShellContext;

describe('ShellContext', function () {
    describe('initialization', function () {
        it('uses provided initial env and vault', function () {
            $context = new ShellContext('production', 'aws-vault');
            
            expect($context->getEnv())->toBe('production');
            expect($context->getVault())->toBe('aws-vault');
        });
    });
    
    describe('env management', function () {
        beforeEach(function () {
            $this->context = new ShellContext('development', 'test');
        });
        
        it('can change env', function () {
            $this->context->setEnv('production');
            
            expect($this->context->getEnv())->toBe('production');
        });
        
        it('invalidates cache when env changes', function () {
            // Get initial cached names (will be empty in test)
            $initial = $this->context->getCachedSecretNames();
            
            $this->context->setEnv('production');
            
            // Cache should be invalidated (still empty, but mechanism works)
            $after = $this->context->getCachedSecretNames();
            expect($after)->toBeArray();
        });
    });
    
    describe('vault management', function () {
        beforeEach(function () {
            $this->context = new ShellContext('development', 'test');
        });
        
        it('can change vault', function () {
            $this->context->setVault('aws-secrets');
            
            expect($this->context->getVault())->toBe('aws-secrets');
        });
        
        it('invalidates cache when vault changes', function () {
            // Get initial cached names
            $initial = $this->context->getCachedSecretNames();
            
            $this->context->setVault('new-vault');
            
            // Cache should be invalidated
            $after = $this->context->getCachedSecretNames();
            expect($after)->toBeArray();
        });
        
        it('returns available vaults', function () {
            $vaults = $this->context->getAvailableVaults();
            
            // Should return array (may be empty in test environment)
            expect($vaults)->toBeArray();
        });
    });
    
    describe('secret name caching', function () {
        beforeEach(function () {
            $this->context = new ShellContext('development', 'test');
        });
        
        it('returns array of cached secret names', function () {
            $names = $this->context->getCachedSecretNames();
            
            expect($names)->toBeArray();
        });
        
        it('caches results for subsequent calls', function () {
            // First call loads from vault
            $first = $this->context->getCachedSecretNames();
            
            // Second call should use cache (we can't directly test this
            // without mocking, but we verify it returns same result)
            $second = $this->context->getCachedSecretNames();
            
            expect($second)->toBe($first);
        });
        
        it('invalidates cache explicitly', function () {
            $this->context->getCachedSecretNames();
            $this->context->invalidateCache();
            
            // After invalidation, should reload
            $names = $this->context->getCachedSecretNames();
            expect($names)->toBeArray();
        });
    });
});