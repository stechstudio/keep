<?php

namespace STS\Keep\Commands;

use Exception;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\VaultEnvPermissions;
use STS\Keep\Data\Workspace;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\LocalStorage;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

class WorkspaceConfigureCommand extends BaseCommand
{
    protected $signature = 'workspace:configure';
    
    protected $description = 'Configure your personal workspace - select which vaults and environments you work with';
    
    protected function process(): int
    {
        info('🎯 Workspace Configuration');
        note('Select which vaults and environments you want to work with. This personalizes your Keep experience.');
        
        $localStorage = new LocalStorage();
        $currentWorkspace = $localStorage->getWorkspace();
        
        // Get all available vaults and environments (not filtered by workspace)
        $availableVaults = Keep::getAllConfiguredVaults()->map(fn($v) => $v->slug())->toArray();
        $availableEnvs = Keep::getAllEnvs();
        
        if (empty($availableVaults)) {
            $this->error('No vaults are configured. Please add a vault first with: keep vault:add');
            return self::FAILURE;
        }
        
        if (empty($availableEnvs)) {
            $this->error('No environments are configured. This should not happen.');
            return self::FAILURE;
        }
        
        // Select active vaults
        $activeVaults = multiselect(
            label: 'Select vaults to include in your workspace',
            options: array_combine($availableVaults, $availableVaults),
            default: $currentWorkspace['active_vaults'] ?? $availableVaults,
            hint: 'Toggle with space bar, confirm with enter',
            required: true
        );
        
        if (empty($activeVaults)) {
            $this->error('You must select at least one vault.');
            return self::FAILURE;
        }
        
        // Select active environments
        $activeEnvs = multiselect(
            label: 'Select environments to include in your workspace',
            options: array_combine($availableEnvs, $availableEnvs),
            default: $currentWorkspace['active_envs'] ?? $availableEnvs,
            hint: 'Toggle with space bar, confirm with enter',
            required: true
        );
        
        if (empty($activeEnvs)) {
            $this->error('You must select at least one environment.');
            return self::FAILURE;
        }
        
        // Show summary
        $this->info('');
        $this->info('Your workspace will include:');
        $this->line('  Vaults: ' . implode(', ', $activeVaults));
        $this->line('  Envs: ' . implode(', ', $activeEnvs));
        $this->info('');
        
        if (!confirm('Save this workspace configuration?', true)) {
            note('Workspace configuration cancelled.');
            return self::SUCCESS;
        }
        
        // Save workspace
        $workspace = [
            'active_vaults' => $activeVaults,
            'active_envs' => $activeEnvs,
            'created_at' => $currentWorkspace['created_at'] ?? date('c')
        ];
        
        $localStorage->saveWorkspace($workspace);
        $this->success('Workspace configuration saved');
        
        // Run verification on the new workspace
        $this->info('');
        info('Verifying permissions for your workspace...');
        
        $collection = spin(
            fn() => $this->testBulkPermissions($activeVaults, $activeEnvs),
            'Testing vault access permissions...'
        );
        
        // Display summary
        $this->info('');
        $this->info('Permission Summary:');
        
        $summary = [];
        foreach ($collection as $permission) {
            $vault = $permission->vault();
            $env = $permission->env();
            $perms = $permission->permissions();
            
            if (!isset($summary[$vault])) {
                $summary[$vault] = [];
            }
            
            $summary[$vault][$env] = empty($perms) 
                ? '<fg=red>no access</>' 
                : '<fg=green>' . implode(', ', $perms) . '</>';
        }
        
        foreach ($summary as $vault => $envs) {
            $this->line("  <info>{$vault}</info>:");
            foreach ($envs as $env => $perms) {
                $this->line("    • {$env}: {$perms}");
            }
        }
        
        $this->info('');
        note('Your workspace is configured! The UI will now only show your selected vaults and environments.');
        
        return self::SUCCESS;
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