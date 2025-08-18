<?php

namespace STS\Keep\Tests\Support;

use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretsCollection;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Vaults\AbstractVault;

class TestVault extends AbstractVault
{
    protected static array $sharedSecrets = [];
    protected int $revision = 1;

    public function list(): SecretsCollection
    {
        return new SecretsCollection(collect(self::$sharedSecrets)->values());
    }

    public function get(string $key): Secret
    {
        $path = $this->format($key);
        
        if (!isset(self::$sharedSecrets[$path])) {
            throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}].");
        }

        return self::$sharedSecrets[$path];
    }

    public function set(string $key, string $value, bool $secure = true): Secret
    {
        $path = $this->format($key);
        $revision = isset(self::$sharedSecrets[$path]) ? self::$sharedSecrets[$path]->revision() + 1 : 1;
        
        $secret = new Secret($key, $value, null, $secure, $this->environment, $revision, $path, $this);
        self::$sharedSecrets[$path] = $secret;
        
        return $secret;
    }

    public function save(Secret $secret): Secret
    {
        self::$sharedSecrets[$secret->path()] = $secret;
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
        self::$sharedSecrets = [];
    }

    public function hasSecret(string $key): bool
    {
        return isset(self::$sharedSecrets[$this->format($key)]);
    }

    public function getSharedSecrets(): array
    {
        return self::$sharedSecrets;
    }
}