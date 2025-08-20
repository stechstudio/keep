<?php

use Illuminate\Support\Facades\Artisan;

describe('GetCommand', function () {

    beforeEach(function () {
        // Clear test vault before each test
        \STS\Keep\Facades\Keep::vault('test')->clear();

        // Set up test secrets - now adding cross-stage setup
        \STS\Keep\Facades\Keep::vault('test')->forStage('testing')->set('TEST_SECRET', 'test-value');
        \STS\Keep\Facades\Keep::vault('test')->forStage('testing')->set('UNICODE_SECRET', 'Hello 世界 🚀');
        \STS\Keep\Facades\Keep::vault('test')->forStage('production')->set('PROD_SECRET', 'prod-value');
    });

    describe('basic functionality', function () {
        it('retrieves an existing secret with default table format', function () {
            $result = Artisan::call('keep:get', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('TEST_SECRET');
            expect($output)->toContain('test-value');
            expect($output)->toContain('1'); // revision
        });

        it('retrieves secret with raw format', function () {
            $result = Artisan::call('keep:get', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'raw',
            ]);

            expect($result)->toBe(0);

            $output = trim(Artisan::output());
            expect($output)->toBe('test-value');
        });

        it('retrieves secret with JSON format', function () {
            $result = Artisan::call('keep:get', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'json',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json['key'])->toBe('TEST_SECRET');
            expect($json['value'])->toBe('test-value');
            expect($json['revision'])->toBe(1);
        });

        it('handles unicode values correctly', function () {
            $result = Artisan::call('keep:get', [
                'key' => 'UNICODE_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'raw',
            ]);

            expect($result)->toBe(0);

            $output = trim(Artisan::output());
            expect($output)->toBe('Hello 世界 🚀');
        });
    });

    describe('error handling', function () {
        it('handles non-existent secret', function () {
            $result = Artisan::call('keep:get', [
                'key' => 'NON_EXISTENT_KEY',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(1); // FAILURE

            $output = Artisan::output();
            expect($output)->toContain('Secret [NON_EXISTENT_KEY] not found');
        });
    });

    describe('stage handling', function () {
        it('retrieves secret from specified stage', function () {
            $result = Artisan::call('keep:get', [
                'key' => 'PROD_SECRET',
                '--vault' => 'test',
                '--stage' => 'production',
                '--format' => 'raw',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = trim(Artisan::output());
            expect($output)->toBe('prod-value');
        });

        // NOTE: Cannot test stage selection prompts in automated tests
        // because they hang waiting for user input. Interactive prompts
        // are not compatible with automated testing environments.
    });
});
