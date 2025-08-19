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
        $this->key = $this->sanitizeKey($key);
    }

    /**
     * Sanitize a secret key by removing dangerous characters and normalizing format.
     *
     * @param  string  $key  The raw key to sanitize
     * @return string The sanitized key
     */
    protected function sanitizeKey(string $key): string
    {
        // 1. Trim whitespace
        $sanitized = trim($key);

        // 2. Remove null bytes and control characters
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $sanitized);

        // 3. Replace spaces with underscores (common in env vars)
        $sanitized = str_replace(' ', '_', $sanitized);

        // 4. Collapse multiple underscores/dashes to single
        $sanitized = preg_replace('/[_-]{2,}/', '_', $sanitized);

        // 5. Remove leading/trailing underscores/dashes
        $sanitized = trim($sanitized, '_-');

        if (empty($sanitized)) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' is invalid after sanitization (resulted in empty string)"
            );
        }

        return $sanitized;
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
