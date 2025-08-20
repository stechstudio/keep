<?php

namespace STS\Keep\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use STS\Keep\Data\Concerns\MasksValues;
use STS\Keep\Vaults\AbstractVault;

class Secret implements Arrayable
{
    use MasksValues;

    protected string $key;

    public function __construct(
        string $key,
        protected ?string $value = null,
        protected ?string $encryptedValue = null,
        protected bool $secure = true,
        protected ?string $stage = null,
        protected ?int $revision = 0,
        protected ?string $path = null,
        protected ?AbstractVault $vault = null,
    ) {
        $this->key = $this->validateKey($key);
    }

    /**
     * Validate a secret key using strict whitelist validation.
     * Only allows letters, digits, and underscores (standard .env format).
     *
     * @param  string  $key  The raw key to validate
     * @return string The validated key
     * @throws \InvalidArgumentException If key contains invalid characters
     */
    protected function validateKey(string $key): string
    {
        $trimmed = trim($key);
        
        // Strict whitelist: Only allow letters, digits, and underscores
        if (!preg_match('/^[A-Za-z0-9_]+$/', $trimmed)) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' contains invalid characters. " .
                "Only letters, numbers, and underscores are allowed."
            );
        }
        
        // Length validation (reasonable limits for secret names)
        if (strlen($trimmed) < 1 || strlen($trimmed) > 255) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' must be 1-255 characters long."
            );
        }
        
        // Cannot start with underscore (poor practice, could conflict with system vars)
        if (str_starts_with($trimmed, '_')) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' cannot start with underscore."
            );
        }
        
        // Cannot start with digit (invalid variable name in most languages)
        if (preg_match('/^[0-9]/', $trimmed)) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' cannot start with a number."
            );
        }
        
        return $trimmed;
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

    public function stage(): ?string
    {
        return $this->stage;
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

    public function withMaskedValue(): static
    {
        $masked = clone $this;
        $masked->value = $this->masked();

        return $masked;
    }

    public function masked(): ?string
    {
        return $this->maskValue($this->value);
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
            'stage' => $this->stage,
            'revision' => $this->revision,
            'path' => $this->path,
            'vault' => $this->vault?->name(),
        ];
    }
}
