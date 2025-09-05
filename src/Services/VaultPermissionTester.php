<?php

namespace STS\Keep\Services;

use Exception;
use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\VaultStagePermissions;
use STS\Keep\Facades\Keep;
use STS\Keep\Vaults\AbstractVault;

class VaultPermissionTester
{
    public function testPermissions(AbstractVault $vault): array
    {
        $permissions = $this->getEmptyPermissions();
        $testKey = 'keep-verify-'.bin2hex(random_bytes(4));
        $writeSucceeded = false;
        $existingSecrets = null;
        
        // Test List permission
        try {
            $existingSecrets = $vault->list();
            $permissions['List'] = true;
        } catch (Exception) {
            // List failed
        }
        
        // Test Write permission
        try {
            $vault->set($testKey, 'test_value');
            $permissions['Write'] = true;
            $writeSucceeded = true;
        } catch (Exception) {
            // Write failed
        }
        
        // Test Read permission
        if ($writeSucceeded) {
            // If write succeeded, try to read our test secret
            try {
                $secret = $vault->get($testKey);
                $permissions['Read'] = ($secret->value() === 'test_value');
            } catch (Exception) {
                // Read failed
            }
        } elseif ($permissions['List'] && $existingSecrets && $existingSecrets->count() > 0) {
            // If write failed but list succeeded and there are existing secrets,
            // try to read the first existing secret to test read permissions
            try {
                $firstSecret = $existingSecrets->first();
                $vault->get($firstSecret->key());
                $permissions['Read'] = true;
            } catch (Exception) {
                // Read failed
            }
        }
        
        // Test History permission
        if ($writeSucceeded) {
            // If write succeeded, try to get history for our test secret
            try {
                $vault->history($testKey, new FilterCollection(), 10);
                $permissions['History'] = true;
            } catch (Exception) {
                // History not supported or failed
            }
        } elseif ($permissions['List'] && $existingSecrets && $existingSecrets->count() > 0) {
            // If write failed but list succeeded and there are existing secrets,
            // try to get history for the first existing secret
            try {
                $firstSecret = $existingSecrets->first();
                $vault->history($firstSecret->key(), new FilterCollection(), 10);
                $permissions['History'] = true;
            } catch (Exception) {
                // History not supported or failed
            }
        }
        
        // Test Delete permission (cleanup test key if write succeeded)
        if ($writeSucceeded) {
            try {
                $vault->delete($testKey);
                $permissions['Delete'] = true;
            } catch (Exception $e) {
                // Delete failed - log this for debugging
                error_log("Warning: Failed to clean up verify test key '{$testKey}' in vault '{$vault->name()}': ".$e->getMessage());
                $permissions['Delete'] = false;
            }
        }
        
        return $permissions;
    }
    
    public function getEmptyPermissions(): array
    {
        return [
            'Read' => false,
            'Write' => false,
            'List' => false,
            'Delete' => false,
            'History' => false
        ];
    }
    
    /**
     * Test permissions for multiple vaults and stages
     * Automatically persists results to vault configurations
     */
    public function testBulkPermissions(array $vaultNames, array $stages): PermissionsCollection
    {
        $collection = new PermissionsCollection();
        
        foreach ($vaultNames as $vaultName) {
            $vaultPermissions = [];
            
            foreach ($stages as $stage) {
                try {
                    $vault = Keep::vault($vaultName, $stage);
                    $results = $this->testPermissions($vault);
                    $permission = VaultStagePermissions::fromTestResults($vaultName, $stage, $results);
                } catch (Exception $e) {
                    $permission = VaultStagePermissions::fromError($vaultName, $stage, $e->getMessage());
                }
                
                $collection->addPermission($permission);
                $vaultPermissions[$stage] = $permission->permissions();
            }
            
            // Persist permissions for this vault
            $this->persistVaultPermissions($vaultName, $vaultPermissions);
        }
        
        return $collection;
    }
    
    /**
     * Test permissions for a single vault across all stages
     * Automatically persists results
     */
    public function testVaultAcrossStages(string $vaultName): PermissionsCollection
    {
        $stages = Keep::getStages();
        return $this->testBulkPermissions([$vaultName], $stages);
    }
    
    /**
     * Test permissions for all vaults with a new stage
     * Automatically persists results
     */
    public function testNewStageAcrossVaults(string $stageName): PermissionsCollection
    {
        $vaultNames = Keep::getConfiguredVaults()->keys()->toArray();
        return $this->testBulkPermissions($vaultNames, [$stageName]);
    }
    
    /**
     * Test all vaults across all stages
     * Automatically persists results
     */
    public function testAllPermissions(): PermissionsCollection
    {
        $vaultNames = Keep::getConfiguredVaults()->keys()->toArray();
        $stages = Keep::getStages();
        return $this->testBulkPermissions($vaultNames, $stages);
    }
    
    protected function persistVaultPermissions(string $vaultName, array $stagePermissions): void
    {
        $vaultConfig = Keep::getVaultConfig($vaultName);
        if ($vaultConfig) {
            $updatedConfig = $vaultConfig->withPermissions($stagePermissions);
            $updatedConfig->save();
        }
    }
    
    /**
     * Format permissions for display (CLI-style)
     */
    public function formatPermissionsForCli(array $permissions): string
    {
        $formatted = [];
        
        foreach ($permissions as $operation => $allowed) {
            if ($allowed === true) {
                $formatted[] = "<fg=green>✓ {$operation}</>";
            } elseif ($allowed === false) {
                $formatted[] = "<fg=red>✗ {$operation}</>";
            } else {
                $formatted[] = "<fg=gray>? {$operation}</>";
            }
        }
        
        return implode(' ', $formatted);
    }
}