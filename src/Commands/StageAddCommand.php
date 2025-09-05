<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\ValidatesStages;
use STS\Keep\Data\Settings;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\VaultPermissionTester;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

class StageAddCommand extends BaseCommand
{
    use ValidatesStages;

    protected $signature = 'stage:add {name? : The name of the stage to add}';

    protected $description = 'Add a custom stage/environment';

    protected function requiresInitialization(): bool
    {
        return true;
    }

    public function process()
    {
        $settings = Settings::load();
        $stageName = $this->getStageName($settings);

        if (! $stageName) {
            return self::FAILURE;
        }

        $this->line('Current stages: '.implode(', ', $settings->stages()));

        if (! confirm("Add '{$stageName}' as a new stage?")) {
            info('Stage addition cancelled.');

            return self::SUCCESS;
        }

        $this->addStage($settings, $stageName);

        info("✅ Stage '{$stageName}' has been added successfully!");
        $this->line('You can now use this stage with any Keep command using --stage='.$stageName);
        
        // Verify and cache permissions for all vaults with the new stage
        $this->verifyPermissionsForNewStage($stageName);

        return self::SUCCESS;
    }

    private function getStageName(Settings $settings): ?string
    {
        $stageName = $this->argument('name') ?: $this->promptForStageName($settings);

        $error = $this->validateNewStageName($stageName, $settings->stages());
        if ($error) {
            error($error);

            return null;
        }

        return $stageName;
    }

    private function promptForStageName(Settings $settings): string
    {
        return text(
            label: 'Enter the name of the new stage',
            placeholder: 'e.g., qa, demo, sandbox, dev2',
            required: true,
            validate: fn ($value) => $this->validateNewStageName($value, $settings->stages())
        );
    }

    private function addStage(Settings $settings, string $stageName): void
    {
        Settings::fromArray([
            'app_name' => $settings->appName(),
            'namespace' => $settings->namespace(),
            'stages' => [...$settings->stages(), $stageName],
            'default_vault' => $settings->defaultVault(),
            'created_at' => $settings->createdAt(),
        ])->save();
    }
    
    private function verifyPermissionsForNewStage(string $stageName): void
    {
        $vaults = Keep::getConfiguredVaults();
        if ($vaults->isEmpty()) {
            return; // No vaults configured yet
        }
        
        info('\nVerifying vault permissions for the new stage...');
        
        $tester = new VaultPermissionTester();
        
        foreach ($vaults as $vaultConfig) {
            try {
                $vault = Keep::vault($vaultConfig->slug(), $stageName);
                $permissions = $tester->testPermissions($vault);
                
                // Build permissions array
                $stagePermissions = [];
                if ($permissions['List']) $stagePermissions[] = 'list';
                if ($permissions['Read']) $stagePermissions[] = 'read';
                if ($permissions['Write']) $stagePermissions[] = 'write';
                if ($permissions['Delete']) $stagePermissions[] = 'delete';
                if ($permissions['History']) $stagePermissions[] = 'history';
                
                // Update vault config with these permissions
                $updatedConfig = $vaultConfig->withStagePermissions($stageName, $stagePermissions);
                $updatedConfig->save();
                
                // Display result
                $permString = empty($stagePermissions) ? 'no permissions' : implode(', ', $stagePermissions);
                info("  • {$vaultConfig->name()}: {$permString}");
            } catch (\Exception $e) {
                // Skip vaults that can't be verified
                info("  • {$vaultConfig->name()}: unable to verify");
            }
        }
    }
}
