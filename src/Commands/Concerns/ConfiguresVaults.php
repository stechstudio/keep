<?php

namespace STS\Keep\Commands\Concerns;

use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

trait ConfiguresVaults
{
    protected function configureNewVault(): ?array
    {
        // Get available vault classes and build options
        $availableVaults = $this->manager->getAvailableVaults();
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
            label: 'Driver slug (used in template placeholders)',
            default: $defaultSlug,
            hint: 'Short identifier used in secret templates like {vault:DB_PASSWORD}'
        );
        
        // Validate slug uniqueness
        $existingVaults = $this->manager->getConfiguredVaults();
        if (isset($existingVaults[$slug])) {
            error("A vault with slug '{$slug}' already exists!");
            return null;
        }
        
        // Get friendly name
        $friendlyName = text(
            label: 'Friendly name for this vault',
            default: $selectedVaultClass::NAME,
            hint: 'A descriptive name to identify this vault configuration'
        );
        
        // Configure the specific vault using dynamic prompts
        $vaultConfig = $this->configureVaultSettings($selectedVaultClass, $friendlyName);
        
        if (!$vaultConfig) {
            error('Failed to configure vault');
            return null;
        }
        
        // Save the vault configuration
        $this->saveVaultConfig($slug, $vaultConfig);
        
        // Set as default vault if none exists
        if (empty($this->manager->getSetting('default_vault'))) {
            info("Setting '{$slug}' as your default vault since you don't have one yet.");
            $this->setDefaultVault($slug);
        }
        
        info("✅ {$friendlyName} vault '{$slug}' configured successfully");
        
        return ['slug' => $slug, 'config' => $vaultConfig];
    }
    
    private function generateUniqueSlug(string $driver): string
    {
        $existingVaults = $this->manager->getConfiguredVaults();
        
        // If the driver doesn't exist, use it as-is
        if (!isset($existingVaults[$driver])) {
            return $driver;
        }
        
        // Find a unique numbered suffix
        $counter = 2;
        while (isset($existingVaults["{$driver}{$counter}"])) {
            $counter++;
        }
        
        return "{$driver}{$counter}";
    }
    
    private function configureVaultSettings(string $vaultClass, string $friendlyName): ?array
    {
        info("Configuring {$friendlyName}...");
        
        // Get dynamic prompts from the vault class
        $prompts = $vaultClass::configure();
        $config = [
            'driver' => $vaultClass::DRIVER,
            'name' => $friendlyName
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
    
    private function saveVaultConfig(string $name, array $config): void
    {
        $vaultPath = getcwd() . "/.keep/vaults/{$name}.json";
        file_put_contents($vaultPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    private function setDefaultVault(string $vaultName): void
    {
        $settingsPath = getcwd() . '/.keep/settings.json';
        $settings = json_decode(file_get_contents($settingsPath), true);
        $settings['default_vault'] = $vaultName;
        file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}