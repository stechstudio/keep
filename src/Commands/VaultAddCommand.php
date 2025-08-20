<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

class VaultAddCommand extends BaseCommand
{
    protected $signature = 'vault:add';
    protected $description = 'Add a new vault configuration';
    
    protected function process(): int
    {
        info('🗄️  Add New Vault');
        note('Configure a new vault to store your secrets.');
        
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
            return self::FAILURE;
        }
        
        // Get friendly name
        $friendlyName = text(
            label: 'Friendly name for this vault',
            default: $selectedVaultClass::NAME,
            hint: 'A descriptive name to identify this vault configuration'
        );
        
        // Configure the specific vault using dynamic prompts
        $vaultConfig = $this->configureVault($selectedVaultClass, $slug, $friendlyName);
        
        if (!$vaultConfig) {
            error('Failed to configure vault');
            return self::FAILURE;
        }
        
        $this->saveVaultConfig($slug, $vaultConfig);
        
        // Ask if this should be the default vault
        if (empty($this->manager->getSetting('default_vault'))) {
            info("Setting '{$slug}' as your default vault since you don't have one yet.");
            $this->setDefaultVault($slug);
        }
        
        return self::SUCCESS;
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
    
    private function configureVault(string $vaultClass, string $slug, string $friendlyName): ?array
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
        
        info("✅ {$friendlyName} vault '{$slug}' configured successfully");
        
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