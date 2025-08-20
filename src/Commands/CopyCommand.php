<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Context;
use STS\Keep\Exceptions\SecretNotFoundException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;

class CopyCommand extends BaseCommand
{
    public $signature = 'copy
        {--from= : Source context in format "vault:stage" or just "stage"}
        {--to= : Destination context in format "vault:stage" or just "stage"}
        {--overwrite : Overwrite destination if it exists}
        {--dry-run : Preview the copy operation without executing it}'
        .self::KEY_SIGNATURE;

    public $description = 'Copy a secret between stages or vaults';

    public function process()
    {
        $key = $this->key();
        
        // Parse source and destination contexts using GathersInput methods
        $sourceContext = Context::fromInput($this->from());
        $destinationContext = Context::fromInput($this->to());

        // Validate contexts are different
        if ($sourceContext->equals($destinationContext)) {
            $this->error('Source and destination are identical. Nothing to copy.');
            return self::FAILURE;
        }

        try {
            // Get source secret
            $sourceVault = $sourceContext->createVault();
            $sourceSecret = $sourceVault->get($key);

            // Check if the destination exists
            $destinationVault = $destinationContext->createVault();
            $destinationExists = $destinationVault->has($key);

            // Handle overwrite protection
            if ($destinationExists && !$this->option('overwrite') && !$this->option('dry-run')) {
                $this->error("Secret [{$key}] already exists in destination. Use --overwrite to replace it.");
                return self::FAILURE;
            }

            // Show preview
            $this->displayCopyPreview($key, $sourceContext, $destinationContext, $sourceSecret, $destinationExists);

            // Handle dry-run
            if ($this->option('dry-run')) {
                $this->info('Dry run completed. No changes made.');
                return self::SUCCESS;
            }

            // Confirm if overwriting
            if ($destinationExists && $this->option('overwrite')) {
                if (!confirm("Are you sure you want to overwrite the existing secret [{$key}]?")) {
                    $this->info('Copy operation cancelled.');
                    return self::SUCCESS;
                }
            }

            // Perform the copy
            $destinationVault->set($key, $sourceSecret->value(), $sourceSecret->isSecure());

            $this->info("Successfully copied secret [{$key}] from {$sourceContext->toString()} to {$destinationContext->toString()}");
        } catch (SecretNotFoundException $e) {
            return $this->error("Source secret [{$key}] not found in {$sourceContext->toString()}");
        }
    }

    protected function displayCopyPreview(string $key, Context $source, Context $destination, $sourceSecret, bool $destinationExists): void
    {
        $this->line("<info>Copy Operation Preview</info>");
        
        table(
            ['Property', 'Value'],
            [
                ['Secret Key', $key],
                ['Source', $source->toString()],
                ['Destination', $destination->toString()],
                ['Source Value', $sourceSecret->isSecure() ? '<masked>' : $sourceSecret->value()],
                ['Security Level', $sourceSecret->isSecure() ? 'Secure' : 'Plain Text'],
                ['Destination Status', $destinationExists ? 'EXISTS (will overwrite)' : 'NEW'],
            ]
        );
    }
}