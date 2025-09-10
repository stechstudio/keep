<?php

namespace STS\Keep\Server\Controllers;

use Exception;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\VaultStagePermissions;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\LocalStorage;

class WorkspaceController extends ApiController
{
    /**
     * Get current workspace configuration
     */
    public function get(): array
    {
        $localStorage = new LocalStorage();
        $workspace = $localStorage->getWorkspace();
        
        // Get all available vaults and stages (unfiltered)
        $allVaults = $this->manager->getAllConfiguredVaults()->map(fn($v) => $v->slug())->values()->toArray();
        $allStages = $this->manager->getAllStages();
        
        return [
            'active_vaults' => $workspace['active_vaults'] ?? $allVaults,
            'active_stages' => $workspace['active_stages'] ?? $allStages,
            'available_vaults' => $allVaults,
            'available_stages' => $allStages,
            'created_at' => $workspace['created_at'] ?? null,
            'updated_at' => $workspace['updated_at'] ?? null,
        ];
    }
    
    /**
     * Update workspace configuration
     */
    public function update(): array
    {
        if (!isset($this->body['active_vaults']) || !is_array($this->body['active_vaults'])) {
            return $this->error('active_vaults is required and must be an array');
        }
        
        if (!isset($this->body['active_stages']) || !is_array($this->body['active_stages'])) {
            return $this->error('active_stages is required and must be an array');
        }
        
        if (empty($this->body['active_vaults'])) {
            return $this->error('At least one vault must be selected');
        }
        
        if (empty($this->body['active_stages'])) {
            return $this->error('At least one stage must be selected');
        }
        
        // Validate that selected vaults exist
        $allVaults = $this->manager->getAllConfiguredVaults()->map(fn($v) => $v->slug())->toArray();
        foreach ($this->body['active_vaults'] as $vault) {
            if (!in_array($vault, $allVaults)) {
                return $this->error("Unknown vault: $vault");
            }
        }
        
        // Validate that selected stages exist
        $allStages = $this->manager->getAllStages();
        foreach ($this->body['active_stages'] as $stage) {
            if (!in_array($stage, $allStages)) {
                return $this->error("Unknown stage: $stage");
            }
        }
        
        // Save workspace configuration
        $localStorage = new LocalStorage();
        $currentWorkspace = $localStorage->getWorkspace();
        
        $workspace = [
            'active_vaults' => array_values($this->body['active_vaults']), // Ensure indexed array
            'active_stages' => array_values($this->body['active_stages']), // Ensure indexed array
            'created_at' => $currentWorkspace['created_at'] ?? date('c'),
        ];
        
        $localStorage->saveWorkspace($workspace);
        
        return [
            'success' => true,
            'workspace' => array_merge($workspace, [
                'updated_at' => date('c')
            ])
        ];
    }
    
    /**
     * Test permissions for the current workspace
     */
    public function verify(): array
    {
        $localStorage = new LocalStorage();
        $workspace = $localStorage->getWorkspace();
        
        // Get active vaults and stages, or all if not configured
        $activeVaults = $workspace['active_vaults'] ?? $this->manager->getAllConfiguredVaults()->map(fn($v) => $v->slug())->toArray();
        $activeStages = $workspace['active_stages'] ?? $this->manager->getAllStages();
        
        // Test permissions
        $collection = $this->testBulkPermissions($activeVaults, $activeStages);
        
        // Format results for frontend
        $results = [];
        foreach ($collection as $permission) {
            $vault = $permission->vault();
            $stage = $permission->stage();
            
            if (!isset($results[$vault])) {
                $results[$vault] = [];
            }
            
            $results[$vault][$stage] = [
                'success' => !empty($permission->permissions()),
                'permissions' => $permission->permissions(),
                'error' => $permission->error()
            ];
        }
        
        return [
            'success' => true,
            'results' => $results
        ];
    }
    
    protected function testBulkPermissions(array $vaultNames, array $stages): PermissionsCollection
    {
        $collection = new PermissionsCollection();
        $localStorage = new LocalStorage();
        
        foreach ($vaultNames as $vaultName) {
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
        }
        
        return $collection;
    }
}