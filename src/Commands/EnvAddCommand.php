<?php

namespace STS\Keep\Commands;

use Exception;
use STS\Keep\Commands\Concerns\ValidatesEnvs;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\Settings;
use STS\Keep\Data\VaultEnvPermissions;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\LocalStorage;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

class EnvAddCommand extends BaseCommand
{
    use ValidatesEnvs;

    protected $signature = 'env:add {name? : The name of the environment to add}';

    protected $description = 'Add a custom environment';

    protected function requiresInitialization(): bool
    {
        return true;
    }

    public function process()
    {
        $settings = Settings::load();
        $envName = $this->getEnvName($settings);

        if (! $envName) {
            return self::FAILURE;
        }

        $this->line('Current environments: '.implode(', ', $settings->envs()));

        if (! confirm("Add '{$envName}' as a new environment?")) {
            info('Environment addition cancelled.');

            return self::SUCCESS;
        }

        $this->addEnv($settings, $envName);

        info("✅ Environment '{$envName}' has been added successfully!");
        $this->line('You can now use this environment with any Keep command using --env='.$envName);
        
        // Verify and cache permissions for all vaults with the new environment
        $collection = $this->testNewEnvAcrossVaults($envName);
        
        if (!$collection->isEmpty()) {
            info('\nVerified vault permissions for the new environment:');
            foreach ($collection as $permission) {
                $permString = empty($permission->permissions()) ? 'no permissions' : implode(', ', $permission->permissions());
                info("  • {$permission->vault()}: {$permString}");
            }
        }

        return self::SUCCESS;
    }

    private function getEnvName(Settings $settings): ?string
    {
        $envName = $this->argument('name') ?: $this->promptForEnvName($settings);

        $error = $this->validateNewEnvName($envName, $settings->envs());
        if ($error) {
            error($error);

            return null;
        }

        return $envName;
    }

    private function promptForEnvName(Settings $settings): string
    {
        return text(
            label: 'Enter the name of the new environment',
            placeholder: 'e.g., qa, demo, sandbox, dev2',
            required: true,
            validate: fn ($value) => $this->validateNewEnvName($value, $settings->envs())
        );
    }

    private function addEnv(Settings $settings, string $envName): void
    {
        Settings::fromArray([
            'app_name' => $settings->appName(),
            'namespace' => $settings->namespace(),
            'envs' => [...$settings->envs(), $envName],
            'default_vault' => $settings->defaultVault(),
            'created_at' => $settings->createdAt(),
        ])->save();
    }
    
    protected function testNewEnvAcrossVaults(string $envName): PermissionsCollection
    {
        $vaultNames = Keep::getConfiguredVaults()->keys()->toArray();
        $collection = new PermissionsCollection();
        $localStorage = new LocalStorage();
        
        foreach ($vaultNames as $vaultName) {
            try {
                $vault = Keep::vault($vaultName, $envName);
                $results = $vault->testPermissions();
                $permission = VaultEnvPermissions::fromTestResults($vaultName, $envName, $results);
            } catch (Exception $e) {
                $permission = VaultEnvPermissions::fromError($vaultName, $envName, $e->getMessage());
            }
            
            $collection->addPermission($permission);
            
            // Update stored permissions for this vault
            $existingPermissions = $localStorage->getVaultPermissions($vaultName) ?? [];
            $existingPermissions[$envName] = $permission->permissions();
            $localStorage->saveVaultPermissions($vaultName, $existingPermissions);
        }
        
        return $collection;
    }
}
