<?php

namespace STS\Keeper;

use Illuminate\Contracts\Support\Arrayable;
use STS\Keeper\Vaults\AbstractKeeperVault;

class Secret implements Arrayable
{
    public function __construct(
        protected string $key,
        protected ?string $plainValue = null,
        protected ?string $encryptedValue = null,
        protected bool $secure = true,
        protected ?string $environment = null,
        protected ?int $version = 0,
        protected ?string $path = null,
        protected ?AbstractKeeperVault $vault = null,
    )
    {
    }

    public function key()
    {
        return $this->key;
    }

    public function plainValue(): ?string
    {
        return $this->plainValue;
    }

    public function encryptedValue(): ?string
    {
        return $this->encryptedValue;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function environment(): ?string
    {
        return $this->environment;
    }

    public function version(): ?int
    {
        return $this->version;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function vault(): ?AbstractKeeperVault
    {
        return $this->vault;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'plainValue' => $this->plainValue,
            'encryptedValue' => $this->encryptedValue,
            'secure' => $this->secure,
            'environment' => $this->environment,
            'version' => $this->version,
            'path' => $this->path,
            'vault' => $this->vault?->name(),
        ];
    }
}