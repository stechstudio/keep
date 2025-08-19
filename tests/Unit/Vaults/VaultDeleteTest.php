<?php

use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Tests\Support\TestVault;

describe('Vault Delete Functionality', function () {

    beforeEach(function () {
        // Clear all test vault storage
        TestVault::clearAll();
    });

    describe('TestVault delete method', function () {
        it('deletes an existing secret successfully', function () {
            $vault = new TestVault('test-vault', ['namespace' => 'test-app'], 'testing');

            // Set a secret first
            $vault->set('TEST_KEY', 'test-value');
            expect($vault->hasSecret('TEST_KEY'))->toBeTrue();

            // Delete the secret
            $result = $vault->delete('TEST_KEY');
            expect($result)->toBeTrue();

            // Verify it's gone
            expect($vault->hasSecret('TEST_KEY'))->toBeFalse();
        });

        it('throws SecretNotFoundException for non-existent secret', function () {
            $vault = new TestVault('test-vault', ['namespace' => 'test-app'], 'testing');

            expect(fn () => $vault->delete('NON_EXISTENT_KEY'))
                ->toThrow(SecretNotFoundException::class, 'Secret [NON_EXISTENT_KEY] not found in vault [test-vault]');
        });

        it('only deletes from the specific environment', function () {
            $testingVault = new TestVault('test-vault', ['namespace' => 'test-app'], 'testing');
            $productionVault = new TestVault('test-vault', ['namespace' => 'test-app'], 'production');

            // Set the same key in both environments
            $testingVault->set('SHARED_KEY', 'testing-value');
            $productionVault->set('SHARED_KEY', 'production-value');

            // Delete from testing environment only
            $testingVault->delete('SHARED_KEY');

            // Verify testing environment secret is gone
            expect($testingVault->hasSecret('SHARED_KEY'))->toBeFalse();

            // Verify production environment secret still exists
            expect($productionVault->hasSecret('SHARED_KEY'))->toBeTrue();
            expect($productionVault->get('SHARED_KEY')->value())->toBe('production-value');
        });

        it('handles unicode and special character keys correctly', function () {
            $vault = new TestVault('test-vault', ['namespace' => 'test-app'], 'testing');

            // Set secrets with special characters
            $vault->set('UNICODE_KEY_世界', 'unicode-value');
            $vault->set('SPECIAL_KEY_!@#', 'special-value');

            // Delete both secrets
            expect($vault->delete('UNICODE_KEY_世界'))->toBeTrue();
            expect($vault->delete('SPECIAL_KEY_!@#'))->toBeTrue();

            // Verify they're gone
            expect($vault->hasSecret('UNICODE_KEY_世界'))->toBeFalse();
            expect($vault->hasSecret('SPECIAL_KEY_!@#'))->toBeFalse();
        });

        it('works with different vault instances', function () {
            $vault1 = new TestVault('vault-1', ['namespace' => 'test-app'], 'testing');
            $vault2 = new TestVault('vault-2', ['namespace' => 'test-app'], 'testing');

            // Set same key in both vaults
            $vault1->set('SHARED_KEY', 'vault1-value');
            $vault2->set('SHARED_KEY', 'vault2-value');

            // Delete from vault1 only
            $vault1->delete('SHARED_KEY');

            // Verify vault1 secret is gone, vault2 secret remains
            expect($vault1->hasSecret('SHARED_KEY'))->toBeFalse();
            expect($vault2->hasSecret('SHARED_KEY'))->toBeTrue();
            expect($vault2->get('SHARED_KEY')->value())->toBe('vault2-value');
        });
    });

    describe('delete operation edge cases', function () {
        it('handles deletion of secrets with different security levels', function () {
            $vault = new TestVault('test-vault', ['namespace' => 'test-app'], 'testing');

            // Set both secure and plain secrets
            $vault->set('SECURE_KEY', 'secure-value', true);
            $vault->set('PLAIN_KEY', 'plain-value', false);

            // Delete both
            expect($vault->delete('SECURE_KEY'))->toBeTrue();
            expect($vault->delete('PLAIN_KEY'))->toBeTrue();

            // Verify both are gone
            expect($vault->hasSecret('SECURE_KEY'))->toBeFalse();
            expect($vault->hasSecret('PLAIN_KEY'))->toBeFalse();
        });

        it('handles deletion after multiple updates', function () {
            $vault = new TestVault('test-vault', ['namespace' => 'test-app'], 'testing');

            // Set and update a secret multiple times
            $vault->set('UPDATED_KEY', 'value1');
            $vault->set('UPDATED_KEY', 'value2');
            $vault->set('UPDATED_KEY', 'value3');

            $secret = $vault->get('UPDATED_KEY');
            expect($secret->revision())->toBe(3);

            // Delete the secret
            expect($vault->delete('UPDATED_KEY'))->toBeTrue();
            expect($vault->hasSecret('UPDATED_KEY'))->toBeFalse();
        });

        it('maintains isolation between environments after deletion', function () {
            $vault = new TestVault('test-vault', ['namespace' => 'test-app']);

            // Create vault instances for different environments
            $testingVault = $vault->forEnvironment('testing');
            $stagingVault = $vault->forEnvironment('staging');
            $productionVault = $vault->forEnvironment('production');

            // Set the same key in all environments
            $testingVault->set('ENV_KEY', 'testing-value');
            $stagingVault->set('ENV_KEY', 'staging-value');
            $productionVault->set('ENV_KEY', 'production-value');

            // Delete from staging only
            $stagingVault->delete('ENV_KEY');

            // Verify proper isolation
            expect($testingVault->hasSecret('ENV_KEY'))->toBeTrue();
            expect($stagingVault->hasSecret('ENV_KEY'))->toBeFalse();
            expect($productionVault->hasSecret('ENV_KEY'))->toBeTrue();

            expect($testingVault->get('ENV_KEY')->value())->toBe('testing-value');
            expect($productionVault->get('ENV_KEY')->value())->toBe('production-value');
        });
    });
});
