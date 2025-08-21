<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\ConfiguresVaults;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Env;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\SecretsEncryption;

class CacheCommand extends BaseCommand
{
    use ConfiguresVaults;

    protected $signature = 'cache:load 
                            {--stage= : The stage to load secrets for}
                            {--vault= : The vault(s) to load secrets from (comma-separated, defaults to all configured vaults)}
                            {--key= : Laravel APP_KEY for encryption (auto-discovered if not provided)}';

    protected $description = 'Load secrets from vault(s) into encrypted cache file for Laravel integration';

    protected function process()
    {
        $stage = $this->stage();
        $vaultNames = $this->getVaultNames();
        $outputPath = getcwd() . "/storage/cache/{$stage}.keep.php";

        if (count($vaultNames) === 1) {
            $this->info("Loading secrets from vault '{$vaultNames[0]}' for stage '{$stage}'...");
        } else {
            $this->info("Loading secrets from " . count($vaultNames) . " vaults (" . implode(', ', $vaultNames) . ") for stage '{$stage}'...");
        }

        $allSecrets = $this->loadSecretsFromVaults($vaultNames, $stage);
        $appKey = $this->getAppKey($allSecrets);

        $this->info("Found " . $allSecrets->count() . " total secrets to cache");

        // Encrypt and write cache file
        $this->writeCacheFile($allSecrets->toKeyValuePair()->toArray(), $appKey, $outputPath);
        
        $this->info("Secrets cached successfully to {$outputPath}");

        return self::SUCCESS;
    }

    protected function getAppKey(SecretCollection $secretsCollection): string
    {
        // Check CLI option first
        if ($appKey = $this->option('key')) {
            return $appKey;
        }

        // Try to get APP_KEY from loaded secrets
        if ($secretsCollection->hasKey('APP_KEY')) {
            return $secretsCollection->getByKey('APP_KEY')->value();
        }

        // Try to load from .env file using Dotenv
        if ($appKey = $this->loadAppKeyFromEnv()) {
            return $appKey;
        }
        
        throw new \InvalidArgumentException(
            'APP_KEY not found. Please provide --key option or ensure APP_KEY is available in .env file.'
        );
    }

    protected function loadAppKeyFromEnv(): ?string
    {
        try {
            $env = Env::fromFile(getcwd() . '/.env');
            $appKey = $env->get('APP_KEY');
            
            return !empty($appKey) ? $appKey : null;
        } catch (\Exception) {
            return null;
        }
    }

    protected function getVaultNames(): array
    {
        // If --vault specified, use those (comma-separated)
        if ($vault = $this->option('vault')) {
            return array_map('trim', explode(',', $vault));
        }

        // Otherwise, use ALL configured vaults
        return Keep::getConfiguredVaults()->keys()->toArray();
    }

    protected function loadSecretsFromVaults(array $vaultNames, string $stage): SecretCollection
    {
        $allSecrets = new SecretCollection();

        foreach ($vaultNames as $vaultName) {
            $vault = Keep::vault($vaultName, $stage);
            $vaultSecrets = $vault->list();
            
            // Merge secrets, later vaults override earlier ones for duplicate keys
            $allSecrets = $allSecrets->merge($vaultSecrets);
        }

        return $allSecrets;
    }

    protected function writeCacheFile(array $secrets, string $appKey, string $outputPath): void
    {
        // Encrypt the secrets
        $encryptedData = SecretsEncryption::encrypt($secrets, $appKey);
        
        // Generate PHP file content that returns the encrypted data
        $phpContent = "<?php\n\nreturn " . var_export($encryptedData, true) . ";";
        
        // Write file using trait (handles directory creation and permissions)
        $this->writeToFile($outputPath, $phpContent, true, false, 0600);
    }
}