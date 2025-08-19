<?php

use Illuminate\Support\Facades\Artisan;

describe('DiffCommand', function () {

    beforeEach(function () {
        // Clear test vault before each test
        \STS\Keep\Facades\Keep::vault('test')->clear();
    });

    describe('basic functionality', function () {
        it('compares secrets across all configured environments by default', function () {
            // Set up test data across environments
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');
            $productionVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('production');

            // Identical secret across all environments
            $testingVault->set('IDENTICAL_SECRET', 'same-value');
            $stagingVault->set('IDENTICAL_SECRET', 'same-value');
            $productionVault->set('IDENTICAL_SECRET', 'same-value');

            // Different values across environments
            $testingVault->set('DIFFERENT_SECRET', 'testing-value');
            $stagingVault->set('DIFFERENT_SECRET', 'staging-value');
            $productionVault->set('DIFFERENT_SECRET', 'production-value');

            // Missing in some environments
            $testingVault->set('INCOMPLETE_SECRET', 'only-in-testing');
            $stagingVault->set('INCOMPLETE_SECRET', 'only-in-staging');
            // Missing in production

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Secret Comparison Matrix');
            expect($output)->toContain('testing');
            expect($output)->toContain('staging');
            expect($output)->toContain('production');
            expect($output)->toContain('IDENTICAL_SECRET');
            expect($output)->toContain('DIFFERENT_SECRET');
            expect($output)->toContain('INCOMPLETE_SECRET');
            expect($output)->toContain('Summary:');
        });

        it('displays values masked by default', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            $testingVault->set('TEST_SECRET', 'long-secret-value');
            $stagingVault->set('TEST_SECRET', 'different-secret-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('long*************'); // Masked testing value
            expect($output)->toContain('diff******************'); // Masked staging value
        });

        it('displays full values with --unmask option', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            $testingVault->set('TEST_SECRET', 'testing-value');
            $stagingVault->set('TEST_SECRET', 'staging-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('testing-value');
            expect($output)->toContain('staging-value');
        });
    });

    describe('status indicators', function () {
        it('shows identical status for secrets with same values', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            $testingVault->set('IDENTICAL_SECRET', 'same-value');
            $stagingVault->set('IDENTICAL_SECRET', 'same-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Identical');
        });

        it('shows different status for secrets with different values', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            $testingVault->set('DIFFERENT_SECRET', 'testing-value');
            $stagingVault->set('DIFFERENT_SECRET', 'staging-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Different');
        });

        it('shows incomplete status for missing secrets', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');

            $testingVault->set('INCOMPLETE_SECRET', 'only-in-testing');
            // Missing in staging

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Incomplete');
            expect($output)->toContain('—');
        });
    });

    describe('environment filtering', function () {
        it('compares only specified environments with --envs option', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');
            $productionVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('production');

            $testingVault->set('TEST_SECRET', 'testing-value');
            $stagingVault->set('TEST_SECRET', 'staging-value');
            $productionVault->set('TEST_SECRET', 'production-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('testing');
            expect($output)->toContain('staging');
            expect($output)->not->toContain('production');
            expect($output)->toContain('Environments compared: testing, staging');
        });

        it('handles single environment comparison', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $testingVault->set('TEST_SECRET', 'testing-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('testing');
            expect($output)->toContain('Environments compared: testing');
        });

        it('warns about invalid environments', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $testingVault->set('TEST_SECRET', 'testing-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,invalid-env',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Warning: Unknown environments specified: invalid-env');
        });
    });

    describe('vault filtering', function () {
        it('uses default vault when --vaults not specified', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $testingVault->set('TEST_SECRET', 'testing-value');

            $result = Artisan::call('keep:diff', [
                '--envs' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Vault: test'); // Default vault name from config
        });

        it('warns about invalid vaults', function () {
            $result = Artisan::call('keep:diff', [
                '--vaults' => 'test,invalid-vault',
                '--envs' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Warning: Unknown vaults specified: invalid-vault');
        });
    });

    describe('summary statistics', function () {
        it('provides accurate summary with percentages', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            // 1 identical
            $testingVault->set('IDENTICAL_SECRET', 'same-value');
            $stagingVault->set('IDENTICAL_SECRET', 'same-value');

            // 1 different
            $testingVault->set('DIFFERENT_SECRET', 'testing-value');
            $stagingVault->set('DIFFERENT_SECRET', 'staging-value');

            // 1 incomplete
            $testingVault->set('INCOMPLETE_SECRET', 'only-in-testing');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Total secrets: 3');
            expect($output)->toContain('Identical across all environments: 1 (33%)');
            expect($output)->toContain('Different values: 1 (33%)');
            expect($output)->toContain('Missing in some environments: 1 (33%)');
        });

        it('shows legend for understanding results', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $testingVault->set('TEST_SECRET', 'testing-value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Summary:');
            expect($output)->toContain('Total secrets:');
        });
    });

    describe('error handling', function () {
        it('handles empty vault gracefully', function () {
            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('No secrets found in any of the specified vault/environment combinations.');
        });

        it('handles invalid option gracefully', function () {
            // Test that invalid options are caught by the framework
            expect(function () {
                Artisan::call('keep:diff', [
                    '--vault' => 'test',
                    '--envs' => 'testing',
                    '--invalid-option' => 'test',
                ]);
            })->toThrow(\Symfony\Component\Console\Exception\InvalidOptionException::class);
        });

        it('handles no vaults available', function () {
            $result = Artisan::call('keep:diff', [
                '--vaults' => 'nonexistent-vault',
                '--envs' => 'testing',
            ]);

            expect($result)->toBe(1);

            $output = Artisan::output();
            expect($output)->toContain('No vaults available for comparison');
        });

        it('handles no environments available', function () {
            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'nonexistent-env',
            ]);

            expect($result)->toBe(1);

            $output = Artisan::output();
            expect($output)->toContain('No environments available for comparison');
        });
    });

    describe('edge cases', function () {
        it('handles secrets with empty values', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            $testingVault->set('EMPTY_SECRET', '');
            $stagingVault->set('EMPTY_SECRET', '');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
                '--unmask' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('EMPTY_SECRET');
            expect($output)->toContain('Identical');
        });

        it('handles secrets with special characters', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            $testingVault->set('SPECIAL_SECRET', 'value with spaces & symbols!');
            $stagingVault->set('SPECIAL_SECRET', 'value with spaces & symbols!');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('SPECIAL_SECRET');
            expect($output)->toContain('Identical');
        });

        it('handles large number of secrets efficiently', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $stagingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('staging');

            // Create 20 secrets
            for ($i = 1; $i <= 20; $i++) {
                $testingVault->set("SECRET_{$i}", "testing-value-{$i}");
                $stagingVault->set("SECRET_{$i}", "staging-value-{$i}");
            }

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Total secrets: 20');
            expect($output)->toContain('Different values: 20 (100%)');
        });

        it('sorts secrets alphabetically', function () {
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');

            // Add secrets in non-alphabetical order
            $testingVault->set('ZEBRA_SECRET', 'value');
            $testingVault->set('ALPHA_SECRET', 'value');
            $testingVault->set('BETA_SECRET', 'value');

            $result = Artisan::call('keep:diff', [
                '--vault' => 'test',
                '--envs' => 'testing',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            $lines = explode("\n", $output);
            
            // Find lines containing the secrets and verify order
            $secretLines = array_filter($lines, fn($line) => str_contains($line, '_SECRET'));
            $secretLines = array_values($secretLines);
            
            expect(count($secretLines))->toBeGreaterThanOrEqual(3);
            
            // Should appear in alphabetical order
            $alphaIndex = null;
            $betaIndex = null;
            $zebraIndex = null;
            
            foreach ($secretLines as $index => $line) {
                if (str_contains($line, 'ALPHA_SECRET')) $alphaIndex = $index;
                if (str_contains($line, 'BETA_SECRET')) $betaIndex = $index;
                if (str_contains($line, 'ZEBRA_SECRET')) $zebraIndex = $index;
            }
            
            expect($alphaIndex)->not->toBeNull();
            expect($betaIndex)->not->toBeNull();
            expect($zebraIndex)->not->toBeNull();
            expect($alphaIndex)->toBeLessThan($betaIndex);
            expect($betaIndex)->toBeLessThan($zebraIndex);
        });
    });

    describe('multiple vaults', function () {
        it('displays vault names in headers when multiple vaults specified', function () {
            // This test would require multiple vault configurations
            // For now, we'll test with a single vault but verify the column naming logic
            $testingVault = \STS\Keep\Facades\Keep::vault('test')->forEnvironment('testing');
            $testingVault->set('TEST_SECRET', 'value');

            $result = Artisan::call('keep:diff', [
                '--vaults' => 'test',
                '--envs' => 'testing,staging',
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            // With single vault, should show environment names only
            expect($output)->toContain('testing');
            expect($output)->toContain('staging');
        });
    });
});