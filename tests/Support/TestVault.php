<?php

namespace STS\Keep\Tests\Support;

use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretsCollection;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Vaults\AbstractVault;

class TestVault extends AbstractVault
{
    /**
     * Vault and environment-aware storage structure:
     * [
     *     'vault-name' => [
     *         'environment' => [
     *             'path' => Secret
     *         ]
     *     ]
     * ]
     */
    protected static array $storage = [];
    protected int $revision = 1;

    public function list(): SecretsCollection
    {
        $secrets = $this->getVaultEnvironmentSecrets();
        return new SecretsCollection(collect($secrets)->values());
    }

    public function get(string $key): Secret
    {
        $path = $this->format($key);
        $secrets = $this->getVaultEnvironmentSecrets();
        
        if (!isset($secrets[$path])) {
            throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}].");
        }

        return $secrets[$path];
    }

    public function set(string $key, string $value, bool $secure = true): Secret
    {
        $path = $this->format($key);
        $secrets = $this->getVaultEnvironmentSecrets();
        $revision = isset($secrets[$path]) ? $secrets[$path]->revision() + 1 : 1;
        
        $secret = new Secret($key, $value, null, $secure, $this->environment, $revision, $path, $this);
        $this->setVaultEnvironmentSecret($path, $secret);
        
        return $secret;
    }

    public function save(Secret $secret): Secret
    {
        $this->setVaultEnvironmentSecret($secret->path(), $secret);
        return $secret;
    }

    public function format(?string $key = null): string
    {
        if (!$key) {
            return sprintf('/%s/%s/', $this->config['namespace'] ?? 'test-app', $this->environment);
        }

        $formatter = $this->keyFormatter ?? fn($k) => strtoupper($k);
        $formattedKey = call_user_func($formatter, $key);
        
        return sprintf('/%s/%s/%s', $this->config['namespace'] ?? 'test-app', $this->environment, $formattedKey);
    }

    public function clear(): void
    {
        // Clear only this vault's environment
        if (isset(self::$storage[$this->name()][$this->environment])) {
            self::$storage[$this->name()][$this->environment] = [];
        }
    }

    public function hasSecret(string $key): bool
    {
        $secrets = $this->getVaultEnvironmentSecrets();
        return isset($secrets[$this->format($key)]);
    }

    public function getSharedSecrets(): array
    {
        return $this->getVaultEnvironmentSecrets();
    }

    /**
     * Get secrets for the current vault and environment
     */
    protected function getVaultEnvironmentSecrets(): array
    {
        return self::$storage[$this->name()][$this->environment] ?? [];
    }

    /**
     * Set a secret for the current vault and environment
     */
    protected function setVaultEnvironmentSecret(string $path, Secret $secret): void
    {
        if (!isset(self::$storage[$this->name()])) {
            self::$storage[$this->name()] = [];
        }
        
        if (!isset(self::$storage[$this->name()][$this->environment])) {
            self::$storage[$this->name()][$this->environment] = [];
        }
        
        self::$storage[$this->name()][$this->environment][$path] = $secret;
    }

    /**
     * Clear all secrets from all vaults and environments (for test cleanup)
     */
    public static function clearAll(): void
    {
        self::$storage = [];
    }

    /**
     * Get the full storage array (for debugging)
     */
    public static function getFullStorage(): array
    {
        return self::$storage;
    }
}