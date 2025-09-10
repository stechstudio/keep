<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\confirm;

class RenameCommand extends BaseCommand
{
    public $signature = 'rename 
        {old : Current secret key name}
        {new : New secret key name}
        {--force : Skip confirmation prompt} 
        {--vault= : The vault to use}
        {--env= : The environment to use}';

    public $description = 'Rename a secret while preserving its value and metadata';

    public function process()
    {
        $oldKey = $this->argument('old');
        $newKey = $this->argument('new');
        $context = $this->vaultContext();
        $vault = $context->createVault();

        // Show what will happen
        $this->newLine();
        $this->line('Rename operation:');
        $this->line(sprintf('  From: <secret-name>%s</secret-name>', $oldKey));
        $this->line(sprintf('  To:   <secret-name>%s</secret-name>', $newKey));
        $this->line(sprintf('  Context: <context>%s:%s</context>', $context->vault, $context->env));
        $this->newLine();

        // Confirm unless forced
        if (!$this->option('force')) {
            $confirmed = confirm(
                label: 'Proceed with rename?',
                default: true,
                hint: 'The old key will be deleted after copying'
            );

            if (!$confirmed) {
                $this->neutral('Rename operation cancelled.');
                return self::SUCCESS;
            }
        }

        // Perform the rename
        // Note: The vault's rename method handles the implementation details.
        // For AWS SSM and Secrets Manager, this uses copy + delete since they don't support native rename.
        try {
            $vault->rename($oldKey, $newKey);

            $this->success(sprintf('Renamed [<secret-name>%s</secret-name>] to [<secret-name>%s</secret-name>] in <context>%s:%s</context>',
                $oldKey,
                $newKey,
                $context->vault,
                $context->env
            ));

            return self::SUCCESS;
        } catch (\STS\Keep\Exceptions\SecretNotFoundException $e) {
            $this->error(sprintf('Secret [<secret-name>%s</secret-name>] not found in <context>%s:%s</context>',
                $oldKey,
                $context->vault,
                $context->env
            ));
            return self::FAILURE;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                $this->error(sprintf('Secret [<secret-name>%s</secret-name>] already exists in <context>%s:%s</context>',
                    $newKey,
                    $context->vault,
                    $context->env
                ));
                $this->neutral('Use a different name or delete the existing secret first.');
            } else {
                $this->error('Failed to rename secret: ' . $e->getMessage());
                
                // Try to clean up if we created the new one but failed to delete old
                if ($vault->has($newKey) && $vault->has($oldKey)) {
                    $this->warn('Partial rename occurred. New secret was created but old one still exists.');
                    $this->neutral('You may want to manually delete one of them.');
                }
            }
            
            return self::FAILURE;
        }
    }
}