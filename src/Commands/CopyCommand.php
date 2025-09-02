<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Context;
use STS\Keep\Exceptions\SecretNotFoundException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;

class CopyCommand extends BaseCommand
{
    public $signature = 'copy
        {key? : The secret key (omit when using --only or --except)}
        {--from= : Source context in format "vault:stage" or just "stage"}
        {--to= : Destination context in format "vault:stage" or just "stage"}
        {--overwrite : Overwrite destination if it exists}
        {--dry-run : Preview the copy operation without executing it}
        {--only= : Only include keys matching this pattern (e.g. DB_*)} 
        {--except= : Exclude keys matching this pattern (e.g. MAIL_*)}';

    public $description = 'Copy secrets between stages or vaults (supports patterns with --only/--except)';

    public function process()
    {
        // Determine operation mode
        $key = $this->argument('key');
        $hasPatterns = $this->option('only') || $this->option('except');

        // Validate input
        if (! $key && ! $hasPatterns) {
            $this->error('Either provide a key or use --only/--except patterns.');

            return self::FAILURE;
        }

        if ($key && $hasPatterns) {
            $this->error('Cannot specify both a key and --only/--except patterns.');

            return self::FAILURE;
        }

        // Parse source and destination contexts
        $sourceContext = Context::fromInput($this->from());
        $destinationContext = Context::fromInput($this->to());

        // Validate contexts are different
        if ($sourceContext->equals($destinationContext)) {
            $this->error('Source and destination are identical. Nothing to copy.');

            return self::FAILURE;
        }

        // Route to appropriate handler
        if ($key) {
            return $this->copySingleSecret($key, $sourceContext, $destinationContext);
        } else {
            return $this->copyBulkSecrets($sourceContext, $destinationContext);
        }
    }

    protected function copySingleSecret(string $key, Context $sourceContext, Context $destinationContext): int
    {
        try {
            // Get source secret
            $sourceVault = $sourceContext->createVault();
            $sourceSecret = $sourceVault->get($key);

            // Check if the destination exists
            $destinationVault = $destinationContext->createVault();
            $destinationExists = $destinationVault->has($key);

            // Handle overwrite protection
            if ($destinationExists && ! $this->option('overwrite') && ! $this->option('dry-run')) {
                $this->error(sprintf('Secret [%s] already exists in destination. Use --overwrite to replace it.', $key));

                return self::FAILURE;
            }

            // Show preview
            $this->displaySingleCopyPreview($key, $sourceContext, $destinationContext, $sourceSecret, $destinationExists);

            // Handle dry-run
            if ($this->option('dry-run')) {
                $this->info('Dry run completed. No changes made.');

                return self::SUCCESS;
            }

            // Confirm if overwriting
            if ($destinationExists && $this->option('overwrite')) {
                if (! confirm("Are you sure you want to overwrite the existing secret [{$key}]?")) {
                    $this->neutral('Copy operation cancelled.');

                    return self::SUCCESS;
                }
            }

            // Perform the copy
            $destinationVault->set($key, $sourceSecret->value(), $sourceSecret->isSecure());

            $this->success(sprintf('Copied secret [<secret-name>%s</secret-name>] from <context>%s</context> to <context>%s</context>',
                $key,
                $sourceContext->toString(),
                $destinationContext->toString()
            ));

            return self::SUCCESS;

        } catch (SecretNotFoundException $e) {
            $this->error(sprintf('Source secret [<secret-name>%s</secret-name>] not found in <context>%s</context>',
                $key,
                $sourceContext->toString()
            ));

            return self::FAILURE;
        }
    }

    protected function copyBulkSecrets(Context $sourceContext, Context $destinationContext): int
    {
        // Get filtered secrets from source
        $secretsToCopy = $this->getFilteredSecrets($sourceContext);

        if ($secretsToCopy->isEmpty()) {
            $this->warn('No secrets match the specified patterns.');

            return self::SUCCESS;
        }

        // Analyze what will be copied
        $copyOperations = $this->analyzeCopyOperations($secretsToCopy, $destinationContext);

        // Display preview
        $this->displayBulkCopyPreview($copyOperations, $sourceContext, $destinationContext);

        // Handle dry-run
        if ($this->option('dry-run')) {
            $this->info('Dry run completed. No changes made.');

            return self::SUCCESS;
        }

        // Validate overwrite permission
        $overwriteCount = collect($copyOperations)->where('exists', true)->count();
        if ($overwriteCount > 0 && ! $this->option('overwrite')) {
            $this->error("{$overwriteCount} secret(s) already exist in destination. Use --overwrite to replace them.");

            return self::FAILURE;
        }

        // Confirm operation
        if (! $this->confirmBulkCopy($copyOperations, $sourceContext, $destinationContext)) {
            $this->neutral('Bulk copy operation cancelled.');

            return self::SUCCESS;
        }

        // Execute the copy
        return $this->executeBulkCopy($secretsToCopy, $destinationContext, $sourceContext);
    }

