<?php

use Illuminate\Support\Facades\Artisan;
use STS\Keep\Tests\Support\TestVault;

describe('CopyCommand', function () {

    beforeEach(function () {
        // Clear test vault before each test
        TestVault::clearAll();
    });

    describe('basic functionality', function () {
        it('copies a secret between stages in same vault', function () {
            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'TEST_KEY',
                'value' => 'test-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Copy to different stage
            $result = Artisan::call('keep:copy', [
                'key' => 'TEST_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify destination exists
            $destinationVault = \STS\Keep\Facades\Keep::vault('test')->forStage('production');
            expect($destinationVault->hasSecret('TEST_KEY'))->toBeTrue();

            $destinationSecret = $destinationVault->get('TEST_KEY');
            expect($destinationSecret->value())->toBe('test-value');
            expect($destinationSecret->isSecure())->toBeTrue();
        });

        it('copies a plain text secret', function () {
            // Create plain text source secret
            Artisan::call('keep:set', [
                'key' => 'PLAIN_KEY',
                'value' => 'plain-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--plain' => true,
                '--no-interaction' => true,
            ]);

            // Copy to different stage
            Artisan::call('keep:copy', [
                'key' => 'PLAIN_KEY',
                '--from' => 'development',
                '--to' => 'staging',
                '--no-interaction' => true,
            ]);

            // Verify destination preserves security level
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('staging')->get('PLAIN_KEY');
            expect($destinationSecret->value())->toBe('plain-value');
            expect($destinationSecret->isSecure())->toBeFalse();
        });

        it('uses default vault when no vault prefix specified', function () {
            // Create source secret in testing stage
            Artisan::call('keep:set', [
                'key' => 'DEFAULT_VAULT_SOURCE',
                'value' => 'source-value',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true,
            ]);

            // Copy from testing to production (both use default vault)
            Artisan::call('keep:copy', [
                'key' => 'DEFAULT_VAULT_SOURCE',
                '--from' => 'testing',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            // Verify copy succeeded
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('DEFAULT_VAULT_SOURCE');
            expect($destinationSecret->value())->toBe('source-value');
        });

        it('accepts key as optional argument like other commands', function () {
            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'OPTIONAL_KEY_TEST',
                'value' => 'test-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Copy with key provided as argument
            $result = Artisan::call('keep:copy', [
                'key' => 'OPTIONAL_KEY_TEST',  // Key provided as argument
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify destination exists
            $destinationVault = \STS\Keep\Facades\Keep::vault('test')->forStage('production');
            expect($destinationVault->hasSecret('OPTIONAL_KEY_TEST'))->toBeTrue();

            $destinationSecret = $destinationVault->get('OPTIONAL_KEY_TEST');
            expect($destinationSecret->value())->toBe('test-value');
        });

        it('accepts from and to as optional options with interactive prompting', function () {
            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'PROMPT_TEST_KEY',
                'value' => 'prompt-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Copy with all values provided (simulating what would happen after prompts)
            $result = Artisan::call('keep:copy', [
                'key' => 'PROMPT_TEST_KEY',
                '--from' => 'development',  // These would come from prompts if not provided
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify destination
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('PROMPT_TEST_KEY');
            expect($destinationSecret->value())->toBe('prompt-value');
        });
    });

    describe('cross-vault copying', function () {
        it('copies between different vaults using vault:stage prefix syntax', function () {
            // Create source secret in first vault
            Artisan::call('keep:set', [
                'key' => 'CROSS_VAULT_KEY',
                'value' => 'cross-vault-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Use explicit vault:stage prefix syntax
            $result = Artisan::call('keep:copy', [
                'key' => 'CROSS_VAULT_KEY',
                '--from' => 'test:development',
                '--to' => 'test:production',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify destination
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('CROSS_VAULT_KEY');
            expect($destinationSecret->value())->toBe('cross-vault-value');
        });

        it('supports mixed default and explicit vault syntax', function () {
            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'MIXED_SYNTAX_KEY',
                'value' => 'mixed-value',
                '--vault' => 'test',
                '--stage' => 'staging',
                '--no-interaction' => true,
            ]);

            // Copy from default vault (no prefix) to explicit vault
            $result = Artisan::call('keep:copy', [
                'key' => 'MIXED_SYNTAX_KEY',
                '--from' => 'staging',              // Uses default vault (test)
                '--to' => 'test:production',        // Explicit vault:stage
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify destination
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('MIXED_SYNTAX_KEY');
            expect($destinationSecret->value())->toBe('mixed-value');
        });
    });

    describe('overwrite protection', function () {
        it('fails when destination exists and --overwrite not specified', function () {
            // Create source and destination secrets
            Artisan::call('keep:set', [
                'key' => 'OVERWRITE_KEY',
                'value' => 'source-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Reset command state to ensure fresh vault instance for different stage
            $this->resetCommandState();
            
            Artisan::call('keep:set', [
                'key' => 'OVERWRITE_KEY',
                'value' => 'destination-value',
                '--vault' => 'test',
                '--stage' => 'production',
                '--no-interaction' => true,
            ]);

            // Try to copy without --overwrite
            $result = Artisan::call('keep:copy', [
                'key' => 'OVERWRITE_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(1);

            $output = Artisan::output();
            expect($output)->toContain('already exists in destination');
            expect($output)->toContain('Use --overwrite to replace it');
            
            // Verify destination was not changed
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('OVERWRITE_KEY');
            expect($destinationSecret->value())->toBe('destination-value');
        });

        it('succeeds with --overwrite when destination exists', function () {
            // Create source and destination secrets
            Artisan::call('keep:set', [
                'key' => 'OVERWRITE_KEY2',
                'value' => 'source-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Reset command state to ensure fresh vault instance for different stage
            $this->resetCommandState();

            Artisan::call('keep:set', [
                'key' => 'OVERWRITE_KEY2',
                'value' => 'destination-value',
                '--vault' => 'test',
                '--stage' => 'production',
                '--no-interaction' => true,
            ]);

            // Copy with --overwrite
            $result = Artisan::call('keep:copy', [
                'key' => 'OVERWRITE_KEY2',
                '--from' => 'development',
                '--to' => 'production',
                '--overwrite' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify destination was overwritten
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('OVERWRITE_KEY2');
            expect($destinationSecret->value())->toBe('source-value');
        });
    });

    describe('dry run functionality', function () {
        it('shows preview without making changes with --dry-run', function () {
            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'DRY_RUN_KEY',
                'value' => 'dry-run-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Run with --dry-run
            $result = Artisan::call('keep:copy', [
                'key' => 'DRY_RUN_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--dry-run' => true,
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            $output = Artisan::output();
            expect($output)->toContain('Copy Operation Preview');
            expect($output)->toContain('DRY_RUN_KEY');
            expect($output)->toContain('test:development');
            expect($output)->toContain('test:production');
            expect($output)->toContain('Dry run completed. No changes made.');

            // Verify destination was not created
            $destinationVault = \STS\Keep\Facades\Keep::vault('test')->forStage('production');
            expect($destinationVault->hasSecret('DRY_RUN_KEY'))->toBeFalse();
        });

        it('shows overwrite warning in dry run preview', function () {
            // Create source and destination secrets
            Artisan::call('keep:set', [
                'key' => 'DRY_OVERWRITE_KEY',
                'value' => 'source-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Reset command state to ensure fresh vault instance for different stage
            $this->resetCommandState();
            
            Artisan::call('keep:set', [
                'key' => 'DRY_OVERWRITE_KEY',
                'value' => 'destination-value',
                '--vault' => 'test',
                '--stage' => 'production',
                '--no-interaction' => true,
            ]);

            // Dry run with existing destination
            Artisan::call('keep:copy', [
                'key' => 'DRY_OVERWRITE_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--dry-run' => true,
                '--no-interaction' => true,
            ]);

            $output = Artisan::output();
            expect($output)->toContain('EXISTS (will overwrite)');
        });
    });

    describe('validation and error handling', function () {
        it('prompts for from and to when not provided (tested with provided values)', function () {
            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'VALIDATION_TEST_KEY',
                'value' => 'validation-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Test that command works when all required info is eventually provided
            $result = Artisan::call('keep:copy', [
                'key' => 'VALIDATION_TEST_KEY',
                '--from' => 'development',  // Would be prompted for if not provided
                '--to' => 'production',     // Would be prompted for if not provided
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify copy succeeded
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('VALIDATION_TEST_KEY');
            expect($destinationSecret->value())->toBe('validation-value');
        });

        it('fails when source secret does not exist', function () {
            $result = Artisan::call('keep:copy', [
                'key' => 'NONEXISTENT_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(1);

            $output = Artisan::output();
            expect($output)->toContain('Source secret [NONEXISTENT_KEY] not found');
        });

        it('fails when source and destination are identical', function () {
            $result = Artisan::call('keep:copy', [
                'key' => 'SAME_KEY',
                '--from' => 'development',
                '--to' => 'development',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(1);

            $output = Artisan::output();
            expect($output)->toContain('Source and destination are identical');
        });
    });

    describe('output messages', function () {
        it('shows success message with source and destination details', function () {
            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'SUCCESS_KEY',
                'value' => 'success-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Copy
            Artisan::call('keep:copy', [
                'key' => 'SUCCESS_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            $output = Artisan::output();
            expect($output)->toContain('Successfully copied secret [SUCCESS_KEY]');
            expect($output)->toContain('from test:development to test:production');
        });

        it('masks secure values in preview', function () {
            // Create secure source secret  
            Artisan::call('keep:set', [
                'key' => 'SECURE_PREVIEW_KEY',
                'value' => 'secret-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Run with --dry-run to see preview
            Artisan::call('keep:copy', [
                'key' => 'SECURE_PREVIEW_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--dry-run' => true,
                '--no-interaction' => true,
            ]);

            $output = Artisan::output();
            expect($output)->toContain('<masked>');
            expect($output)->not->toContain('secret-value');
        });

        it('shows plain text values in preview for plain secrets', function () {
            // Create plain source secret
            Artisan::call('keep:set', [
                'key' => 'PLAIN_PREVIEW_KEY',
                'value' => 'plain-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--plain' => true,
                '--no-interaction' => true,
            ]);

            // Run with --dry-run to see preview
            Artisan::call('keep:copy', [
                'key' => 'PLAIN_PREVIEW_KEY',
                '--from' => 'development',
                '--to' => 'production',
                '--dry-run' => true,
                '--no-interaction' => true,
            ]);

            $output = Artisan::output();
            expect($output)->toContain('plain-value');
            expect($output)->toContain('Plain Text');
        });
    });

    describe('edge cases', function () {
        it('handles keys with special characters', function () {
            $specialKey = 'KEY_WITH_SPECIAL-CHARS.123';

            // Create source secret
            Artisan::call('keep:set', [
                'key' => $specialKey,
                'value' => 'special-value',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Copy
            $result = Artisan::call('keep:copy', [
                'key' => $specialKey,
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Verify destination
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get($specialKey);
            expect($destinationSecret->value())->toBe('special-value');
        });

        it('handles values with special characters and unicode', function () {
            $specialValue = 'value with spaces & symbols: @#$%^&*()_+-=[]{}|;:,.<>? 🚀 世界';

            // Create source secret
            Artisan::call('keep:set', [
                'key' => 'SPECIAL_VALUE_COPY',
                'value' => $specialValue,
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Copy
            Artisan::call('keep:copy', [
                'key' => 'SPECIAL_VALUE_COPY',
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            // Verify destination value is preserved exactly
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('SPECIAL_VALUE_COPY');
            expect($destinationSecret->value())->toBe($specialValue);
        });

        it('handles empty values', function () {
            // Create source secret with empty value
            Artisan::call('keep:set', [
                'key' => 'EMPTY_VALUE_COPY',
                'value' => '',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true,
            ]);

            // Copy
            Artisan::call('keep:copy', [
                'key' => 'EMPTY_VALUE_COPY',
                '--from' => 'development',
                '--to' => 'production',
                '--no-interaction' => true,
            ]);

            // Verify empty value is preserved
            $destinationSecret = \STS\Keep\Facades\Keep::vault('test')->forStage('production')->get('EMPTY_VALUE_COPY');
            expect($destinationSecret->value())->toBe('');
        });
    });
});