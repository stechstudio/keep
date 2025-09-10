<?php

namespace STS\Keep\Tests\Support;

use Carbon\Carbon;
use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretHistory;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Vaults\AbstractVault;

class TestVault extends AbstractVault
{
    public const string DRIVER = 'test';

    public static function configure(array $existingSettings = []): array
    {
        // Return empty array for test vault since it doesn't need configuration
        return [];
    }

    /**
     * Vault and env-aware storage structure:
     * [
     *     'vault-name' => [
     *         'env' => [
     *             'path' => Secret
     *         ]
     *     ]
     * ]
     */
    protected static array $storage = [];

    /**
     * History storage structure:
     * [
     *     'vault-name' => [
     *         'env' => [
     *             'path' => [SecretHistory] // Array of history entries
     *         ]
     *     ]
     * ]
     */
    protected static array $history = [];

    protected int $revision = 1;

    public function list(): SecretCollection
    {
        $secrets = $this->getVaultStageSecrets();

        return new SecretCollection(collect($secrets)->values());
    }

    public function all(): SecretCollection
    {
        // Same as list() for TestVault
        return $this->list();
    }

    public function get(string $key): Secret
    {
        $path = $this->format($key);
        $secrets = $this->getVaultStageSecrets();

        if (! isset($secrets[$path])) {
            throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}].");
        }

        return $secrets[$path];
    }

    public function set(string $key, string $value, bool $secure = true): Secret
    {
        $path = $this->format($key);
        $secrets = $this->getVaultStageSecrets();
        $revision = isset($secrets[$path]) ? $secrets[$path]->revision() + 1 : 1;

        $secret = Secret::fromUser($key, $value, null, $secure, $this->env, $revision, $path, $this);
        $this->setVaultStageSecret($path, $secret);

        // Add to history
        $this->addToHistory($path, $secret);

        return $secret;
    }

    public function save(Secret $secret): Secret
    {
        $this->setVaultStageSecret($secret->path(), $secret);

        return $secret;
    }

    public function delete(string $key): bool
    {
        $path = $this->format($key);
        $secrets = $this->getVaultStageSecrets();

        if (! isset($secrets[$path])) {
            throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}]");
        }

        unset(self::$storage[$this->name()][$this->env][$path]);

        return true;
    }

    public function format(?string $key = null): string
    {
        if (! $key) {
            return sprintf('/%s/%s/', $this->config['namespace'] ?? 'test-app', $this->env);
        }

        $formattedKey = strtoupper($key);

        return sprintf('/%s/%s/%s', $this->config['namespace'] ?? 'test-app', $this->env, $formattedKey);
    }

    public function clear(): void
    {
        // Clear only this vault's env
        if (isset(self::$storage[$this->name()][$this->env])) {
            self::$storage[$this->name()][$this->env] = [];
        }
    }

    public function has(string $key): bool
    {
        $secrets = $this->getVaultStageSecrets();

        return isset($secrets[$this->format($key)]);
    }

    public function getSharedSecrets(): array
    {
        return $this->getVaultStageSecrets();
    }

    /**
     * Get secrets for the current vault and env
     */
    protected function getVaultStageSecrets(): array
    {
        return self::$storage[$this->name()][$this->env] ?? [];
    }

    /**
     * Set a secret for the current vault and env
     */
    protected function setVaultStageSecret(string $path, Secret $secret): void
    {
        if (! isset(self::$storage[$this->name()])) {
            self::$storage[$this->name()] = [];
        }

        if (! isset(self::$storage[$this->name()][$this->env])) {
            self::$storage[$this->name()][$this->env] = [];
        }

        self::$storage[$this->name()][$this->env][$path] = $secret;
    }

    public function history(string $key, FilterCollection $filters, ?int $limit = 10): SecretHistoryCollection
    {
        $path = $this->format($key);
        $historyEntries = $this->getVaultStageHistory($path);

        if (empty($historyEntries)) {
            throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}]");
        }

        $collection = new SecretHistoryCollection($historyEntries);

        // Apply filters first, then sort
        $filtered = $collection->applyFilters($filters)->sortByVersionDesc();

        // Apply limit if specified
        return $limit !== null ? $filtered->take($limit) : $filtered;
    }

    /**
     * Add a secret to history
     */
    protected function addToHistory(string $path, Secret $secret): void
    {
        if (! isset(self::$history[$this->name()])) {
            self::$history[$this->name()] = [];
        }

        if (! isset(self::$history[$this->name()][$this->env])) {
            self::$history[$this->name()][$this->env] = [];
        }

        if (! isset(self::$history[$this->name()][$this->env][$path])) {
            self::$history[$this->name()][$this->env][$path] = [];
        }

        $historyEntry = new SecretHistory(
            key: $secret->key(),
            value: $secret->value(),
            version: $secret->revision(),
            lastModifiedDate: Carbon::now(),
            lastModifiedUser: 'test-user',
            dataType: 'text',
            labels: [],
            policies: null,
            description: null,
            secure: $secret->isSecure(),
        );

        self::$history[$this->name()][$this->env][$path][] = $historyEntry;
    }

    /**
     * Get history entries for a specific path
     */
    protected function getVaultStageHistory(string $path): array
    {
        return self::$history[$this->name()][$this->env][$path] ?? [];
    }

    /**
     * Clear all secrets from all vaults and envs (for test cleanup)
     */
    public static function clearAll(): void
    {
        self::$storage = [];
        self::$history = [];
    }

    /**
     * Get the full storage array (for debugging)
     */
    public static function getFullStorage(): array
    {
        return self::$storage;
    }

    /**
     * Get the full history array (for debugging)
     */
    public static function getFullHistory(): array
    {
        return self::$history;
    }
}
