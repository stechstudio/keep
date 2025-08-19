<?php

namespace STS\Keep\Tests\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretHistory;
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

    /**
     * History storage structure:
     * [
     *     'vault-name' => [
     *         'environment' => [
     *             'path' => [SecretHistory] // Array of history entries
     *         ]
     *     ]
     * ]
     */
    protected static array $history = [];

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

        if (! isset($secrets[$path])) {
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

        // Add to history
        $this->addToHistory($path, $secret);

        return $secret;
    }

    public function save(Secret $secret): Secret
    {
        $this->setVaultEnvironmentSecret($secret->path(), $secret);

        return $secret;
    }

    public function delete(string $key): bool
    {
        $path = $this->format($key);
        $secrets = $this->getVaultEnvironmentSecrets();

        if (! isset($secrets[$path])) {
            throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}]");
        }

        unset(self::$storage[$this->name()][$this->environment][$path]);

        return true;
    }

    public function format(?string $key = null): string
    {
        if (! $key) {
            return sprintf('/%s/%s/', $this->config['namespace'] ?? 'test-app', $this->environment);
        }

        $formatter = $this->keyFormatter ?? fn ($k) => strtoupper($k);
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
        if (! isset(self::$storage[$this->name()])) {
            self::$storage[$this->name()] = [];
        }

        if (! isset(self::$storage[$this->name()][$this->environment])) {
            self::$storage[$this->name()][$this->environment] = [];
        }

        self::$storage[$this->name()][$this->environment][$path] = $secret;
    }

    public function history(string $key, int $limit = 10): Collection
    {
        $path = $this->format($key);
        $historyEntries = $this->getVaultEnvironmentHistory($path);

        if (empty($historyEntries)) {
            throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}]");
        }

        return collect($historyEntries)
            ->sortByDesc(fn ($entry) => $entry->version())
            ->take($limit)
            ->values();
    }

    /**
     * Add a secret to history
     */
    protected function addToHistory(string $path, Secret $secret): void
    {
        if (! isset(self::$history[$this->name()])) {
            self::$history[$this->name()] = [];
        }

        if (! isset(self::$history[$this->name()][$this->environment])) {
            self::$history[$this->name()][$this->environment] = [];
        }

        if (! isset(self::$history[$this->name()][$this->environment][$path])) {
            self::$history[$this->name()][$this->environment][$path] = [];
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

        self::$history[$this->name()][$this->environment][$path][] = $historyEntry;
    }

    /**
     * Get history entries for a specific path
     */
    protected function getVaultEnvironmentHistory(string $path): array
    {
        return self::$history[$this->name()][$this->environment][$path] ?? [];
    }

    /**
     * Clear all secrets from all vaults and environments (for test cleanup)
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
