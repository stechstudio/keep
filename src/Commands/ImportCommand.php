<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Env;
use STS\Keep\Data\SecretsCollection;
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
    .self::ENV_SIGNATURE;

    public $description = 'Import a .env file and store as environment secrets in a specified vault';

    public function process(): int
    {
        if ($this->option('overwrite') && $this->option('skip-existing')) {
            $this->error("You cannot use --overwrite and --skip-existing together.");
            return self::FAILURE;
        }

        $envFilePath = $this->argument('from') ?? text('Path to .env file', required: true);
        if (!file_exists($envFilePath) || !is_readable($envFilePath)) {
            $this->error("Env file [$envFilePath] does not exist or is not readable.");
            return self::FAILURE;
        }

        $env = new Env(file_get_contents($envFilePath));

        $secrets = $this->vault()->list()->filterByPatterns(
            only: $this->option('only'),
            except: $this->option('except')
        );

        if (!$this->canImport($env, $secrets)) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $imported = null;
        } else {
            $imported = $this->runImport($env, $secrets);
        }

        table(['Key', 'Status', 'Rev'], $this->resultsTable($env, $secrets, $imported));

        if ($this->option('dry-run')) {
            $this->info("This was a dry run. No secrets were imported.");
        }

        return self::SUCCESS;
    }

    protected function canImport(Env $env, SecretsCollection $secrets): bool
    {
        // If any keys exist in the vault, we can only proceed if --overwrite or --skip-existing is set
        $existingKeys = $env->allKeys()->intersect($secrets->allKeys());

        if ($existingKeys->isNotEmpty()) {
            if ($this->option('overwrite')) {
                $this->warn("The following keys already exist and will be overwritten: ".$existingKeys->implode(', '));
                return true;
            }

            if ($this->option('skip-existing')) {
                $this->warn("The following keys already exist and will be skipped: ".$existingKeys->implode(', '));
                return true;
            }

            $this->error("The following keys already exist: ".$existingKeys->implode(', '));
            $this->line("Use --overwrite to overwrite existing keys, or --skip-existing to skip them.");
            return false;
        }

        return true;
    }

    protected function runImport(Env $env, SecretsCollection $secrets): SecretsCollection
    {
        $imported = new SecretsCollection();

        foreach ($env->entries() as $entry) {
            if ($secrets->hasKey($entry->getName()) && $this->option('skip-existing')) {
                continue;
            }

            if ($entry->getValue()->isEmpty()) {
                $this->warn("Skipping key [{$entry->getName()}] with empty value.");
                continue;
            }

            try {
                $imported->push(
                    $secret = $this->vault()->set($entry->getName(), $entry->getValue()->get()->getChars())
                );
                $this->info("Imported key [{$secret->key()}]");
            } catch (KeepException $e) {
                $this->error("Failed to import key [{$entry->getName()}]: ".$e->getMessage());
            }
        }

        return $imported;
    }

    protected function resultsTable(Env $env, SecretsCollection $secrets, ?SecretsCollection $imported): array
    {
        $rows = [];

        foreach ($env->entries() as $entry) {
            $status = 'Skipped';
            if ($imported && $imported->hasKey($entry->getName())) {
                $status = 'Imported';
            } elseif ($secrets->hasKey($entry->getName())) {
                $status = 'Exists';
            }

            $rows[] = [
                'key'     => $entry->getName(),
                'status'  => $status,
                'revision' => $imported && $imported->hasKey($entry->getName())
                    ? $imported->getByKey($entry->getName())->revision()
                    : $secrets->getByKey($entry->getName())?->revision()
            ];
        }

        return $rows;
    }
}
