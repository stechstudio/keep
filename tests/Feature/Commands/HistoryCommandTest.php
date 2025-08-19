<?php

use Illuminate\Support\Facades\Artisan;

describe('HistoryCommand', function () {

    beforeEach(function () {
        // Clear test vault before each test
        \STS\Keep\Facades\Keep::vault('test')->clear();

        // Set up test secret with multiple versions for history testing
        $vault = \STS\Keep\Facades\Keep::vault('test')->forStage('testing');

        // Create initial version
        $vault->set('TEST_SECRET', 'initial-value');

        // Create multiple versions to have history
        $vault->set('TEST_SECRET', 'second-value');
        $vault->set('TEST_SECRET', 'third-value');
        $vault->set('TEST_SECRET', 'current-value');
    });

    describe('basic functionality', function () {
        it('displays history for an existing secret with default table format', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('History for secret: TEST_SECRET');
            expect($output)->toContain('Version');
            expect($output)->toContain('Value');
            expect($output)->toContain('Modified Date');
            expect($output)->toContain('Modified By');
        });

        it('displays history with values masked by default', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            // Values should be masked
            expect($output)->toContain('curr*********'); // masked current-value (13 chars)
            expect($output)->toContain('thir*******'); // masked third-value (11 chars)
            expect($output)->toContain('seco********'); // masked second-value (12 chars)
            expect($output)->toContain('init*********'); // masked initial-value (13 chars)
        });

        it('displays full values with --unmask option', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            // Values should be unmasked
            expect($output)->toContain('current-value');
            expect($output)->toContain('third-value');
            expect($output)->toContain('second-value');
            expect($output)->toContain('initial-value');
        });

        it('displays history in JSON format', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'json',
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json)->not->toBeEmpty();
            expect(count($json))->toBe(4); // Four versions

            // Check structure of first history entry
            $firstEntry = $json[0];
            expect($firstEntry)->toHaveKey('key');
            expect($firstEntry)->toHaveKey('value');
            expect($firstEntry)->toHaveKey('version');
            expect($firstEntry)->toHaveKey('lastModifiedDate');

            // Should be sorted by version descending (newest first)
            expect($firstEntry['version'])->toBe(4);
            expect($firstEntry['value'])->toBe('current-value');
        });

        it('limits history entries with --limit option', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'json',
                '--limit' => 2,
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect(count($json))->toBe(2); // Only 2 entries due to limit

            // Should be the 2 most recent versions
            expect($json[0]['version'])->toBe(4);
            expect($json[1]['version'])->toBe(3);
        });
    });

    describe('error handling', function () {
        it('handles non-existent secret', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'NONEXISTENT_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            expect($result)->toBe(1); // FAILURE

            $output = Artisan::output();
            expect($output)->toContain('not found');
        });

        it('handles invalid format option', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'invalid',
            ]);

            expect($result)->toBe(1); // FAILURE

            $output = Artisan::output();
            expect($output)->toContain('Invalid format option');
            expect($output)->toContain('table, json');
        });
    });

    describe('environment and vault handling', function () {
        it('uses specified stage', function () {
            // Set up secret in staging environment
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forStage('staging');
            $stagingVault->set('STAGING_SECRET', 'staging-value');
            $stagingVault->set('STAGING_SECRET', 'staging-value-v2');

            $result = Artisan::call('keep:history', [
                'key' => 'STAGING_SECRET',
                '--vault' => 'test',
                '--stage' => 'staging',
                '--format' => 'json',
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect(count($json))->toBe(2); // Two versions in staging
            expect($json[0]['value'])->toBe('staging-value-v2');
        });

        it('uses specified vault', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'json',
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json[0]['key'])->toBe('TEST_SECRET');
        });
    });

    describe('masking functionality', function () {
        it('masks short values with ****', function () {
            // Set up a secret with a short value
            $vault = \STS\Keep\Facades\Keep::vault('test')->forStage('testing');
            $vault->set('SHORT_SECRET', 'abc');

            $result = Artisan::call('keep:history', [
                'key' => 'SHORT_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('****'); // Short values are masked as ****
        });

        it('masks long values with first 4 chars + asterisks', function () {
            // Set up a secret with a long value
            $vault = \STS\Keep\Facades\Keep::vault('test')->forStage('testing');
            $vault->set('LONG_SECRET', 'very-long-secret-value');

            $result = Artisan::call('keep:history', [
                'key' => 'LONG_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('very******************'); // First 4 chars + asterisks
        });
    });

    describe('edge cases', function () {
        it('handles secret with empty value in history', function () {
            // Set up a secret with an empty value
            $vault = \STS\Keep\Facades\Keep::vault('test')->forStage('testing');
            $vault->set('EMPTY_SECRET', '');

            $result = Artisan::call('keep:history', [
                'key' => 'EMPTY_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('EMPTY_SECRET');
        });

        it('handles large limit value', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--limit' => 100,
                '--format' => 'json',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect(count($json))->toBe(4); // Still only 4 entries available
        });

        it('handles limit of 1', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'TEST_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--limit' => 1,
                '--format' => 'json',
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect(count($json))->toBe(1); // Only 1 entry
            expect($json[0]['version'])->toBe(4); // Should be the most recent
            expect($json[0]['value'])->toBe('current-value');
        });
    });
});
