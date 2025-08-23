<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Facades\Keep;

class ExportCommand extends BaseCommand
{
    use GathersInput;
    
    public $signature = 'export 
        {--format=env : json|env} 
        {--output= : File where to save the output (defaults to stdout)} 
        {--overwrite : Overwrite the output file if it exists} 
        {--append : Append to the output file if it exists} 
        {--stage= : The stage to export secrets for}
        {--vault= : The vault(s) to export from (comma-separated, defaults to all configured vaults)}'
        .self::ONLY_EXCLUDE_SIGNATURE;

    public $description = 'Export stage secrets from vault(s)';

    public function process()
    {
        $stage = $this->stage();
        $vaultNames = $this->getVaultNames();

        if (count($vaultNames) === 1) {
            $this->info("Exporting secrets from vault '{$vaultNames[0]}' for stage '{$stage}'...");
        } else {
            $this->info("Exporting secrets from " . count($vaultNames) . " vaults (" . implode(', ', $vaultNames) . ") for stage '{$stage}'...");
        }

        $allSecrets = $this->loadSecretsFromVaults($vaultNames, $stage);
        $output = $this->formatOutput($allSecrets);

        $this->info("Found " . $allSecrets->count() . " total secrets to export");

        if ($this->option('output')) {
            $this->writeToFile(
                $this->option('output'),
                $output,
                $this->option('overwrite'),
                $this->option('append')
            );
            $this->info("Secrets exported to [{$this->option('output')}].");
        } else {
            $this->line($output);
        }
    }

    protected function getVaultNames(): array
    {
        // If --vault specified, use those (comma-separated)
        if ($vault = $this->option('vault')) {
            return array_map('trim', explode(',', $vault));
        }

        // Otherwise, use ALL configured vaults
        return Keep::getConfiguredVaults()->keys()->toArray();
    }

    protected function loadSecretsFromVaults(array $vaultNames, string $stage): SecretCollection
    {
        $allSecrets = new SecretCollection();

        foreach ($vaultNames as $vaultName) {
            $vault = Keep::vault($vaultName, $stage);
            $vaultSecrets = $vault->list()->filterByPatterns(
                only: $this->option('only'),
                except: $this->option('except')
            );
            
            // Merge secrets, later vaults override earlier ones for duplicate keys
            $allSecrets = $allSecrets->merge($vaultSecrets);
        }

        return $allSecrets;
    }

    protected function formatOutput(SecretCollection $secrets): string
    {
        return $this->option('format') === 'json'
            ? $secrets
                ->toKeyValuePair()
                ->toJson(JSON_PRETTY_PRINT)
            : $secrets->toEnvString();
    }
}