    protected function getFilteredSecrets(Context $sourceContext)
    {
        $sourceVault = $sourceContext->createVault();
        $allSecrets = $sourceVault->list();

        return $allSecrets->filterByPatterns(
            $this->option('only'),
            $this->option('except')
        );
    }

    protected function analyzeCopyOperations($secretsToCopy, Context $destinationContext): array
    {
        $destinationVault = $destinationContext->createVault();
        $operations = [];

        foreach ($secretsToCopy as $secret) {
            $operations[] = [
                'key' => $secret->key(),
                'exists' => $destinationVault->has($secret->key()),
                'secure' => $secret->isSecure(),
            ];
        }

        return $operations;
    }

    protected function confirmBulkCopy(array $operations, Context $source, Context $destination): bool
    {
        $totalCount = count($operations);
        $overwriteCount = collect($operations)->where('exists', true)->count();

        $message = $overwriteCount > 0
            ? "Copy {$totalCount} secret(s) from {$source->toString()} to {$destination->toString()}? ({$overwriteCount} will be overwritten)"
            : "Copy {$totalCount} secret(s) from {$source->toString()} to {$destination->toString()}?";

        return confirm($message);
    }

    protected function executeBulkCopy($secretsToCopy, Context $destinationContext, Context $sourceContext): int
    {
        $destinationVault = $destinationContext->createVault();
        $totalCount = $secretsToCopy->count();
        $successCount = 0;
        $errorCount = 0;

        $this->info("Copying {$totalCount} secret(s)...");
        $this->newLine();

        foreach ($secretsToCopy as $secret) {
            try {
                $destinationVault->set($secret->key(), $secret->value(), $secret->isSecure());
                $this->line("  ✓ {$secret->key()}");
                $successCount++;
            } catch (\Exception $e) {
                $this->line("  ✗ {$secret->key()} - {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->newLine();

        // Report results
        if ($errorCount === 0) {
            $this->success(sprintf('Copied %d secret(s) from <context>%s</context> to <context>%s</context>',
                $successCount,
                $sourceContext->toString(),
                $destinationContext->toString()
            ));

            return self::SUCCESS;
        }

        if ($successCount === 0) {
            $this->error("Failed to copy any secrets. {$errorCount} error(s) occurred.");

            return self::FAILURE;
        }

        $this->warn("Copied {$successCount} secret(s) with {$errorCount} error(s)");

        return self::SUCCESS;
    }

    protected function displaySingleCopyPreview(string $key, Context $source, Context $destination, $sourceSecret, bool $destinationExists): void
    {
        $this->line('<info>Copy Operation Preview</info>');

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

    protected function displayBulkCopyPreview(array $operations, Context $source, Context $destination): void
    {
        $this->line('<info>Bulk Copy Operation Preview</info>');
        $this->newLine();

        // Summary info
        $totalCount = count($operations);
        $overwriteCount = collect($operations)->where('exists', true)->count();
        $newCount = $totalCount - $overwriteCount;

        $this->line("Source: <comment>{$source->toString()}</comment>");
        $this->line("Destination: <comment>{$destination->toString()}</comment>");
        $this->line("Total secrets: <comment>{$totalCount}</comment>");
        $this->line("New secrets: <comment>{$newCount}</comment>");
        $this->line("Overwrites: <comment>{$overwriteCount}</comment>");
        $this->newLine();

        // Show list of secrets
        $this->line('Secrets to copy:');
        $rows = [];
        foreach ($operations as $op) {
            $rows[] = [
                $op['key'],
                $op['secure'] ? 'Secure' : 'Plain',
                $op['exists'] ? 'Overwrite' : 'New',
            ];
        }

        table(['Key', 'Type', 'Action'], $rows);
    }
}
