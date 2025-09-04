<?php

namespace STS\Keep\Services;

use Exception;
use STS\Keep\Data\Collections\FilterCollection;
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