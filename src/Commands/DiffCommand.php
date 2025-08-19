<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Collection;
use STS\Keep\Data\SecretDiff;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\DiffService;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DiffCommand extends AbstractCommand
{
    public $signature = 'keep:diff 
        {--envs= : Comma-separated list of environments to compare (defaults to all configured environments)}
        {--vaults= : Comma-separated list of vaults to compare (defaults to current/default vault)}'
        .self::UNMASK_SIGNATURE;

    public $description = 'Compare secrets across multiple environments and vaults in a matrix view';


    public function process(): int
    {
        $vaults = $this->getVaultsToCompare();
        $environments = $this->getEnvironmentsToCompare();

        if (empty($vaults)) {
            $this->error('No vaults available for comparison.');
            return self::FAILURE;
        }

        if (empty($environments)) {
            $this->error('No environments available for comparison.');
            return self::FAILURE;
        }

        $diffService = new DiffService();
        $diffs = spin(fn () => $diffService->compare($vaults, $environments), 'Gathering secrets for comparison...');

        if ($diffs->isEmpty()) {
            $this->info('No secrets found in any of the specified vault/environment combinations.');
            return self::SUCCESS;
        }

        $this->displayTable($diffs, $vaults, $environments, $diffService);

        return self::SUCCESS;
    }

    protected function getVaultsToCompare(): array
    {
        $vaultsOption = $this->option('vaults');
        $vaultOption = $this->option('vault');
        
        if ($vaultsOption) {
            // Parse comma-separated vaults and validate them
            $requestedVaults = array_map('trim', explode(',', $vaultsOption));
            $availableVaults = Keep::available();
            
            $invalidVaults = array_diff($requestedVaults, $availableVaults);
            if (!empty($invalidVaults)) {
                $this->warn("Warning: Unknown vaults specified: " . implode(', ', $invalidVaults));
            }
            
            return array_intersect($requestedVaults, $availableVaults);
        }

        if ($vaultOption) {
            // Single vault specified via --vault option
            return [$vaultOption];
        }

        // Default to current/default vault only
        return [Keep::getDefaultVault()];
    }

    protected function getEnvironmentsToCompare(): array
    {
        $envsOption = $this->option('envs');
        
        if ($envsOption) {
            // Parse comma-separated environments and validate them
            $requestedEnvs = array_map('trim', explode(',', $envsOption));
            $availableEnvs = Keep::environments();
            
            $invalidEnvs = array_diff($requestedEnvs, $availableEnvs);
            if (!empty($invalidEnvs)) {
                $this->warn("Warning: Unknown environments specified: " . implode(', ', $invalidEnvs));
            }
            
            return array_intersect($requestedEnvs, $availableEnvs);
        }

        // Default to all configured environments
        return Keep::environments();
    }

    protected function displayTable(Collection $diffs, array $vaults, array $environments, DiffService $diffService): bool
    {
        $this->newLine();
        $this->info('Secret Comparison Matrix');

        // Build column headers
        $headers = ['Key'];
        $vaultEnvCombinations = [];
        
        foreach ($vaults as $vault) {
            foreach ($environments as $env) {
                $columnHeader = count($vaults) > 1 ? "{$vault}.{$env}" : $env;
                $headers[] = $columnHeader;
                $vaultEnvCombinations[] = "{$vault}.{$env}";
            }
        }
        
        $headers[] = 'Status';

        // Build table rows
        $rows = $diffs->map(function (SecretDiff $diff) use ($vaultEnvCombinations) {
            $row = [$diff->key()];
            
            $masked = !$this->option('unmask');
            
            foreach ($vaultEnvCombinations as $vaultEnv) {
                $row[] = $diff->getValueString($vaultEnv, $masked);
            }
            
            $row[] = $diff->getStatusLabel();
            
            return $row;
        })->toArray();

        table($headers, $rows);

        $this->displaySummary($diffs, $vaults, $environments, $diffService);

        return true;
    }

    protected function displaySummary(Collection $diffs, array $vaults, array $environments, DiffService $diffService): void
    {
        $summary = $diffService->generateSummary($diffs, $vaults, $environments);

        $this->info('Summary:');
        $this->line("• Total secrets: {$summary['total_secrets']}");
        
        if ($summary['total_secrets'] > 0) {
            $this->line("• Identical across all environments: {$summary['identical']} ({$summary['identical_percentage']}%)");
            $this->line("• Different values: {$summary['different']} ({$summary['different_percentage']}%)");
            $this->line("• Missing in some environments: {$summary['incomplete']} ({$summary['incomplete_percentage']}%)");
        }
        
        $this->line("• Environments compared: {$summary['environments_compared']}");
        
        if (count($vaults) > 1) {
            $this->line("• Vaults compared: {$summary['vaults_compared']}");
        } else {
            $this->line("• Vault: {$summary['vaults_compared']}");
        }

    }
}