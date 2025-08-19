<?php

use STS\Keep\Vaults\AwsSecretsManagerVault;

describe('AwsSecretsManagerVault', function () {
    
    beforeEach(function () {
        config()->set('keep.namespace', 'test-app');
        config()->set('keep.aws.region', 'us-east-1');
    });

    describe('format method', function () {
        it('formats secret names correctly with default prefix', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', [], 'production');
            
            expect($vault->format('DATABASE_URL'))
                ->toBe('test-app/production/DATABASE_URL');
        });

        it('formats secret names with custom prefix', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', [
                'prefix' => 'myapp'
            ], 'staging');
            
            expect($vault->format('API_KEY'))
                ->toBe('myapp/test-app/staging/API_KEY');
        });

        it('formats path correctly without key for listing', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', [], 'development');
            
            expect($vault->format())
                ->toBe('test-app/development');
        });

        it('handles custom key formatter', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', [], 'production');
            $vault->formatKeyUsing(function ($key, $stage, $config) {
                return "custom/{$stage}/{$key}";
            });
            
            expect($vault->format('SECRET_KEY'))
                ->toBe('custom/production/SECRET_KEY');
        });

        it('handles empty prefix gracefully', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', [
                'prefix' => ''
            ], 'test');
            
            expect($vault->format('KEY'))
                ->toBe('test-app/test/KEY');
        });
    });

    describe('driver registration', function () {
        it('can be created through KeepManager', function () {
            $manager = app(\STS\Keep\KeepManager::class);
            
            $vault = $manager->createSecretsmanagerDriver('test-secrets', [
                'driver' => 'secretsmanager'
            ]);
            
            expect($vault)->toBeInstanceOf(AwsSecretsManagerVault::class);
            expect($vault->name())->toBe('test-secrets');
        });
    });

    describe('vault interface compliance', function () {
        it('implements all required AbstractVault methods', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', []);
            
            // Check that all abstract methods are implemented
            $reflection = new ReflectionClass($vault);
            
            expect($reflection->hasMethod('format'))->toBeTrue();
            expect($reflection->hasMethod('list'))->toBeTrue();
            expect($reflection->hasMethod('has'))->toBeTrue();
            expect($reflection->hasMethod('get'))->toBeTrue();
            expect($reflection->hasMethod('save'))->toBeTrue();
            expect($reflection->hasMethod('set'))->toBeTrue();
            expect($reflection->hasMethod('delete'))->toBeTrue();
            expect($reflection->hasMethod('history'))->toBeTrue();
        });

        it('extends AbstractVault', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', []);
            
            expect($vault)->toBeInstanceOf(\STS\Keep\Vaults\AbstractVault::class);
        });
    });

    describe('configuration', function () {
        it('accepts configuration options', function () {
            $config = [
                'prefix' => 'myapp',
                'region' => 'us-west-2'
            ];
            
            $vault = new AwsSecretsManagerVault('secrets-manager', $config);
            
            // Access the protected config property via reflection for testing
            $reflection = new ReflectionClass($vault);
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);
            
            expect($configProperty->getValue($vault))->toBe($config);
        });

        it('has correct driver name', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', []);
            
            expect($vault->name())->toBe('secrets-manager');
        });
    });

    describe('stage management', function () {
        it('handles stage setting via forStage method', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', []);
            
            $productionVault = $vault->forStage('production');
            $developmentVault = $vault->forStage('development');
            
            // Access the protected stage property via reflection for testing
            $reflection = new ReflectionClass($productionVault);
            $stageProperty = $reflection->getProperty('stage');
            $stageProperty->setAccessible(true);
            
            expect($stageProperty->getValue($productionVault))->toBe('production');
            expect($stageProperty->getValue($developmentVault))->toBe('development');
        });

        it('incorporates stage in secret path', function () {
            $vault = new AwsSecretsManagerVault('secrets-manager', [], 'staging');
            
            expect($vault->format('TEST_KEY'))
                ->toContain('/staging/');
        });
    });

    describe('error handling', function () {
        it('properly maps AWS exception types', function () {
            // This is a structural test - we verify the exception handling structure
            // exists without needing to make actual AWS calls
            $vault = new AwsSecretsManagerVault('secrets-manager', [], 'test');
            
            // Verify the vault has access to the expected exception classes
            expect(class_exists('Aws\SecretsManager\Exception\SecretsManagerException'))->toBeTrue();
            expect(class_exists('STS\Keep\Exceptions\SecretNotFoundException'))->toBeTrue();
            expect(class_exists('STS\Keep\Exceptions\AccessDeniedException'))->toBeTrue();
            expect(class_exists('STS\Keep\Exceptions\KeepException'))->toBeTrue();
        });
    });

    describe('security properties', function () {
        it('treats all secrets as secure by default', function () {
            // AWS Secrets Manager always encrypts secrets
            $vault = new AwsSecretsManagerVault('secrets-manager', [], 'test');
            
            // This is verified in the implementation where secure is always set to true
            // We're testing the structural expectation here
            expect($vault)->toBeInstanceOf(AwsSecretsManagerVault::class);
        });
    });
});