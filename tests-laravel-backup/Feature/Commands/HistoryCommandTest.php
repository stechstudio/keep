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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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
                '--no-interaction' => true,
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

    describe('filtering functionality', function () {
        beforeEach(function () {
            // Clear and set up test data with different users and dates
            \STS\Keep\Facades\Keep::vault('test')->clear();
            
            $vault = \STS\Keep\Facades\Keep::vault('test')->forStage('testing');
            
            // Create history entries with different users and dates (no sleep needed)
            $vault->set('FILTERED_SECRET', 'value1'); // This will have 'test-user' from TestVault
            $vault->set('FILTERED_SECRET', 'value2');
            $vault->set('FILTERED_SECRET', 'value3');
        });

        it('filters history by user (partial match)', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'FILTERED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--user' => 'test', // Should match 'test-user'
                '--format' => 'json',
                '--unmask' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json)->not->toBeEmpty();

            // All entries should have users containing 'test'
            foreach ($json as $entry) {
                expect($entry['lastModifiedUser'])->toContain('test');
            }
        });

        it('filters history by user with no matches', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'FILTERED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--user' => 'nonexistent',
                '--format' => 'json',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json)->toBeEmpty(); // No entries should match
        });

        it('filters history by since date', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'FILTERED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--since' => '1 second ago',
                '--format' => 'json',
                '--unmask' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json)->not->toBeEmpty(); // Should have recent entries
        });

        it('filters history by before date', function () {
            $futureDate = now()->addDay()->format('Y-m-d');

            $result = Artisan::call('keep:history', [
                'key' => 'FILTERED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--before' => $futureDate,
                '--format' => 'json',
                '--unmask' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect($json)->not->toBeEmpty(); // Should have all entries (before future date)
        });

        it('combines user and date filters', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'FILTERED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--user' => 'test',
                '--since' => '1 minute ago',
                '--format' => 'json',
                '--unmask' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();

            // Should have entries that match both user and date criteria
            foreach ($json as $entry) {
                expect($entry['lastModifiedUser'])->toContain('test');
            }
        });

        it('applies limit after filtering', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'FILTERED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--user' => 'test',
                '--limit' => 2,
                '--format' => 'json',
                '--unmask' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect(count($json))->toBe(2); // Should respect limit after filtering
        });

        it('fails with invalid date formats', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'FILTERED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--since' => 'invalid-date',
                '--format' => 'table',
                '--no-interaction' => true,
            ]);

            // Should fail with invalid date
            expect($result)->toBe(1);

            $output = Artisan::output();

            // Should contain error message about invalid date
            expect($output)->toContain('Invalid date format');
        });
    });

    describe('pagination functionality', function () {
        beforeEach(function () {
            // Clear and create many history entries
            \STS\Keep\Facades\Keep::vault('test')->clear();
            
            $vault = \STS\Keep\Facades\Keep::vault('test')->forStage('testing');
            
            // Create 15 versions to test pagination
            for ($i = 1; $i <= 15; $i++) {
                $vault->set('PAGINATED_SECRET', "value-{$i}");
            }
        });

        it('supports unlimited entries with high limit', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'PAGINATED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--limit' => 100, // Higher than available entries
                '--format' => 'json',
                '--unmask' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect(count($json))->toBe(15); // Should return all 15 entries
            
            // Should be sorted by version descending
            expect($json[0]['version'])->toBe(15);
            expect($json[14]['version'])->toBe(1);
        });

        it('correctly limits large result sets', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'PAGINATED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--limit' => 5,
                '--format' => 'json',
                '--unmask' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $json = json_decode($output, true);

            expect($json)->toBeArray();
            expect(count($json))->toBe(5); // Should limit to 5 entries
            
            // Should be the 5 most recent
            expect($json[0]['version'])->toBe(15);
            expect($json[4]['version'])->toBe(11);
        });

        it('handles zero limit', function () {
            $result = Artisan::call('keep:history', [
                'key' => 'PAGINATED_SECRET',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--limit' => 0,
                '--format' => 'json',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            
            // With zero limit, it should output an empty JSON array
            $json = json_decode(trim($output), true);
            
            if ($json !== null) {
                expect($json)->toBeArray();
                expect($json)->toBeEmpty(); // Should return no entries
            } else {
                // If not JSON, output should at least indicate success
                expect($output)->not->toBeEmpty();
            }
        });
    });
});
