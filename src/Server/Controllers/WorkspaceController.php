<?php

namespace STS\Keep\Server\Controllers;

use Exception;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\VaultEnvPermissions;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\LocalStorage;

class WorkspaceController extends ApiController
{
    public function get(): array
    {
        $localStorage = new LocalStorage();
        $workspace = $localStorage->getWorkspace();
        
        $allVaults = $this->manager->getAllConfiguredVaults()->map(fn($v) => $v->slug())->values()->toArray();
        $allEnvs = $this->manager->getAllEnvs();
        
        return [
            'active_vaults' => $workspace['active_vaults'] ?? $allVaults,
            'active_envs' => $workspace['active_envs'] ?? $allEnvs,
            'available_vaults' => $allVaults,
            'available_envs' => $allEnvs,
            'created_at' => $workspace['created_at'] ?? null,
            'updated_at' => $workspace['updated_at'] ?? null,
        ];
    }
    
    public function update(): array
    {
        if (!isset($this->body['active_vaults']) || !is_array($this->body['active_vaults'])) {
            return $this->error('active_vaults is required and must be an array');
        }
        
        if (!isset($this->body['active_envs']) || !is_array($this->body['active_envs'])) {
            return $this->error('active_envs is required and must be an array');
        }
        
        if (empty($this->body['active_vaults'])) {
            return $this->error('At least one vault must be selected');
        }
        
        if (empty($this->body['active_envs'])) {
            return $this->error('At least one environment must be selected');
        }
        
        // Validate that selected vaults exist
        $allVaults = $this->manager->getAllConfiguredVaults()->map(fn($v) => $v->slug())->toArray();
        foreach ($this->body['active_vaults'] as $vault) {
            if (!in_array($vault, $allVaults)) {
                return $this->error("Unknown vault: $vault");
            }
        }
        
        // Validate that selected envs exist
        $allEnvs = $this->manager->getAllEnvs();
        foreach ($this->body['active_envs'] as $env) {
            if (!in_array($env, $allEnvs)) {
                return $this->error("Unknown environment: $env");
            }
        }
        
        // Save workspace configuration
        $localStorage = new LocalStorage();
        $currentWorkspace = $localStorage->getWorkspace();
        
        $workspace = [
            'active_vaults' => array_values($this->body['active_vaults']), // Ensure indexed array
            'active_envs' => array_values($this->body['active_envs']), // Ensure indexed array
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
        
        // Get active vaults and envs, or all if not configured
        $activeVaults = $workspace['active_vaults'] ?? $this->manager->getAllConfiguredVaults()->map(fn($v) => $v->slug())->toArray();
        $activeEnvs = $workspace['active_envs'] ?? $this->manager->getAllEnvs();
        
        // Test permissions
        $collection = $this->testBulkPermissions($activeVaults, $activeEnvs);
        
        // Format results for frontend
        $results = [];
        foreach ($collection as $permission) {
            $vault = $permission->vault();
            $env = $permission->env();
            
            if (!isset($results[$vault])) {
                $results[$vault] = [];
            }
            
            $results[$vault][$env] = [
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
    
    protected function testBulkPermissions(array $vaultNames, array $envs): PermissionsCollection
    {
        $collection = new PermissionsCollection();
        $localStorage = new LocalStorage();
        
        foreach ($vaultNames as $vaultName) {
            $vaultPermissions = [];
            
            foreach ($envs as $env) {
                try {
                    $vault = Keep::vault($vaultName, $env);
                    $results = $vault->testPermissions();
                    $permission = VaultEnvPermissions::fromTestResults($vaultName, $env, $results);
                } catch (Exception $e) {
                    $permission = VaultEnvPermissions::fromError($vaultName, $env, $e->getMessage());
                }
                
                $collection->addPermission($permission);
                $vaultPermissions[$env] = $permission->permissions();
            }
            
            // Persist permissions for this vault
            $localStorage->saveVaultPermissions($vaultName, $vaultPermissions);
        }
        
        return $collection;
    }
}