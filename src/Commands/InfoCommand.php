<?php

namespace STS\Keep\Commands;

use STS\Keep\Facades\Keep;

use function Laravel\Prompts\table;

class InfoCommand extends AbstractCommand
{
    public $signature = 'keep:info {--format=table : table|json}';

    public $description = 'Display Keep configuration and status information';

    public function process(): int
    {
        $info = $this->gatherInfo();

        return match ($this->option('format')) {
            'table' => $this->displayTable($info) ?: self::SUCCESS,
            'json' => $this->line(json_encode($info, JSON_PRETTY_PRINT)) ?: self::SUCCESS,
            default => $this->error('Invalid format option. Supported formats are: table, json.') ?: self::FAILURE,
        };
    }

    protected function gatherInfo(): array
    {
        return [
            'namespace' => Keep::namespace(),
            'stage' => Keep::stage(),
            'default_vault' => Keep::getDefaultVault(),
            'available_vaults' => Keep::available(),
            'configured_stages' => Keep::stages(),
            'vault_configurations' => $this->getVaultConfigurations(),
        ];
    }

    protected function getVaultConfigurations(): array
    {
        $configurations = [];

        foreach (Keep::available() as $vaultName) {
            $config = config("keep.vaults.$vaultName", []);
            $configurations[$vaultName] = [
                'driver' => $config['driver'] ?? 'Unknown',
                'region' => $config['region'] ?? null,
                'prefix' => $config['prefix'] ?? null,
            ];
        }

        return $configurations;
    }

    protected function displayTable(array $info): void
    {
        $this->newLine();
        $this->info('Keep Configuration');
        $this->newLine();

        // Basic configuration
        table(['Setting', 'Value'], [
            ['Namespace', $info['namespace']],
            ['Current Stage', $info['stage']],
            ['Default Vault', $info['default_vault']],
        ]);

        $this->newLine();

        // Available stages
        $this->info('Configured Stages');
        table(['Stage'], array_map(fn ($stage) => [$stage], $info['configured_stages']));

        $this->newLine();

        // Vault configurations
        $this->info('Vault Configurations');
        $vaultRows = [];
        foreach ($info['vault_configurations'] as $name => $config) {
            $details = [];
            if ($config['driver']) {
                $details[] = "driver: {$config['driver']}";
            }
            if ($config['region']) {
                $details[] = "region: {$config['region']}";
            }
            if ($config['prefix']) {
                $details[] = "prefix: {$config['prefix']}";
            }

            $vaultRows[] = [
                $name,
                $config['driver'],
                implode(', ', array_filter([$config['region'], $config['prefix']])),
            ];
        }

        table(['Vault', 'Driver', 'Configuration'], $vaultRows);
    }
}
