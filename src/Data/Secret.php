<?php

namespace STS\Keep\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use STS\Keep\Vaults\AbstractVault;

class Secret implements Arrayable
{
    public function __construct(
        protected string $key,
        protected ?string $value = null,
        protected ?string $encryptedValue = null,
        protected bool $secure = true,
        protected ?string $environment = null,
        protected ?int $revision = 0,
        protected ?string $path = null,
        protected ?AbstractVault $vault = null,
    )
    {
    }

    public function key()
    {
        return $this->key;
    }

    public function value(): ?string
    {
        return $this->value;
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

    public function revision(): ?int
    {
        return $this->revision;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function vault(): ?AbstractVault
    {
        return $this->vault;
    }

    public function only(array $keys): array
    {
        return Arr::only($this->toArray(), $keys);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'encryptedValue' => $this->encryptedValue,
            'secure' => $this->secure,
            'environment' => $this->environment,
            'revision' => $this->revision,
            'path' => $this->path,
            'vault' => $this->vault?->name(),
        ];
    }
}