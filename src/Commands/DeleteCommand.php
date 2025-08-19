<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;

class DeleteCommand extends AbstractCommand
{
    public $signature = 'keep:delete {--force : Skip confirmation prompt} '
        .self::KEY_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Delete a stage secret from a specified vault';

    public function process(): int
    {
        $key = $this->key();
        $vaultName = $this->vaultName();
        $stage = $this->stage();

        // Get the secret first to verify it exists
        $secret = $this->vault()->get($key);

        // Show secret details
        $this->newLine();
        $this->info('Secret to be deleted:');
        table(['Key', 'Stage', 'Vault'], [
            [$secret->key(), $stage, $vaultName],
        ]);

        // Confirmation prompt (unless --force is used)
        if (! $this->option('force')) {
            $confirmed = confirm(
                label: 'Are you sure you want to permanently delete this secret?',
                default: false,
                hint: 'This action cannot be undone'
            );

            if (! $confirmed) {
                $this->info('Secret deletion cancelled.');

                return self::SUCCESS;
            }
        }

        // Delete the secret
        $this->vault()->delete($key);

        $this->newLine();
        $this->info("Secret [{$key}] has been permanently deleted from vault [{$vaultName}] in stage [{$stage}].");

        return self::SUCCESS;
    }
}
