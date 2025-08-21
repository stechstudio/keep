<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Collection;
use STS\Keep\Data\Context;
use STS\Keep\Data\SecretDiff;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\DiffService;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DiffCommand extends BaseCommand
{
    public $signature = 'diff 
        {--stage= : Comma-separated list of stages to compare (defaults to all configured stages)}
        {--vault= : Comma-separated list of vaults to compare (defaults to all configured vaults)}'
        .self::UNMASK_SIGNATURE;

    public $description = 'Compare secrets across multiple stages and vaults in a matrix view';

    public function process()
    {
        $vaults = $this->getVaultsToCompare();
        $stages = $this->getStagesToCompare();

        if (empty($vaults)) {
            return $this->error('No vaults available for comparison.');
        }

        if (empty($stages)) {
            return $this->error('No stages available for comparison.');
        }

        $diffService = new DiffService;
        $diffs = spin(fn () => $diffService->compare($vaults, $stages), 'Gathering secrets for comparison...');

        if ($diffs->isNotEmpty()) {
            $this->displayTable($diffs, $vaults, $stages, $diffService);
        } else {
            $this->info('No secrets found in any of the specified vault/stage combinations.');
        }
    }

    protected function getVaultsToCompare(): array
    {
        $vaultOption = $this->option('vault');

        if ($vaultOption) {
            $requestedVaults = array_map('trim', explode(',', $vaultOption));

            $configuredVaultNames = Keep::getConfiguredVaults()->keys()->toArray();
            $invalidVaults = array_diff($requestedVaults, $configuredVaultNames);
            if (! empty($invalidVaults)) {
                $this->warn('Warning: Unknown vaults specified: '.implode(', ', $invalidVaults));
            }

            return array_intersect($requestedVaults, $configuredVaultNames);
        }

        // Default to all configured vaults
        return Keep::getConfiguredVaults()->keys()->toArray();
    }

    protected function getStagesToCompare(): array
    {
        $stagesOption = $this->option('stage');

        if ($stagesOption) {
            // Parse comma-separated stages and validate them
            $requestedStages = array_map('trim', explode(',', $stagesOption));
            $availableStages = Keep::getStages();

            $invalidStages = array_diff($requestedStages, $availableStages);
            if (! empty($invalidStages)) {
                $this->warn('Warning: Unknown stages specified: '.implode(', ', $invalidStages));
            }

            return array_intersect($requestedStages, $availableStages);
        }

        // Default to all configured stages
        return Keep::getStages();
    }

    protected function displayTable(Collection $diffs, array $vaults, array $stages, DiffService $diffService): void
    {
        $this->newLine();
        $this->info('Secret Comparison Matrix');

        // Build column headers
        $headers = ['Key'];
        $vaultStageCombinations = [];

        foreach ($vaults as $vault) {
            foreach ($stages as $stage) {
                $columnHeader = count($vaults) > 1 ? "{$vault}.{$stage}" : $stage;
                $headers[] = $columnHeader;
                $vaultStageCombinations[] = "{$vault}.{$stage}";
            }
        }

        $headers[] = 'Status';

        // Build table rows
        $rows = $diffs->map(function (SecretDiff $diff) use ($vaultStageCombinations) {
            $row = [$diff->key()];

            $masked = ! $this->option('unmask');

            foreach ($vaultStageCombinations as $vaultStage) {
                $row[] = $diff->getValueString($vaultStage, $masked);
            }

            $row[] = $diff->getStatusLabel();

            return $row;
        })->toArray();

        table($headers, $rows);

        $this->displaySummary($diffs, $vaults, $stages, $diffService);
    }

    protected function displaySummary(Collection $diffs, array $vaults, array $stages, DiffService $diffService): void
    {
        $summary = $diffService->generateSummary($diffs, $vaults, $stages);

        $this->info('Summary:');
        $this->line("• Total secrets: {$summary['total_secrets']}");

        if ($summary['total_secrets'] > 0) {
            $this->line("• Identical across all stages: {$summary['identical']} ({$summary['identical_percentage']}%)");
            $this->line("• Different values: {$summary['different']} ({$summary['different_percentage']}%)");
            $this->line("• Missing in some stages: {$summary['incomplete']} ({$summary['incomplete_percentage']}%)");
        }

        $this->line("• Stages compared: {$summary['stages_compared']}");

        if (count($vaults) > 1) {
            $this->line("• Vaults compared: {$summary['vaults_compared']}");
        } else {
            $this->line("• Vault: {$summary['vaults_compared']}");
        }
    }

}
