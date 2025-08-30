<?php

use STS\Keep\Data\Settings;

describe('StageAddCommand', function () {
    
    beforeEach(function () {
        $this->tempDir = createTempKeepDir();

        // Create .keep directory and settings to initialize Keep
        mkdir('.keep');
        mkdir('.keep/vaults');

        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'test',
            'stages' => ['testing', 'production'],
            'created_at' => date('c'),
            'version' => '1.0',
        ];

        file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));

        // Create test vault configuration for testing (never hits AWS)
        $vaultConfig = [
            'driver' => 'test',
            'name' => 'Test Vault',
            'namespace' => 'test-app',
        ];

        file_put_contents('.keep/vaults/test.json', json_encode($vaultConfig, JSON_PRETTY_PRINT));
    });

    afterEach(function () {
        if (isset($this->tempDir)) {
            cleanupTempDir($this->tempDir);
        }
    });
    
    describe('adding custom stages', function () {
        
        it('adds a new custom stage via argument', function () {
            $initialSettings = Settings::load();
            
            // Use a unique stage name for this test
            $stageName = 'test-stage-' . uniqid();
            
            expect($initialSettings->stages())->not->toContain($stageName);
            
            // Add a custom stage (auto-confirmed in non-interactive mode)
            $commandTester = runCommand('stage:add', [
                'name' => $stageName
            ]);
            
            expect($commandTester->getStatusCode())->toBe(0);
            
            // Verify stage was added
            $updatedSettings = Settings::load();
            expect($updatedSettings->stages())->toContain($stageName);
        });
        
        it('validates stage name format', function () {
            // Try to add an invalid stage name
            $commandTester = runCommand('stage:add', [
                'name' => 'invalid stage!' // Contains space and special char
            ]);
            
            expect($commandTester->getStatusCode())->toBe(1);
            expect($commandTester->getDisplay())->toContain('can only contain');
            
            // Verify stage was not added
            $settings = Settings::load();
            expect($settings->stages())->not->toContain('invalid stage!');
        });
        
        it('prevents duplicate stage names', function () {
            $settings = Settings::load();
            $existingStage = $settings->stages()[0]; // Get first existing stage
            
            // Try to add a duplicate
            $commandTester = runCommand('stage:add', [
                'name' => $existingStage
            ]);
            
            expect($commandTester->getStatusCode())->toBe(1);
            
            // Count should remain the same
            $updatedSettings = Settings::load();
            expect(count($updatedSettings->stages()))->toBe(count($settings->stages()));
        });
        
        it('allows lowercase alphanumeric names with hyphens and underscores', function () {
            $validNames = ['dev-2', 'test_env', 'qa1', 'prod-backup'];
            
            foreach ($validNames as $stageName) {
                // Remove stage if it exists (cleanup from previous tests)
                $settings = Settings::load();
                $stages = array_diff($settings->stages(), [$stageName]);
                Settings::fromArray([
                    'app_name' => $settings->appName(),
                    'namespace' => $settings->namespace(),
                    'stages' => array_values($stages),
                    'default_vault' => $settings->defaultVault(),
                    'created_at' => $settings->createdAt(),
                ])->save();
                
                // Add the stage
                $commandTester = runCommand('stage:add', [
                    'name' => $stageName
                ]);
                
                expect($commandTester->getStatusCode())->toBe(0);
                
                // Verify it was added
                $updatedSettings = Settings::load();
                expect($updatedSettings->stages())->toContain($stageName);
            }
        });
    });
    
    
    describe('integration with other commands', function () {
        
        it('makes custom stage available for use', function () {
            // Add a unique custom stage
            $stageName = 'integration-' . uniqid();
            $commandTester = runCommand('stage:add', ['name' => $stageName]);
            
            expect($commandTester->getStatusCode())->toBe(0);
            expect($commandTester->getDisplay())->toContain("Stage '{$stageName}' has been added successfully");
            
            // Verify the custom stage is persisted in settings
            $settings = Settings::load();
            expect($settings->stages())->toContain($stageName);
            
            // Verify multiple custom stages can be added
            $secondStage = 'secondary-' . uniqid();
            $secondCommand = runCommand('stage:add', ['name' => $secondStage]);
            
            expect($secondCommand->getStatusCode())->toBe(0);
            
            $updatedSettings = Settings::load();
            expect($updatedSettings->stages())->toContain($stageName);
            expect($updatedSettings->stages())->toContain($secondStage);
        });
    });
});