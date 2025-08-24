<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Str;
use STS\Keep\Commands\Concerns\ConfiguresVaults;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Env;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\Crypt;

class CacheCommand extends BaseCommand
{
    use ConfiguresVaults;

    protected $signature = 'cache 
                            {--stage= : The stage to load secrets for}
                            {--vault= : The vault(s) to load secrets from (comma-separated, defaults to all configured vaults)}';

    protected $description = 'Load secrets from vault(s) into encrypted cache file for Laravel integration';

    protected function process()
    {
        $stage = $this->stage();
        $vaultNames = $this->getVaultNames();

        if (count($vaultNames) === 1) {
            $this->info("Loading secrets from vault '{$vaultNames[0]}' for stage '{$stage}'...");
        } else {
            $this->info("Loading secrets from " . count($vaultNames) . " vaults (" . implode(', ', $vaultNames) . ") for stage '{$stage}'...");
        }

        $allSecrets = $this->loadSecretsFromVaults($vaultNames, $stage);

        $this->info("Found " . $allSecrets->count() . " total secrets to cache");

        $keyPart = Str::random(32);
        $this->writeCacheFile($stage, $allSecrets->toKeyValuePair()->toArray(), $keyPart);
        $this->saveKeyPartToEnv($keyPart);

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

    protected function writeCacheFile(string $stage, array $secrets, $keyPart): void
    {
        $outputPath = getcwd() . "/.keep/cache/{$stage}.keep.php";

        $encryptedData = (new Crypt($keyPart))->encrypt($secrets);
        $phpContent = "<?php\n\nreturn " . var_export($encryptedData, true) . ";";

        if(!$this->filesystem->isFile(getcwd() . "/.keep/cache/.gitignore")) {
            $this->writeToFile(getcwd() . "/.keep/cache/.gitignore", "*\n!.gitignore\n", true, false, 0644);
        }

        $this->writeToFile($outputPath, $phpContent, true, false, 0600);

        $this->info("Secrets cached successfully to {$outputPath}");
    }

    protected function saveKeyPartToEnv(string $keyPart): void
    {
        $envPath = getcwd() . '/.env';
        
        // Remove existing KEEP_CACHE_KEY_PART if present
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            $content = preg_replace('/^KEEP_CACHE_KEY_PART=.*$/m', '', $content);
            $content = rtrim($content);
        } else {
            $content = '';
        }
        
        // Append new key part
        $content .= ($content ? "\n" : '') . "KEEP_CACHE_KEY_PART={$keyPart}\n";
        
        $this->filesystem->put($envPath, $content);
        $this->filesystem->chmod($envPath, 0600);

        $this->info("Updated .env file with KEEP_CACHE_KEY_PART");
    }
}