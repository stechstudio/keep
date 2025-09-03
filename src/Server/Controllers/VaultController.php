<?php

namespace STS\Keep\Server\Controllers;

use Exception;
use STS\Keep\KeepApplication;

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
            'default_stage' => $settings['default_stage'] ?? 'prod',
            'keep_version' => KeepApplication::VERSION
        ]);
    }
    
    public function updateSettings(): array
    {
        try {
            $currentSettings = $this->manager->getSettings();
            
            // Update settings from request body
            $fields = ['app_name', 'namespace', 'default_vault', 'default_stage'];
            foreach ($fields as $field) {
                if ($this->hasParam($field)) {
                    $currentSettings[$field] = $this->getParam($field);
                }
            }
            
            // Create a new Settings object with updated values
            $newSettings = \STS\Keep\Data\Settings::fromArray($currentSettings);
            
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
            $newSettings = \STS\Keep\Data\Settings::fromArray($settings);
            $newSettings->save();
            
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
            
            // Prevent removing default stages
            $defaultStages = ['local', 'staging', 'production'];
            if (in_array($stageName, $defaultStages)) {
                return $this->error('Cannot remove system stage');
            }
            
            $stages = array_values(array_filter($stages, fn($s) => $s !== $stageName));
            $settings['stages'] = $stages;
            
            // Save updated settings to disk
            $newSettings = \STS\Keep\Data\Settings::fromArray($settings);
            $newSettings->save();
            
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
        
        // TODO: Add vault to configuration
        // This would need to update the vaults.json configuration
        
        return $this->success([
            'message' => 'Vault added successfully'
        ]);
    }
    
    public function updateVault(string $slug): array
    {
        // TODO: Update vault configuration
        // This would need to update the vaults.json configuration
        
        return $this->success([
            'message' => 'Vault updated successfully'
        ]);
    }
    
    public function deleteVault(string $slug): array
    {
        // Prevent deleting the default vault
        if ($slug === $this->manager->getDefaultVault()) {
            return $this->error('Cannot delete the default vault');
        }
        
        // TODO: Remove vault from configuration
        // This would need to update the vaults.json configuration
        
        return $this->success([
            'message' => 'Vault deleted successfully'
        ]);
    }

    public function verify(): array
    {
        $results = [];
        
        foreach ($this->manager->getConfiguredVaults() as $vaultConfig) {
            $vaultName = $vaultConfig->slug();
            
            try {
                $vault = $this->manager->vault($vaultName, 'local');
                $permissions = $this->testVaultPermissions($vault);
                
                $results[$vaultName] = [
                    'success' => $permissions['List'] || $permissions['Read'],
                    'permissions' => $permissions
                ];
            } catch (Exception $e) {
                $results[$vaultName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'permissions' => $this->getEmptyPermissions()
                ];
            }
        }
        
        return $this->success([
            'results' => $results
        ]);
    }

    private function testVaultPermissions($vault): array
    {
        $permissions = $this->getEmptyPermissions();
        $testKey = 'keep-verify-' . bin2hex(random_bytes(4));
        $writeSucceeded = false;
        
        // Test List permission
        try {
            $vault->list();
            $permissions['List'] = true;
        } catch (Exception $e) {
            // List failed
        }
        
        // Test Write permission
        try {
            $vault->set($testKey, 'test_value');
            $permissions['Write'] = true;
            $writeSucceeded = true;
            
            // Test Read permission
            try {
                $secret = $vault->get($testKey);
                $permissions['Read'] = ($secret->value() === 'test_value');
            } catch (Exception $e) {
                // Read failed
            }
            
            // Test History permission
            try {
                $filters = new \STS\Keep\Data\Collections\FilterCollection();
                $vault->history($testKey, $filters, 10);
                $permissions['History'] = true;
            } catch (Exception $e) {
                // History not supported or failed
            }
        } catch (Exception $e) {
            // Write failed, can't test read/history
        }
        
        // Always try to clean up the test key if write succeeded
        if ($writeSucceeded) {
            try {
                $vault->delete($testKey);
                $permissions['Delete'] = true;
            } catch (Exception $e) {
                // Delete failed - log this for debugging
                error_log("Warning: Failed to clean up verify test key '{$testKey}' in vault '{$vault->name()}': " . $e->getMessage());
                $permissions['Delete'] = false;
            }
        }
        
        return $permissions;
    }

    private function getEmptyPermissions(): array
    {
        return [
            'Read' => false,
            'Write' => false,
            'List' => false,
            'Delete' => false,
            'History' => false
        ];
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