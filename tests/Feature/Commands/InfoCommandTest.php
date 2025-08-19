<?php

use Illuminate\Support\Facades\Artisan;

describe('InfoCommand', function () {

    beforeEach(function () {
        // Clear test vault before each test
        \STS\Keep\Facades\Keep::vault('test')->clear();
    });

    describe('basic functionality', function () {
        it('displays configuration info with default table format', function () {
            $result = Artisan::call('keep:info', [
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Keep Configuration');
            expect($output)->toContain('test-app'); // namespace
            expect($output)->toContain('testing'); // current environment
            expect($output)->toContain('test'); // default vault
        });

        it('displays configuration info with JSON format', function () {
            $result = Artisan::call('keep:info', [
                '--format' => 'json',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json['namespace'])->toBe('test-app');
            expect($json['environment'])->toBe('testing');
            expect($json['default_vault'])->toBe('test');
            expect($json['available_vaults'])->toContain('test');
            expect($json['configured_environments'])->toContain('testing');
            expect($json['configured_environments'])->toContain('staging');
            expect($json['configured_environments'])->toContain('production');
        });

        it('displays vault configurations in table format', function () {
            $result = Artisan::call('keep:info', [
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Vault Configurations');
            expect($output)->toContain('test'); // vault name
            expect($output)->toContain('test'); // driver name
        });

        it('displays configured environments in table format', function () {
            $result = Artisan::call('keep:info', [
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Configured Environments');
            expect($output)->toContain('testing');
            expect($output)->toContain('staging');
            expect($output)->toContain('production');
        });
    });

    describe('error handling', function () {
        it('handles invalid format option', function () {
            $result = Artisan::call('keep:info', [
                '--format' => 'invalid',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(1); // FAILURE

            $output = Artisan::output();
            expect($output)->toContain('Invalid format option');
        });
    });

    describe('JSON output structure', function () {
        it('includes all expected configuration keys', function () {
            $result = Artisan::call('keep:info', [
                '--format' => 'json',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toHaveKeys([
                'namespace',
                'environment',
                'default_vault',
                'available_vaults',
                'configured_environments',
                'vault_configurations',
            ]);
        });

        it('includes vault configuration details', function () {
            $result = Artisan::call('keep:info', [
                '--format' => 'json',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json['vault_configurations'])->toHaveKey('test');
            expect($json['vault_configurations']['test'])->toHaveKeys([
                'driver',
                'region',
                'prefix',
            ]);
            expect($json['vault_configurations']['test']['driver'])->toBe('test');
        });
    });
});
