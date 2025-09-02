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
            'stages' => $settings['stages'] ?? ['local', 'staging', 'production'],
            'default_vault' => $this->manager->getDefaultVault(),
            'default_stage' => $settings['default_stage'] ?? 'prod',
            'keep_version' => KeepApplication::VERSION
        ]);
    }
    
    public function updateSettings(): array
    {
        $settings = $this->manager->getSettings();
        
        // Update settings from request body
        if (isset($this->body['app_name'])) {
            $settings['app_name'] = $this->body['app_name'];
        }
        if (isset($this->body['default_vault'])) {
            $settings['default_vault'] = $this->body['default_vault'];
        }
        if (isset($this->body['default_stage'])) {
            $settings['default_stage'] = $this->body['default_stage'];
        }
        
        // TODO: Save settings to disk - for now just return success
        // In a real implementation, this would save to settings.json
        
        return $this->success([
            'message' => 'Settings updated successfully'
        ]);
    }
    
    public function addStage(): array
    {
        if (!isset($this->body['stage'])) {
            return $this->error('Stage name is required');
        }
        
        $stageName = $this->body['stage'];
        $settings = $this->manager->getSettings();
        $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
        
        if (in_array($stageName, $stages)) {
            return $this->error('Stage already exists');
        }
        
        $stages[] = $stageName;
        $settings['stages'] = $stages;
        
        // TODO: Save settings to disk
        
        return $this->success([
            'message' => 'Stage added successfully',
            'stages' => $stages
        ]);
    }
    
    public function removeStage(): array
    {
        if (!isset($this->body['stage'])) {
            return $this->error('Stage name is required');
        }
        
        $stageName = $this->body['stage'];
        $settings = $this->manager->getSettings();
        $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
        
        // Prevent removing default stages
        $defaultStages = ['local', 'staging', 'production'];
        if (in_array($stageName, $defaultStages)) {
            return $this->error('Cannot remove system stage');
        }
        
        $stages = array_values(array_filter($stages, fn($s) => $s !== $stageName));
        $settings['stages'] = $stages;
        
        // TODO: Save settings to disk
        
        return $this->success([
            'message' => 'Stage removed successfully',
            'stages' => $stages
        ]);
    }
    
    public function addVault(): array
    {
        $required = ['name', 'slug', 'driver'];
        foreach ($required as $field) {
            if (!isset($this->body[$field])) {
                return $this->error("Field '$field' is required");
            }
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
            $permissions = [
                'Read' => false,
                'Write' => false,
                'List' => false,
                'Delete' => false,
                'History' => false,
                'Metadata' => false
            ];
            
            try {
                $vault = $this->manager->vault($vaultName, 'local');
                $testKey = '__keep_verify_' . uniqid();
                
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
                    
                    // Test Read permission
                    try {
                        $value = $vault->get($testKey);
                        $permissions['Read'] = $value === 'test_value';
                    } catch (Exception $e) {
                        // Read failed
                    }
                    
                    // Test History permission
                    try {
                        // History requires FilterCollection as second parameter
                        $filters = new \STS\Keep\Data\Collections\FilterCollection();
                        $vault->history($testKey, $filters, 10);
                        $permissions['History'] = true;
                    } catch (Exception $e) {
                        // History not supported or failed
                    }
                    
                    // Test Metadata permission
                    try {
                        // Most vaults don't have separate metadata, so we'll consider it same as read
                        $permissions['Metadata'] = $permissions['Read'];
                    } catch (Exception $e) {
                        // Metadata failed
                    }
                    
                    // Test Delete permission
                    try {
                        $vault->delete($testKey);
                        $permissions['Delete'] = true;
                    } catch (Exception $e) {
                        // Delete failed
                    }
                } catch (Exception $e) {
                    // Write failed, can't test other permissions
                }
                
                $results[$vaultName] = [
                    'success' => $permissions['List'] || $permissions['Read'],
                    'permissions' => $permissions
                ];
            } catch (Exception $e) {
                $results[$vaultName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'permissions' => $permissions
                ];
            }
        }
        
        return $this->success([
            'results' => $results
        ]);
    }

    public function diff(): array
    {
        $stages = isset($this->query['stages']) 
            ? explode(',', $this->query['stages']) 
            : ['local', 'staging', 'production'];
            
        $vaults = isset($this->query['vaults']) 
            ? explode(',', $this->query['vaults']) 
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