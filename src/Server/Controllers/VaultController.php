<?php

namespace STS\Keep\Server\Controllers;

use Exception;
use STS\Keep\Data\Settings;
use STS\Keep\KeepApplication;
use STS\Keep\Services\VaultPermissionTester;

class VaultController extends ApiController
{
    public function list(): array
    {
        $vaults = $this->manager->getConfiguredVaults();
        $defaultVault = $this->manager->getDefaultVault();
        
        $vaultList = $vaults->map(function($config) use ($defaultVault) {
            $slug = $config->slug();
            $name = $config->name();
            $driver = $config->driver();
            $scope = $config->scope();
            
            // Get the vault class to access its friendly NAME constant if available
            $vaultClass = null;
            foreach ($this->manager->getAvailableVaults() as $class) {
                if ($class::DRIVER === $driver) {
                    $vaultClass = $class;
                    break;
                }
            }
            
            // Use the name from config, or fall back to the class NAME constant
            $friendlyName = $name ?: ($vaultClass ? $vaultClass::NAME : ucfirst($driver));
            
            return [
                'slug' => $slug,
                'name' => $friendlyName,
                'driver' => $driver,
                'scope' => $scope,
                'isDefault' => $slug === $defaultVault,
                // Legacy fields for compatibility
                'display' => $friendlyName . ' (' . $slug . ')'
            ];
        });
        
        return $this->success([
            'vaults' => $vaultList->values()->toArray()
        ]);
    }

    public function listStages(): array
    {
        $settings = $this->manager->getSettings();
        $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
        
        return $this->success([
            'stages' => $stages
        ]);
    }

    public function getSettings(): array
    {
        $settings = $this->manager->getSettings();
        
        return $this->success([
            'app_name' => $settings['app_name'] ?? 'Keep',
            'namespace' => $settings['namespace'] ?? '',
            'stages' => $settings['stages'] ?? ['local', 'staging', 'production'],
            'default_vault' => $this->manager->getDefaultVault(),
            'template_path' => $settings['template_path'] ?? 'env',
            'keep_version' => KeepApplication::VERSION
        ]);
    }
    
