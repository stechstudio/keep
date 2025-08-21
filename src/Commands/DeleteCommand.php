<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;

class DeleteCommand extends BaseCommand
{
    public $signature = 'delete {--force : Skip confirmation prompt} '
        .self::KEY_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Delete a stage secret from a specified vault';

    public function process()
    {
        $key = $this->key();
        $context = $this->context();
        $vault = $context->createVault();

        // Get the secret first to verify it exists
        $secret = $vault->get($key);

        // Show secret details
        $this->newLine();
        $this->info('Secret to be deleted:');
        table(['Key', 'Stage', 'Vault'], [
            [$secret->key(), $context->stage, $context->vault],
        ]);

        // Confirmation prompt (unless --force is used)
        if (! $this->option('force')) {
            $confirmed = confirm(
                label: 'Are you sure you want to permanently delete this secret?',
                default: false,
                hint: 'This action cannot be undone'
            );

            if (! $confirmed) {
                return $this->info('Secret deletion cancelled.');
            }
        }

        // Delete the secret
        $vault->delete($key);

        $this->newLine();
        $this->info("Secret [{$key}] has been permanently deleted from vault [{$context->vault}] in stage [{$context->stage}].");
    }
}
