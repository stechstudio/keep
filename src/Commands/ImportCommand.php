<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Env;
use STS\Keep\Exceptions\KeepException;

use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class ImportCommand extends BaseCommand
{
    public $signature = 'import {from? : Env file to import from}
        {--overwrite : Overwrite existing secrets} 
        {--skip-existing : Skip existing secrets}  
        {--dry-run : Show what would be imported without actually importing}
        {--force : Skip confirmation prompts for automation} '
        .self::ONLY_EXCLUDE_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Import a .env file and store as stage secrets in a specified vault';

    public function process()
    {
        if ($this->option('overwrite') && $this->option('skip-existing')) {
            return $this->error('You cannot use --overwrite and --skip-existing together.');
        }

        $envFilePath = $this->argument('from') ?? text('Path to .env file', required: true);
        if (! file_exists($envFilePath) || ! is_readable($envFilePath)) {
            return $this->error("Env file [$envFilePath] does not exist or is not readable.");
        }

        $env = Env::fromFile($envFilePath);

        // Convert env entries to secrets and apply filtering
        $importSecrets = $env->secrets()->filterByPatterns(
            only: $this->option('only'),
            except: $this->option('except')
        );

        $context = $this->context();
        $vault = $context->createVault();
        $vaultSecrets = $vault->list();

        if (! $this->canImport($importSecrets, $vaultSecrets)) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $imported = null;
        } else {
            $imported = $this->runImport($importSecrets, $vaultSecrets, $vault);
        }

        table(['Key', 'Status', 'Rev'], $this->resultsTable($importSecrets, $vaultSecrets, $imported));

        if ($this->option('dry-run')) {
            $this->info('This was a dry run. No secrets were imported.');
        }
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

    protected function runImport(SecretCollection $importSecrets, SecretCollection $vaultSecrets, $vault): SecretCollection
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

            // Validate key before importing to ensure it meets Keep's standards
            try {
                $this->validateUserKey($secret->key());
            } catch (\InvalidArgumentException $e) {
                $this->error("Skipping invalid key [{$secret->key()}]: " . $e->getMessage());
                continue;
            }

            try {
                $imported->push(
                    $importedSecret = $vault->set($secret->key(), $secret->value())
                );
                $this->info("Imported key [{$importedSecret->key()}]");
                usleep(150000); // Slight delay to avoid rate limits
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
    
    /**
     * Validate a user-provided key for safe vault operations.
     * More permissive than .env requirements to support various use cases.
     */
    protected function validateUserKey(string $key): void
    {
        $trimmed = trim($key);

        // Allow letters, digits, underscores, and hyphens (common in cloud services)
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $trimmed)) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' contains invalid characters. ".
                'Only letters, numbers, underscores, and hyphens are allowed.'
            );
        }

        // Length validation (reasonable limits for secret names)
        if (strlen($trimmed) < 1 || strlen($trimmed) > 255) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' must be 1-255 characters long."
            );
        }

        // Cannot start with hyphen (could be interpreted as command flag)
        if (str_starts_with($trimmed, '-')) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' cannot start with hyphen."
            );
        }
    }
}