    public function updateSettings(): array
    {
        try {
            $currentSettings = $this->manager->getSettings();
            
            // Update settings from request body
            $fields = ['app_name', 'namespace', 'default_vault', 'template_path'];
            foreach ($fields as $field) {
                if ($this->hasParam($field)) {
                    $currentSettings[$field] = $this->getParam($field);
                }
            }
            
            // Create a new Settings object with updated values
            $newSettings = Settings::fromArray($currentSettings);
            
            // Save settings to disk
            $newSettings->save();
            
            // Note: The manager would need to be reloaded to pick up the new settings
            // In a real app, you might want to reinitialize the manager or have a method to update its settings
            
            return $this->success([
                'message' => 'Settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to save settings: ' . $e->getMessage());
        }
    }
    
    public function addStage(): array
    {
        if ($error = $this->requireFields(['stage'])) {
            return $error;
        }
        
        try {
            $stageName = $this->getParam('stage');
            $settings = $this->manager->getSettings();
            $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
            
            if (in_array($stageName, $stages)) {
                return $this->error('Stage already exists');
            }
            
            $stages[] = $stageName;
            $settings['stages'] = $stages;
            
            // Save updated settings to disk
            Settings::fromArray($settings)->save();
            
            return $this->success([
                'message' => 'Stage added successfully',
                'stages' => $stages
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to add stage: ' . $e->getMessage());
        }
    }
    
    public function removeStage(): array
    {
        if ($error = $this->requireFields(['stage'])) {
            return $error;
        }
        
        try {
            $stageName = $this->getParam('stage');
            $settings = $this->manager->getSettings();
            $stages = $settings['stages'] ?? ['local', 'staging', 'production'];

            $settings['stages'] = array_values(
                array_filter($stages, fn($s) => $s !== $stageName)
            );

            Settings::fromArray($settings)->save();
            
            return $this->success([
                'message' => 'Stage removed successfully',
                'stages' => $stages
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to remove stage: ' . $e->getMessage());
        }
    }
    
    public function addVault(): array
    {
        if ($error = $this->requireFields(['name', 'slug', 'driver'])) {
            return $error;
        }
        
        try {
            $slug = $this->getParam('slug');
            $driver = $this->getParam('driver');
            $name = $this->getParam('name');
            $isDefault = $this->getParam('isDefault', false);
            $scope = $this->getParam('scope', '');
            
            // Check if vault already exists
            $existingVaults = $this->manager->getConfiguredVaults();
            if ($existingVaults->has($slug)) {
                return $this->error('Vault with this slug already exists');
            }
            
            // Create vault config with scope as top-level property
            $vaultConfig = new \STS\Keep\Data\VaultConfig(
                slug: $slug,
                driver: $driver,
                name: $name,
                scope: $scope,
                config: []  // vendor-specific settings would go here
            );
            
            // Save vault using its own save method
            $vaultConfig->save();
            
            // Update default vault in settings if requested
            if ($isDefault) {
                $settings = $this->manager->getSettings();
                $settings['default_vault'] = $slug;
                $newSettings = Settings::fromArray($settings);
                $newSettings->save();
            }
            
            return $this->success([
                'message' => 'Vault added successfully',
                'vault' => [
                    'slug' => $slug,
                    'name' => $name,
                    'driver' => $driver,
                    'scope' => $scope,
                    'isDefault' => $isDefault
                ]
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to add vault: ' . $e->getMessage());
        }
    }
    
    public function updateVault(string $slug): array
    {
        try {
            // Check if vault exists
            $existingVaults = $this->manager->getConfiguredVaults();
            if (!$existingVaults->has($slug)) {
                return $this->error('Vault not found');
            }
            
            // Get existing vault config
            $existingVaultConfig = $existingVaults->get($slug);
            $existingConfig = $existingVaultConfig->toArray();
            
            // Update fields that were provided
            if ($this->hasParam('name')) {
                $existingConfig['name'] = $this->getParam('name');
            }
            
            if ($this->hasParam('driver')) {
                $existingConfig['driver'] = $this->getParam('driver');
            }
            
            // Handle scope configuration
            if ($this->hasParam('scope')) {
                $scope = $this->getParam('scope');
                if (!empty($scope)) {
                    $existingConfig['scope'] = $scope;
                } else {
                    // Remove scope if empty string provided
                    unset($existingConfig['scope']);
                }
            }
            
            // Handle slug change
            $newSlug = $this->getParam('slug', $slug);
            if ($newSlug !== $slug) {
                // Check if new slug already exists
                if ($existingVaults->has($newSlug)) {
                    return $this->error('A vault with the new slug already exists');
                }
                
                $existingConfig['slug'] = $newSlug;
            }
            
            // Validate and create the updated config
            try {
                $vaultConfig = \STS\Keep\Data\VaultConfig::fromArray($existingConfig);
            } catch (\Exception $e) {
                return $this->error('Invalid vault configuration: ' . $e->getMessage());
            }
            
            // Save updated config
            $vaultConfig->save();
            
            // If slug changed, delete old file
            if ($newSlug !== $slug) {
                $oldVaultFile = getcwd().'/.keep/vaults/'.$slug.'.json';
                if (file_exists($oldVaultFile)) {
                    unlink($oldVaultFile);
                }
                
                // Update default vault in settings if this was the default
                $settings = $this->manager->getSettings();
                if ($settings['default_vault'] === $slug) {
                    $settings['default_vault'] = $newSlug;
                    $newSettings = Settings::fromArray($settings);
                    $newSettings->save();
                }
            }
            
            // Handle isDefault flag
            if ($this->hasParam('isDefault') && $this->getParam('isDefault')) {
                $settings = $this->manager->getSettings();
                $settings['default_vault'] = $newSlug;
                $newSettings = Settings::fromArray($settings);
                $newSettings->save();
            }
            
            return $this->success([
                'message' => 'Vault updated successfully',
                'vault' => [
                    'slug' => $newSlug,
                    'name' => $vaultConfig->name(),
                    'driver' => $vaultConfig->driver(),
                    'scope' => $vaultConfig->scope(),
                    'isDefault' => $this->manager->getDefaultVault() === $newSlug
                ]
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to update vault: ' . $e->getMessage());
        }
    }
    
    public function deleteVault(string $slug): array
    {
        try {
            // Prevent deleting the default vault
            if ($slug === $this->manager->getDefaultVault()) {
                return $this->error('Cannot delete the default vault');
            }
            
            // Check if vault exists
            $existingVaults = $this->manager->getConfiguredVaults();
            if (!$existingVaults->has($slug)) {
                return $this->error('Vault not found');
            }
            
            $vaultFile = getcwd().'/.keep/vaults/'.$slug.'.json';
            
            // Delete the vault configuration file
            if (!unlink($vaultFile)) {
                return $this->error('Failed to delete vault configuration file');
            }
            
            return $this->success([
                'message' => 'Vault deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to delete vault: ' . $e->getMessage());
        }
    }

    public function verify(): array
    {
        $results = [];
        $tester = new VaultPermissionTester();
        
        foreach ($this->manager->getConfiguredVaults() as $vaultConfig) {
            $vaultName = $vaultConfig->slug();
            
            try {
                $vault = $this->manager->vault($vaultName, 'local');
                $permissions = $tester->testPermissions($vault);
                
                $results[$vaultName] = [
                    'success' => $permissions['List'] || $permissions['Read'],
                    'permissions' => $permissions
                ];
            } catch (Exception $e) {
                $results[$vaultName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'permissions' => $tester->getEmptyPermissions()
                ];
            }
        }
        
        return $this->success([
            'results' => $results
        ]);
    }


    public function diff(): array
    {
        $stages = $this->hasParam('stages')
            ? explode(',', $this->getParam('stages'))
            : ['local', 'staging', 'production'];
            
        $vaults = $this->hasParam('vaults')
            ? explode(',', $this->getParam('vaults'))
            : array_map(fn($v) => $v->slug(), $this->manager->getConfiguredVaults()->toArray());
        
        $matrix = [];
        
        foreach ($vaults as $vaultName) {
            try {
                foreach ($stages as $stage) {
                    $vault = $this->manager->vault($vaultName, $stage);
                    $secrets = $vault->list();
                    foreach ($secrets as $secret) {
                        // Return unmasked values so client can handle masking
                        $matrix[$secret->key()][$vaultName][$stage] = $secret->value();
                    }
                }
            } catch (Exception $e) {
                // Skip vault if it fails
            }
        }
        
        return $this->success([
            'diff' => $matrix,
            'stages' => $stages,
            'vaults' => $vaults
        ]);
    }
}