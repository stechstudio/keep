<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Collection;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\SecretDiff;
use STS\Keep\Facades\Keep;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DiffCommand extends BaseCommand
{
    use GathersInput;

    public $signature = 'diff 
        {--env= : Comma-separated list of environments to compare (defaults to all configured environments)}
        {--vault= : Comma-separated list of vaults to compare (defaults to all configured vaults)}
        {--unmask : Show full secret values instead of masked values}
        {--only= : Only include keys matching this pattern (e.g. DB_*)} 
        {--except= : Exclude keys matching this pattern (e.g. MAIL_*)}';

    public $description = 'Compare secrets across multiple environments and vaults in a matrix view';

    public function process()
    {
        $vaults = $this->getVaultsToCompare();
        $envs = $this->getEnvsToCompare();

        if (empty($vaults)) {
            $this->error('No vaults available for comparison.');

            return self::FAILURE;
        }

        if (empty($envs)) {
            $this->error('No environments available for comparison.');

            return self::FAILURE;
        }

        $diffs = spin(fn () => SecretCollection::compare($vaults, $envs, $this->option('only'), $this->option('except')), 'Gathering secrets for comparison...');

        if ($diffs->isNotEmpty()) {
            $this->displayTable($diffs, $vaults, $envs);
        } else {
            $this->info('No secrets found in any of the specified vault/environment combinations.');
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

        return Keep::getConfiguredVaults()->keys()->toArray();
    }

    protected function getEnvsToCompare(): array
    {
        $envsOption = $this->option('env');

        if ($envsOption) {
            $requestedEnvs = array_map('trim', explode(',', $envsOption));
            $availableEnvs = Keep::getEnvs();

            $invalidEnvs = array_diff($requestedEnvs, $availableEnvs);
            if (! empty($invalidEnvs)) {
                $this->warn('Warning: Unknown environments specified: '.implode(', ', $invalidEnvs));
            }

            return array_intersect($requestedEnvs, $availableEnvs);
        }

        return Keep::getEnvs();
    }

    protected function displayTable(Collection $diffs, array $vaults, array $envs): void
    {
        $this->newLine();
        $this->info('Secret Comparison Matrix');

        $headers = ['Key'];
        $vaultEnvCombinations = [];

        foreach ($vaults as $vault) {
            foreach ($envs as $env) {
                $columnHeader = count($vaults) > 1 ? "{$vault}.{$env}" : $env;
                $headers[] = $columnHeader;
                $vaultEnvCombinations[] = "{$vault}.{$env}";
            }
        }

        $headers[] = 'Status';

        $rows = $diffs->map(function (SecretDiff $diff) use ($vaultEnvCombinations) {
            $row = [$diff->key()];

            $masked = ! $this->option('unmask');

            foreach ($vaultEnvCombinations as $vaultEnv) {
                $row[] = $diff->getValueString($vaultEnv, $masked);
            }

            $row[] = $diff->getStatusLabel();

            return $row;
        })->toArray();

        table($headers, $rows);

        $this->displaySummary($diffs, $vaults, $envs);
    }

    protected function displaySummary(Collection $diffs, array $vaults, array $envs): void
    {
        $summary = SecretCollection::generateDiffSummary($diffs, $vaults, $envs);

        $this->info('Summary:');
        $this->line("• Total secrets: {$summary['total_secrets']}");

        if ($summary['total_secrets'] > 0) {
            $this->line("• Identical across all environments: {$summary['identical']} ({$summary['identical_percentage']}%)");
            $this->line("• Different values: {$summary['different']} ({$summary['different_percentage']}%)");
            $this->line("• Missing in some environments: {$summary['incomplete']} ({$summary['incomplete_percentage']}%)");
        }

        $this->line("• Environments compared: {$summary['envs_compared']}");

        if (count($vaults) > 1) {
            $this->line("• Vaults compared: {$summary['vaults_compared']}");
        } else {
            $this->line("• Vault: {$summary['vaults_compared']}");
        }
    }
}
