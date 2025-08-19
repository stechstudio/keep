<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Env;
use STS\Keep\Data\SecretCollection;
use STS\Keep\Exceptions\KeepException;

use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class ImportCommand extends AbstractCommand
{
    public $signature = 'keep:import {from? : Env file to import from}
        {--overwrite : Overwrite existing secrets} 
        {--skip-existing : Skip existing secrets}  
        {--dry-run : Show what would be imported without actually importing} '
        .self::ONLY_EXCLUDE_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Import a .env file and store as stage secrets in a specified vault';

    public function process(): int
    {
        if ($this->option('overwrite') && $this->option('skip-existing')) {
            $this->error('You cannot use --overwrite and --skip-existing together.');

            return self::FAILURE;
        }

        $envFilePath = $this->argument('from') ?? text('Path to .env file', required: true);
        if (! file_exists($envFilePath) || ! is_readable($envFilePath)) {
            $this->error("Env file [$envFilePath] does not exist or is not readable.");

            return self::FAILURE;
        }

        $env = new Env(file_get_contents($envFilePath));

        // Convert env entries to secrets and apply filtering
        $importSecrets = $env->secrets()->filterByPatterns(
            only: $this->option('only'),
            except: $this->option('except')
        );

        $vaultSecrets = $this->vault()->list();

        if (! $this->canImport($importSecrets, $vaultSecrets)) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $imported = null;
        } else {
            $imported = $this->runImport($importSecrets, $vaultSecrets);
        }

        table(['Key', 'Status', 'Rev'], $this->resultsTable($importSecrets, $vaultSecrets, $imported));

        if ($this->option('dry-run')) {
            $this->info('This was a dry run. No secrets were imported.');
        }

        return self::SUCCESS;
    }

    protected function canImport(SecretCollection $importSecrets, SecretCollection $vaultSecrets): bool
    {
        // If any keys exist in the vault, we can only proceed if --overwrite or --skip-existing is set
        $existingKeys = $importSecrets->allKeys()->intersect($vaultSecrets->allKeys());

        if ($existingKeys->isNotEmpty()) {
            if ($this->option('overwrite')) {
                $this->warn('The following keys already exist and will be overwritten: '.$existingKeys->implode(', '));

                return true;
            }

            if ($this->option('skip-existing')) {
                $this->warn('The following keys already exist and will be skipped: '.$existingKeys->implode(', '));

                return true;
            }

            $this->error('The following keys already exist: '.$existingKeys->implode(', '));
            $this->line('Use --overwrite to overwrite existing keys, or --skip-existing to skip them.');

            return false;
        }

        return true;
    }

    protected function runImport(SecretCollection $importSecrets, SecretCollection $vaultSecrets): SecretCollection
    {
        $imported = new SecretCollection;

        foreach ($importSecrets as $secret) {
            if ($vaultSecrets->hasKey($secret->key()) && $this->option('skip-existing')) {
                continue;
            }

            if (empty($secret->value())) {
                $this->warn("Skipping key [{$secret->key()}] with empty value.");

                continue;
            }

            try {
                $imported->push(
                    $importedSecret = $this->vault()->set($secret->key(), $secret->value())
                );
                $this->info("Imported key [{$importedSecret->key()}]");
            } catch (KeepException $e) {
                $this->error("Failed to import key [{$secret->key()}]: ".$e->getMessage());
            }
        }

        return $imported;
    }

    protected function resultsTable(SecretCollection $importSecrets, SecretCollection $vaultSecrets, ?SecretCollection $imported): array
    {
        $rows = [];

        foreach ($importSecrets as $secret) {
            $key = $secret->key();
            $value = $secret->value();

            // Determine status based on what actually happened
            $status = 'Skipped';
            $revision = null;

            if ($imported && $imported->hasKey($key)) {
                $status = 'Imported';
                $revision = $imported->getByKey($key)->revision();
            } elseif (empty($value)) {
                $status = 'Skipped'; // Empty value
            } elseif ($vaultSecrets->hasKey($key)) {
                $status = 'Exists'; // Already exists in vault
                $revision = $vaultSecrets->getByKey($key)?->revision();
            }

            $rows[] = [
                'key' => $key,
                'status' => $status,
                'revision' => $revision,
            ];
        }

        return $rows;
    }
}
