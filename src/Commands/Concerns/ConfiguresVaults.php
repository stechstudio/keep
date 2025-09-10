<?php

namespace STS\Keep\Commands\Concerns;

use Exception;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\VaultConfig;
use STS\Keep\Data\VaultStagePermissions;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\LocalStorage;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait ConfiguresVaults
{
    protected function configureNewVault(): ?array
    {
        // Get available vault classes and build options
        $availableVaults = Keep::getAvailableVaults();
        $driverOptions = [];
        $vaultClassMap = [];

        foreach ($availableVaults as $vaultClass) {
            $driver = $vaultClass::DRIVER;
            $name = $vaultClass::NAME;
            $driverOptions[$driver] = $name;
            $vaultClassMap[$driver] = $vaultClass;
        }

        $selectedDriver = select(
            label: 'Which vault driver would you like to use?',
            options: $driverOptions
        );

        $selectedVaultClass = $vaultClassMap[$selectedDriver];

        // Generate default slug and check for uniqueness
        $defaultSlug = $this->generateUniqueSlug($selectedDriver);

        $slug = text(
            label: 'Vault slug (used in template placeholders)',
            default: $defaultSlug,
            hint: 'Short identifier used in secret templates like {vault:DB_PASSWORD}'
        );

        $existingVaults = Keep::getConfiguredVaults();
        if ($existingVaults->has($slug)) {
            error("A vault with slug '{$slug}' already exists!");

            return null;
        }

        $friendlyName = text(
            label: 'Friendly name for this vault',
            default: $selectedVaultClass::NAME,
            hint: 'A descriptive name to identify this vault configuration'
        );

        $vaultConfig = $this->configureVaultSettings($selectedVaultClass, $friendlyName, $slug);

        if (! $vaultConfig) {
            error('Failed to configure vault');

            return null;
        }

        // Save the vault configuration using robust VaultConfiguration object
        VaultConfig::fromArray($vaultConfig)->save();

        // Set as default vault if none exists
        if (empty(Keep::getSetting('default_vault'))) {
            info("Setting '{$slug}' as your default vault since you don't have one yet.");
            $this->setDefaultVault($slug);
        }

        info("✅ {$friendlyName} vault '{$slug}' configured successfully");
        
        // Reload vault configurations to include the newly saved vault
        // This ensures the permission tester can find and update the vault
        $container = \STS\Keep\KeepContainer::getInstance();
        $container->instance(
            \STS\Keep\KeepManager::class,
            new \STS\Keep\KeepManager(
                \STS\Keep\Data\Settings::load(),
                \STS\Keep\Data\Collections\VaultConfigCollection::load()
            )
        );
        
        // Run verify to check and cache permissions for all stages
        info("\nVerifying vault permissions...");
        $collection = $this->testVaultAcrossStages($slug);
        
        // Display summary of permissions
        foreach ($collection->groupByStage() as $stage => $permissions) {
            $permission = $permissions->first();
            $permString = empty($permission->permissions()) ? 'no permissions' : implode(', ', $permission->permissions());
            info("  • {$stage}: {$permString}");
        }

        return ['slug' => $slug, 'config' => $vaultConfig];
    }
    
    protected function testVaultAcrossStages(string $vaultName): PermissionsCollection
    {
        $stages = Keep::getStages();
        $collection = new PermissionsCollection();
        $localStorage = new LocalStorage();
        $vaultPermissions = [];
        
        foreach ($stages as $stage) {
            try {
                $vault = Keep::vault($vaultName, $stage);
                $results = $vault->testPermissions();
                $permission = VaultStagePermissions::fromTestResults($vaultName, $stage, $results);
            } catch (Exception $e) {
                $permission = VaultStagePermissions::fromError($vaultName, $stage, $e->getMessage());
            }
            
            $collection->addPermission($permission);
            $vaultPermissions[$stage] = $permission->permissions();
        }
        
        // Persist permissions for this vault
        $localStorage->saveVaultPermissions($vaultName, $vaultPermissions);
        
        return $collection;
    }

    private function generateUniqueSlug(string $driver): string
    {
        $existingVaults = Keep::getConfiguredVaults();

        // If the driver doesn't exist, use it as-is
        if (! isset($existingVaults[$driver])) {
            return $driver;
        }

        // Find a unique numbered suffix
        $counter = 2;
        while (isset($existingVaults["{$driver}{$counter}"])) {
            $counter++;
        }

        return "{$driver}{$counter}";
    }

    private function configureVaultSettings(string $vaultClass, string $friendlyName, string $slug): ?array
    {
        info("Configuring {$friendlyName}...");

        // Get dynamic prompts from the vault class
        $prompts = $vaultClass::configure();
        $config = [
            'slug' => $slug,
            'driver' => $vaultClass::DRIVER,
            'name' => $friendlyName,
        ];

        // Process each prompt
        foreach ($prompts as $key => $prompt) {
            $value = $prompt->prompt();

            // Only store non-empty values
            if ($value !== '') {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    private function setDefaultVault(string $vaultName): void
    {
        // Load existing settings or create minimal settings if none exist
        $settings = \STS\Keep\Data\Settings::load();

        if ($settings) {
            $updatedSettings = $settings->withDefaultVault($vaultName);
        } else {
            // Create minimal settings if file doesn't exist
            $updatedSettings = new \STS\Keep\Data\Settings(
                appName: 'keep-app',
                namespace: 'keep-app',
                stages: ['local', 'staging', 'production'],
                defaultVault: $vaultName
            );
        }

        $updatedSettings->save();
    }
}
