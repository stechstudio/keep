<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Collection;
use STS\Keep\Data\Context;
use STS\Keep\Data\SecretDiff;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\DiffService;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DiffCommand extends AbstractCommand
{
    public $signature = 'keep:diff 
        {--context= : Comma-separated list of contexts to compare (e.g., "vault1:stage1,vault2:stage2")}
        {--stage= : Comma-separated list of stages to compare (defaults to all configured stages)}
        {--vault= : Comma-separated list of vaults to compare (defaults to current/default vault)}'
        .self::UNMASK_SIGNATURE;

    public $description = 'Compare secrets across multiple stages and vaults in a matrix view';

    public function process(): int
    {
        // If --context is provided, use it to get vaults and stages
        if ($this->option('context')) {
            [$vaults, $stages] = $this->parseContextsToVaultsAndStages();
        } else {
            $vaults = $this->getVaultsToCompare();
            $stages = $this->getStagesToCompare();
        }

        if (empty($vaults)) {
            $this->error('No vaults available for comparison.');

            return self::FAILURE;
        }

        if (empty($stages)) {
            $this->error('No stages available for comparison.');

            return self::FAILURE;
        }

        $diffService = new DiffService;
        $diffs = spin(fn () => $diffService->compare($vaults, $stages), 'Gathering secrets for comparison...');

        if ($diffs->isEmpty()) {
            $this->info('No secrets found in any of the specified vault/stage combinations.');

            return self::SUCCESS;
        }

        $this->displayTable($diffs, $vaults, $stages, $diffService);

        return self::SUCCESS;
    }

    protected function getVaultsToCompare(): array
    {
        $vaultOption = $this->option('vault');

        if ($vaultOption) {
            $requestedVaults = array_map('trim', explode(',', $vaultOption));

            $invalidVaults = array_diff($requestedVaults, Keep::available());
            if (! empty($invalidVaults)) {
                $this->warn('Warning: Unknown vaults specified: '.implode(', ', $invalidVaults));
            }

            return array_intersect($requestedVaults, Keep::available());
        }

        // Default to current/default vault only
        return [Keep::getDefaultVault()];
    }

    protected function getStagesToCompare(): array
    {
        $stagesOption = $this->option('stage');

        if ($stagesOption) {
            // Parse comma-separated stages and validate them
            $requestedStages = array_map('trim', explode(',', $stagesOption));
            $availableStages = Keep::stages();

            $invalidStages = array_diff($requestedStages, $availableStages);
            if (! empty($invalidStages)) {
                $this->warn('Warning: Unknown stages specified: '.implode(', ', $invalidStages));
            }

            return array_intersect($requestedStages, $availableStages);
        }

        // Default to all configured stages
        return Keep::stages();
    }

    protected function displayTable(Collection $diffs, array $vaults, array $stages, DiffService $diffService): bool
    {
        $this->newLine();
        $this->info('Secret Comparison Matrix');

        // Build column headers
        $headers = ['Key'];
        $vaultEnvCombinations = [];

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

        return true;
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

    protected function parseContextsToVaultsAndStages(): array
    {
        $contextInputs = array_map('trim', explode(',', $this->option('context')));
        $contexts = collect($contextInputs)->map(fn($input) => Context::fromInput($input));
        
        $vaults = $contexts->pluck('vault')->unique()->values()->toArray();
        $stages = $contexts->pluck('stage')->unique()->values()->toArray();
        
        // Validate vaults exist
        $invalidVaults = array_diff($vaults, Keep::available());
        if (!empty($invalidVaults)) {
            $this->warn('Warning: Unknown vaults specified: ' . implode(', ', $invalidVaults));
            $vaults = array_intersect($vaults, Keep::available());
        }
        
        // Validate stages exist
        $invalidStages = array_diff($stages, Keep::stages());
        if (!empty($invalidStages)) {
            $this->warn('Warning: Unknown stages specified: ' . implode(', ', $invalidStages));
            $stages = array_intersect($stages, Keep::stages());
        }
        
        return [$vaults, $stages];
    }
}
