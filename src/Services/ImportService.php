<?php

namespace STS\Keep\Services;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Env;
use STS\Keep\Data\Secret;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Validation\SecretKeyValidator;
use STS\Keep\Vaults\AbstractVault;

class ImportService
{
    const STRATEGY_OVERWRITE = 'overwrite';
    const STRATEGY_SKIP = 'skip';
    const STRATEGY_FAIL = 'fail';

    const IMPORT_DELAY_MICROSECONDS = 150000;

    protected array $results = [];
    protected array $errors = [];
    
    /**
     * Parse an env file and return the secrets it contains
     */
    public function parseEnvFile(string $filePath): SecretCollection
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new KeepException("Env file [$filePath] does not exist or is not readable.");
        }
        
        $env = Env::fromFile($filePath);
        return $env->secrets();
    }
    
    /**
     * Parse env content directly (useful for web uploads)
     */
    public function parseEnvContent(string $content): SecretCollection
    {
        $env = new Env($content);
        return $env->secrets();
    }
    
    /**
     * Analyze import for conflicts and prepare results
     */
    public function analyzeImport(
        SecretCollection $importSecrets,
        SecretCollection $existingSecrets,
        ?string $only = null,
        ?string $except = null
    ): array {
        // Apply filters
        $filteredSecrets = $importSecrets->filterByPatterns($only, $except);
        
        $analysis = [
            'total' => $filteredSecrets->count(),
            'new' => 0,
            'existing' => 0,
            'invalid' => 0,
            'empty' => 0,
            'secrets' => []
        ];
        
        foreach ($filteredSecrets as $secret) {
            $key = $secret->key();
            $status = 'new';
            $error = null;
            
            // Check for empty value
            if (empty($secret->value())) {
                $status = 'empty';
                $analysis['empty']++;
            }
            // Validate key
            elseif (!$this->isValidKey($key)) {
                $status = 'invalid';
                $error = $this->getKeyValidationError($key);
                $analysis['invalid']++;
            }
            // Check if exists
            elseif ($existingSecrets->hasKey($key)) {
                $status = 'existing';
                $analysis['existing']++;
            } else {
                $analysis['new']++;
            }
            
            $analysis['secrets'][] = [
                'key' => $key,
                'value' => $secret->value(),
                'status' => $status,
                'error' => $error,
                'existing_revision' => $existingSecrets->hasKey($key) 
                    ? $existingSecrets->getByKey($key)?->revision() 
                    : null
            ];
        }
        
        return $analysis;
    }
    
    /**
     * Execute the import with the specified strategy
     */
    public function executeImport(
        SecretCollection $importSecrets,
        AbstractVault $vault,
        string $strategy = self::STRATEGY_SKIP,
        ?string $only = null,
        ?string $except = null,
        bool $dryRun = false
    ): array {
        $this->results = [];
        $this->errors = [];
        
        // Get existing secrets
        $existingSecrets = $vault->list();
        
        // Apply filters
        $filteredSecrets = $importSecrets->filterByPatterns($only, $except);
        
        $imported = new SecretCollection();
        $skipped = new SecretCollection();
        $failed = new SecretCollection();
        
        foreach ($filteredSecrets as $secret) {
            $key = $secret->key();
            $value = $secret->value();
            
            // Skip empty values
            if (empty($value)) {
                $skipped->push($secret);
                $this->results[$key] = ['status' => 'skipped', 'reason' => 'empty_value'];
                continue;
            }
            
            // Validate key
            if (!$this->isValidKey($key)) {
                $failed->push($secret);
                $this->results[$key] = [
                    'status' => 'failed',
                    'reason' => 'invalid_key',
                    'error' => $this->getKeyValidationError($key)
                ];
                $this->errors[] = "Invalid key '$key': " . $this->getKeyValidationError($key);
                continue;
            }
            
            // Handle existing keys based on strategy
            if ($existingSecrets->hasKey($key)) {
                if ($strategy === self::STRATEGY_SKIP) {
                    $skipped->push($secret);
                    $this->results[$key] = ['status' => 'skipped', 'reason' => 'exists'];
                    continue;
                } elseif ($strategy === self::STRATEGY_FAIL) {
                    $failed->push($secret);
                    $this->results[$key] = ['status' => 'failed', 'reason' => 'exists'];
                    $this->errors[] = "Key '$key' already exists";
                    continue;
                }
                // STRATEGY_OVERWRITE continues to import
            }
            
            // Import the secret (unless dry run)
            if (!$dryRun) {
                try {
                    $importedSecret = $vault->set($key, $value);
                    $imported->push($importedSecret);
                    $this->results[$key] = [
                        'status' => 'imported',
                        'revision' => $importedSecret->revision()
                    ];

                    usleep(self::IMPORT_DELAY_MICROSECONDS);
                } catch (KeepException $e) {
                    $failed->push($secret);
                    $this->results[$key] = [
                        'status' => 'failed',
                        'reason' => 'vault_error',
                        'error' => $e->getMessage()
                    ];
                    $this->errors[] = "Failed to import '$key': " . $e->getMessage();
                }
            } else {
                // Dry run - just mark as would be imported
                $imported->push($secret);
                $this->results[$key] = [
                    'status' => 'would_import',
                    'revision' => null
                ];
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $this->results,
            'errors' => $this->errors,
            'dry_run' => $dryRun
        ];
    }
    
    protected function isValidKey(string $key): bool
    {
        return (new SecretKeyValidator())->isValid($key);
    }

    protected function getKeyValidationError(string $key): string
    {
        return (new SecretKeyValidator())->getValidationError($key) ?? 'Invalid key';
    }
    
    /**
     * Get the results of the last import operation
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * Get any errors from the last import operation
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}