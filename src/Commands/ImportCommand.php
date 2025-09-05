<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Services\ImportService;
use STS\Keep\Exceptions\KeepException;

use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class ImportCommand extends BaseCommand
{
    public $signature = 'import 
        {from? : Env file to import from}
        {--overwrite : Overwrite existing secrets} 
        {--skip-existing : Skip existing secrets}  
        {--dry-run : Show what would be imported without actually importing}
        {--force : Skip confirmation prompts for automation} 
        {--only= : Only include keys matching this pattern (e.g. DB_*)} 
        {--except= : Exclude keys matching this pattern (e.g. MAIL_*)}
        {--vault= : The vault to use}
        {--stage= : The stage to use}';

    public $description = 'Import a .env file and store as stage secrets in a specified vault';

    public function process()
    {
        if ($this->option('overwrite') && $this->option('skip-existing')) {
            $this->error('You cannot use --overwrite and --skip-existing together.');

            return self::FAILURE;
        }

        $envFilePath = $this->argument('from') ?? text('Path to .env file', required: true);
        
        // Use ImportService for parsing and importing
        $importService = new ImportService();
        
        try {
            $importSecrets = $importService->parseEnvFile($envFilePath);
        } catch (KeepException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $context = $this->vaultContext();
        $vault = $context->createVault();
        $vaultSecrets = $vault->list();

        // Determine strategy based on options
        $strategy = ImportService::STRATEGY_FAIL;
        if ($this->option('overwrite')) {
            $strategy = ImportService::STRATEGY_OVERWRITE;
        } elseif ($this->option('skip-existing')) {
            $strategy = ImportService::STRATEGY_SKIP;
        }
        
        // Check for conflicts first
        $analysis = $importService->analyzeImport(
            $importSecrets, 
            $vaultSecrets,
            $this->option('only'),
            $this->option('except')
        );
        
        // Show warnings if there are conflicts and no strategy specified
        if ($analysis['existing'] > 0 && $strategy === ImportService::STRATEGY_FAIL) {
            $existingKeys = collect($analysis['secrets'])
                ->where('status', 'existing')
                ->pluck('key');
            $this->error('The following keys already exist: '.$existingKeys->implode(', '));
            $this->line('Use --overwrite to overwrite existing keys, or --skip-existing to skip them.');
            return self::FAILURE;
        }
        
        // Show warnings for other strategies
        if ($analysis['existing'] > 0 && $strategy === ImportService::STRATEGY_OVERWRITE) {
            $existingKeys = collect($analysis['secrets'])
                ->where('status', 'existing')
                ->pluck('key');
            $this->warn('The following keys already exist and will be overwritten: '.$existingKeys->implode(', '));
        } elseif ($analysis['existing'] > 0 && $strategy === ImportService::STRATEGY_SKIP) {
            $existingKeys = collect($analysis['secrets'])
                ->where('status', 'existing')
                ->pluck('key');
            $this->warn('The following keys already exist and will be skipped: '.$existingKeys->implode(', '));
        }
        
        // Execute import
        $result = $importService->executeImport(
            $importSecrets,
            $vault,
            $strategy,
            $this->option('only'),
            $this->option('except'),
            $this->option('dry-run')
        );
        
        // Display results
        table(['Key', 'Status', 'Rev'], $this->resultsTable($result));
        
        // Show errors if any
        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if ($this->option('dry-run')) {
            $this->info('This was a dry run. No secrets were imported.');
        } else {
            $this->success(sprintf('Imported %d secrets', $result['imported']->count()));
        }
    }


    protected function resultsTable(array $result): array
    {
        $rows = [];
        
        foreach ($result['results'] as $key => $info) {
            $status = match($info['status']) {
                'imported' => 'Imported',
                'would_import' => 'Would Import',
                'skipped' => match($info['reason'] ?? '') {
                    'empty_value' => 'Skipped (empty)',
                    'exists' => 'Skipped (exists)',
                    default => 'Skipped'
                },
                'failed' => match($info['reason'] ?? '') {
                    'invalid_key' => 'Failed (invalid)',
                    'exists' => 'Failed (exists)',
                    'vault_error' => 'Failed (error)',
                    default => 'Failed'
                },
                default => 'Unknown'
            };
            
            $rows[] = [
                'key' => $key,
                'status' => $status,
                'revision' => $info['revision'] ?? null,
            ];
        }

        return $rows;
    }
}
