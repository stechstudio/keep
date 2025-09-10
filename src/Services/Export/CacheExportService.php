<?php

namespace STS\Keep\Services\Export;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use STS\Keep\Services\Crypt;
use STS\Keep\Services\SecretLoader;
use Symfony\Component\Console\Output\OutputInterface;

class CacheExportService
{
    public function __construct(
        protected SecretLoader $secretLoader,
        protected Filesystem $filesystem
    ) {}

    public function handle(array $options, OutputInterface $output): int
    {
        $env = $options['env'];
        $vaultNames = $this->determineVaults($options);

        if (count($vaultNames) === 1) {
            $output->writeln("<info>Loading secrets from vault '{$vaultNames[0]}' for environment '{$env}'...</info>");
        } else {
            $output->writeln('<info>Loading secrets from '.count($vaultNames).' vaults ('.implode(', ', $vaultNames).") for environment '{$env}'...</info>");
        }

        $allSecrets = $this->secretLoader->loadFromVaults($vaultNames, $env);

        // Apply filters
        $allSecrets = $allSecrets->filterByPatterns(
            only: $options['only'] ?? null,
            except: $options['except'] ?? null
        );

        $output->writeln('<info>Found '.$allSecrets->count().' total secrets to cache</info>');

        $keyPart = Str::random(32);
        $this->writeCacheFile($env, $allSecrets->toKeyValuePair()->toArray(), $keyPart, $output);
        $this->saveKeyPartToEnv($keyPart, $output);

        return 0;
    }

    protected function determineVaults(array $options): array
    {
        // If --vault explicitly specified, use those
        if (isset($options['vault']) && $options['vault']) {
            return array_map('trim', explode(',', $options['vault']));
        }

        // Otherwise, use ALL configured vaults
        return $this->secretLoader->getAllVaultNames();
    }

    protected function writeCacheFile(string $env, array $secrets, string $keyPart, OutputInterface $output): void
    {
        $outputPath = getcwd()."/.keep/cache/{$env}.keep.php";

        $encryptedData = (new Crypt($keyPart))->encrypt($secrets);
        $phpContent = "<?php\n\nreturn ".var_export($encryptedData, true).';';

        // Ensure .keep/cache directory exists with .gitignore
        $cacheDir = getcwd().'/.keep/cache';
        if (! $this->filesystem->exists($cacheDir)) {
            $this->filesystem->makeDirectory($cacheDir, 0755, true);
        }

        if (! $this->filesystem->isFile($cacheDir.'/.gitignore')) {
            $this->filesystem->put($cacheDir.'/.gitignore', "*\n!.gitignore\n");
            $this->filesystem->chmod($cacheDir.'/.gitignore', 0644);
        }

        $this->filesystem->put($outputPath, $phpContent);
        $this->filesystem->chmod($outputPath, 0600);

        $output->writeln("<info>Secrets cached successfully to {$outputPath}</info>");
    }

    protected function saveKeyPartToEnv(string $keyPart, OutputInterface $output): void
    {
        $envPath = getcwd().'/.env';

        // Remove existing KEEP_CACHE_KEY_PART if present
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            $content = preg_replace('/^KEEP_CACHE_KEY_PART=.*$/m', '', $content);
            $content = rtrim($content);
        } else {
            $content = '';
        }

        // Append new key part
        $content .= ($content ? "\n" : '')."KEEP_CACHE_KEY_PART={$keyPart}\n";

        $this->filesystem->put($envPath, $content);
        $this->filesystem->chmod($envPath, 0600);

        $output->writeln('<info>Updated .env file with KEEP_CACHE_KEY_PART</info>');
    }
}
