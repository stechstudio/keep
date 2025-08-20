<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

class VaultEditCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('vault:edit')
             ->setDescription('Edit an existing vault configuration')
             ->addArgument('slug', InputArgument::OPTIONAL, 'Slug of the vault to edit');
    }
    
    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        info('🔧  Edit Vault Configuration');
        
        $configuredVaults = $this->manager->getConfiguredVaults();
        
        if (empty($configuredVaults)) {
            error('No vaults are configured yet.');
            info('Add your first vault with: keep vault:add');
            return self::FAILURE;
        }
        
        // Get vault slug from argument or prompt user to select
        $slug = $input->getArgument('slug');
        
        if (!$slug) {
            // Build options for vault selection
            $vaultOptions = [];
            foreach ($configuredVaults as $vaultSlug => $config) {
                $vaultOptions[$vaultSlug] = "{$config['name']} ({$vaultSlug})";
            }
            
            $slug = select(
                label: 'Which vault would you like to edit?',
                options: $vaultOptions
            );
        }
        
        // Validate vault exists
        if (!isset($configuredVaults[$slug])) {
            error("Vault '{$slug}' does not exist.");
            return self::FAILURE;
        }
        
        $existingConfig = $configuredVaults[$slug];
        
        info("Editing vault: {$existingConfig['name']} ({$slug})");
        
        // Find the vault class for this driver
        $vaultClass = $this->findVaultClass($existingConfig['driver']);
        if (!$vaultClass) {
            error("Unknown vault driver: {$existingConfig['driver']}");
            return self::FAILURE;
        }
        
        // Edit built-in fields
        $newSlug = text(
            label: 'Driver slug (used in template placeholders)',
            default: $slug,
            hint: 'Short identifier used in secret templates like {vault:DB_PASSWORD}'
        );
        
        $friendlyName = text(
            label: 'Friendly name for this vault',
            default: $existingConfig['name'],
            hint: 'A descriptive name to identify this vault configuration'
        );
        
        // Edit vault-specific configuration
        $updatedConfig = $this->editVaultConfig($vaultClass, $friendlyName, $existingConfig);
        
        // Handle slug changes
        if ($newSlug !== $slug) {
            // Check if new slug already exists
            if (isset($configuredVaults[$newSlug])) {
                error("A vault with slug '{$newSlug}' already exists!");
                return self::FAILURE;
            }
            
            // Delete old vault file and save with new slug
            $this->deleteVaultConfig($slug);
            $this->saveVaultConfig($newSlug, $updatedConfig);
            
            // Update default vault if this was the default
            if ($this->manager->getSetting('default_vault') === $slug) {
                $this->setDefaultVault($newSlug);
            }
            
            info("✅ Vault configuration updated and renamed from '{$slug}' to '{$newSlug}'");
        } else {
            // Save with same slug
            $this->saveVaultConfig($slug, $updatedConfig);
            info("✅ Vault '{$slug}' configuration updated successfully");
        }
        
        return self::SUCCESS;
    }
    
    private function findVaultClass(string $driver): ?string
    {
        $availableVaults = $this->manager->getAvailableVaults();
        
        foreach ($availableVaults as $vaultClass) {
            if ($vaultClass::DRIVER === $driver) {
                return $vaultClass;
            }
        }
        
        return null;
    }
    
    private function editVaultConfig(string $vaultClass, string $friendlyName, array $existingConfig): array
    {
        info("Configuring {$friendlyName}...");
        
        // Get dynamic prompts from the vault class, passing existing settings
        $prompts = $vaultClass::configure($existingConfig);
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
    
    private function saveVaultConfig(string $slug, array $config): void
    {
        $vaultPath = getcwd() . "/.keep/vaults/{$slug}.json";
        file_put_contents($vaultPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    private function deleteVaultConfig(string $slug): void
    {
        $vaultPath = getcwd() . "/.keep/vaults/{$slug}.json";
        if (file_exists($vaultPath)) {
            unlink($vaultPath);
        }
    }
    
    private function setDefaultVault(string $vaultSlug): void
    {
        $settingsPath = getcwd() . '/.keep/settings.json';
        $settings = json_decode(file_get_contents($settingsPath), true);
        $settings['default_vault'] = $vaultSlug;
        file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}