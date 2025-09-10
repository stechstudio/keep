<?php

namespace STS\Keep\Commands;

use Exception;
use STS\Keep\Commands\Concerns\ValidatesStages;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\Settings;
use STS\Keep\Data\VaultStagePermissions;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\LocalStorage;

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
        $collection = $this->testNewStageAcrossVaults($stageName);
        
        if (!$collection->isEmpty()) {
            info('\nVerified vault permissions for the new stage:');
            foreach ($collection as $permission) {
                $permString = empty($permission->permissions()) ? 'no permissions' : implode(', ', $permission->permissions());
                info("  • {$permission->vault()}: {$permString}");
            }
        }

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
    
    protected function testNewStageAcrossVaults(string $stageName): PermissionsCollection
    {
        $vaultNames = Keep::getConfiguredVaults()->keys()->toArray();
        $collection = new PermissionsCollection();
        $localStorage = new LocalStorage();
        
        foreach ($vaultNames as $vaultName) {
            try {
                $vault = Keep::vault($vaultName, $stageName);
                $results = $vault->testPermissions();
                $permission = VaultStagePermissions::fromTestResults($vaultName, $stageName, $results);
            } catch (Exception $e) {
                $permission = VaultStagePermissions::fromError($vaultName, $stageName, $e->getMessage());
            }
            
            $collection->addPermission($permission);
            
            // Update stored permissions for this vault
            $existingPermissions = $localStorage->getVaultPermissions($vaultName) ?? [];
            $existingPermissions[$stageName] = $permission->permissions();
            $localStorage->saveVaultPermissions($vaultName, $existingPermissions);
        }
        
        return $collection;
    }
}
