<?php

namespace STS\Keep\Data;

use STS\Keep\Contracts\KeepRepositoryInterface;
use STS\Keep\Services\SecretsEncryption;

class KeepRepository implements KeepRepositoryInterface
{
    protected array $secrets;

    public function __construct(
        protected string $cacheFilePath,
        protected string $appKey
    ) {
        $this->loadSecrets();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->secrets[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->secrets[$key]);
    }

    public function all(): array
    {
        return $this->secrets;
    }

    protected function loadSecrets(): void
    {
        if (!file_exists($this->cacheFilePath)) {
            $this->secrets = [];
            return;
        }

        try {
            // Load encrypted data from PHP file (leverages OPCache)
            $encryptedData = include $this->cacheFilePath;
            
            // Decrypt secrets
            $this->secrets = SecretsEncryption::decrypt($encryptedData, $this->appKey);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to load secrets from cache file '{$this->cacheFilePath}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}