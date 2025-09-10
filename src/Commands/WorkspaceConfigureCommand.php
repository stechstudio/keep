<?php

namespace STS\Keep\Commands;

use Exception;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\VaultStagePermissions;
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
    
    protected $description = 'Configure your personal workspace - select which vaults and stages you work with';
    
    protected function process(): int
    {
        info('🎯 Workspace Configuration');
        note('Select which vaults and stages you want to work with. This personalizes your Keep experience.');
        
        $localStorage = new LocalStorage();
        $currentWorkspace = $localStorage->getWorkspace();
        
        // Get all available vaults and stages (not filtered by workspace)
        $availableVaults = Keep::getAllConfiguredVaults()->map(fn($v) => $v->slug())->toArray();
        $availableStages = Keep::getAllStages();
        
        if (empty($availableVaults)) {
            $this->error('No vaults are configured. Please add a vault first with: keep vault:add');
            return self::FAILURE;
        }
        
        if (empty($availableStages)) {
            $this->error('No stages are configured. This should not happen.');
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
        
        // Select active stages
        $activeStages = multiselect(
            label: 'Select stages to include in your workspace',
            options: array_combine($availableStages, $availableStages),
            default: $currentWorkspace['active_stages'] ?? $availableStages,
            hint: 'Toggle with space bar, confirm with enter',
            required: true
        );
        
        if (empty($activeStages)) {
            $this->error('You must select at least one stage.');
            return self::FAILURE;
        }
        
        // Show summary
        $this->info('');
        $this->info('Your workspace will include:');
        $this->line('  Vaults: ' . implode(', ', $activeVaults));
        $this->line('  Stages: ' . implode(', ', $activeStages));
        $this->info('');
        
        if (!confirm('Save this workspace configuration?', true)) {
            note('Workspace configuration cancelled.');
            return self::SUCCESS;
        }
        
        // Save workspace
        $workspace = [
            'active_vaults' => $activeVaults,
            'active_stages' => $activeStages,
            'created_at' => $currentWorkspace['created_at'] ?? date('c')
        ];
        
        $localStorage->saveWorkspace($workspace);
        $this->success('Workspace configuration saved');
        
        // Run verification on the new workspace
        $this->info('');
        info('Verifying permissions for your workspace...');
        
        $collection = spin(
            fn() => $this->testBulkPermissions($activeVaults, $activeStages),
            'Testing vault access permissions...'
        );
        
        // Display summary
        $this->info('');
        $this->info('Permission Summary:');
        
        $summary = [];
        foreach ($collection as $permission) {
            $vault = $permission->vault();
            $stage = $permission->stage();
            $perms = $permission->permissions();
            
            if (!isset($summary[$vault])) {
                $summary[$vault] = [];
            }
            
            $summary[$vault][$stage] = empty($perms) 
                ? '<fg=red>no access</>' 
                : '<fg=green>' . implode(', ', $perms) . '</>';
        }
        
        foreach ($summary as $vault => $stages) {
            $this->line("  <info>{$vault}</info>:");
            foreach ($stages as $stage => $perms) {
                $this->line("    • {$stage}: {$perms}");
            }
        }
        
        $this->info('');
        note('Your workspace is configured! The UI will now only show your selected vaults and stages.');
        
        return self::SUCCESS;
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