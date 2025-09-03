<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;

class CleanupVerifyCommand extends BaseCommand
{
    public $signature = 'cleanup:verify 
        {--vault= : The vault to clean up}
        {--stage= : The stage to clean up}
        {--force : Skip confirmation prompts}';

    public $description = 'Clean up orphaned verification test keys from vaults';

    public function process()
    {
        $context = $this->vaultContext();
        $vault = $context->createVault();
        
        $this->info('Searching for orphaned verify test keys...');
        
        // Get all secrets
        $secrets = $vault->list();
        
        // Filter for verify test keys
        $verifyKeys = $secrets->filter(function($secret) {
            $key = $secret->key();
            return str_starts_with($key, 'keep-verify-') || 
                   str_starts_with($key, '__keep_verify_') ||
                   str_starts_with($key, 'keep_verify_') ||
                   str_starts_with($key, '_keep_verify_');
        });
        
        if ($verifyKeys->isEmpty()) {
            $this->info('No orphaned verify test keys found.');
            return self::SUCCESS;
        }
        
        $this->warn("Found {$verifyKeys->count()} orphaned verify test key(s):");
        
        table(
            ['Key', 'Modified'],
            $verifyKeys->map(function($secret) {
                return [
                    'key' => $secret->key(),
                    'modified' => $secret->lastModified()?->diffForHumans() ?? 'Unknown'
                ];
            })->toArray()
        );
        
        if (!$this->option('force')) {
            $confirmed = confirm(
                "Delete {$verifyKeys->count()} orphaned verify test key(s)?",
                default: false
            );
            
            if (!$confirmed) {
                $this->info('Cleanup cancelled.');
                return self::SUCCESS;
            }
        }
        
        $deleted = 0;
        $failed = 0;
        
        foreach ($verifyKeys as $secret) {
            try {
                $vault->delete($secret->key());
                $this->line("✓ Deleted: {$secret->key()}");
                $deleted++;
            } catch (\Exception $e) {
                $this->error("✗ Failed to delete: {$secret->key()} - {$e->getMessage()}");
                $failed++;
            }
        }
        
        $this->newLine();
        $this->info("Cleanup complete: {$deleted} deleted, {$failed} failed.");
        
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}