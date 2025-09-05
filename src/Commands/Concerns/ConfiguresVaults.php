<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Data\VaultConfig;
use STS\Keep\Facades\Keep;

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
        
        // Run verify to check and cache permissions for all stages
        $this->verifyAndCachePermissions($slug);

        return ['slug' => $slug, 'config' => $vaultConfig];
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
    
    protected function verifyAndCachePermissions(string $vaultSlug): void
    {
        info('\nVerifying vault permissions...');
        
        $stages = Keep::getStages();
        if (empty($stages)) {
            return; // No stages configured yet
        }
        
        $tester = new \STS\Keep\Services\VaultPermissionTester();
        $allPermissions = [];
        
        foreach ($stages as $stage) {
            try {
                $vault = Keep::vault($vaultSlug, $stage);
                $permissions = $tester->testPermissions($vault);
                
                // Build permissions array
                $stagePermissions = [];
                if ($permissions['List']) $stagePermissions[] = 'list';
                if ($permissions['Read']) $stagePermissions[] = 'read';
                if ($permissions['Write']) $stagePermissions[] = 'write';
                if ($permissions['Delete']) $stagePermissions[] = 'delete';
                if ($permissions['History']) $stagePermissions[] = 'history';
                
                $allPermissions[$stage] = $stagePermissions;
                
                // Display result for this stage
                $permString = empty($stagePermissions) ? 'no permissions' : implode(', ', $stagePermissions);
                info("  • {$stage}: {$permString}");
            } catch (\Exception $e) {
                // Skip stages that can't be verified
                info("  • {$stage}: unable to verify");
            }
        }
        
        // Save all permissions at once
        if (!empty($allPermissions)) {
            $vaultConfig = Keep::getVaultConfig($vaultSlug);
            if ($vaultConfig) {
                $updatedConfig = $vaultConfig->withPermissions($allPermissions);
                $updatedConfig->save();
            }
        }
    }
}
