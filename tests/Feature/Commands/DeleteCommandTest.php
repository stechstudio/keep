<?php

use Illuminate\Support\Facades\Artisan;

describe('DeleteCommand', function () {

    beforeEach(function () {
        // Clear test vault before each test
        \STS\Keep\Facades\Keep::vault('test')->clear();

        // Set up test secrets
        \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing')->set('TEST_SECRET', 'test-value');
        \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing')->set('UNICODE_SECRET', 'Hello 世界 🚀');
        \STS\Keep\Facades\Keep::vault('test')->forEnvironment('production')->set('PROD_SECRET', 'prod-value');
    });

    describe('basic functionality', function () {
        it('deletes an existing secret with --force flag', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Secret [TEST_SECRET] has been permanently deleted');
            expect($output)->toContain('vault [test]');
            expect($output)->toContain('environment [testing]');
        });

        it('shows secret details before deletion', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Secret to be deleted:');
            expect($output)->toContain('TEST_SECRET');
            expect($output)->toContain('testing');
            expect($output)->toContain('test');
        });

        it('actually removes the secret from the vault', function () {
            // Verify secret exists before deletion
            $vault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            expect($vault->hasSecret('TEST_SECRET'))->toBeTrue();

            // Delete the secret
            Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            // Verify secret no longer exists
            expect($vault->hasSecret('TEST_SECRET'))->toBeFalse();
        });

        it('handles unicode secrets correctly', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'UNICODE_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Secret [UNICODE_SECRET] has been permanently deleted');
        });
    });

    describe('error handling', function () {
        it('handles non-existent secret', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'NON_EXISTENT_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(1); // FAILURE

            $output = Artisan::output();
            expect($output)->toContain('Secret [NON_EXISTENT_KEY] not found');
        });
    });

    describe('environment handling', function () {
        it('deletes secret from specified environment only', function () {
            // Set the same key in different environments
            \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging')->set('SHARED_SECRET', 'staging-value');
            \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing')->set('SHARED_SECRET', 'testing-value');

            // Delete from testing environment only
            Artisan::call('keep:delete', [
                'key' => 'SHARED_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            // Verify testing environment secret is gone
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            expect($testingVault->hasSecret('SHARED_SECRET'))->toBeFalse();

            // Verify staging environment secret still exists
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');
            expect($stagingVault->hasSecret('SHARED_SECRET'))->toBeTrue();
        });

        it('deletes secret from production environment', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'PROD_SECRET',
                '--vault' => 'test',
                '--env' => 'production',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('environment [production]');
        });
    });

    describe('vault handling', function () {
        it('uses specified vault', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('vault [test]');
        });

        it('uses default vault when not specified', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('vault [test]'); // default vault in test config
        });
    });

    describe('force flag behavior', function () {
        it('skips confirmation when --force is used', function () {
            $result = Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->not->toContain('Are you sure');
            expect($output)->not->toContain('cancelled');
            expect($output)->toContain('permanently deleted');
        });

        // NOTE: Cannot test interactive confirmation prompts in automated tests
        // because they require user input which hangs in automated environments.
        // The confirmation behavior would need to be tested manually or with
        // specialized testing tools that can simulate user input.
    });

    describe('integration scenarios', function () {
        it('delete then get operation fails', function () {
            // Delete the secret
            Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            // Try to get the deleted secret
            $result = Artisan::call('keep:get', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(1); // Should fail
        });

        it('delete then set operation creates new secret', function () {
            // Delete the secret
            Artisan::call('keep:delete', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            // Set a new secret with the same key
            $result = Artisan::call('keep:set', [
                'key' => 'TEST_SECRET',
                'value' => 'new-value',
                '--vault' => 'test',
                '--env' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify the new secret exists
            $vault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $secret = $vault->get('TEST_SECRET');
            expect($secret->value())->toBe('new-value');
            expect($secret->revision())->toBe(1); // Should be revision 1 as it's a new secret
        });
    });
});