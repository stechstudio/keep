<?php

namespace STS\Keep\Data;

class PlaceholderValidationResult
{
    public function __construct(
        public readonly Placeholder $placeholder,
        public readonly string $vault,
        public readonly bool $valid,
        public readonly ?string $error = null,
        public readonly ?Secret $secret = null
    ) {}

    /**
     * Create a valid result
     */
    public static function valid(Placeholder $placeholder, string $vault, Secret $secret): self
    {
        return new self(
            placeholder: $placeholder,
            vault: $vault,
            valid: true,
            secret: $secret
        );
    }

    /**
     * Create an invalid result
     */
    public static function invalid(Placeholder $placeholder, string $vault, string $error): self
    {
        return new self(
            placeholder: $placeholder,
            vault: $vault,
            valid: false,
            error: $error
        );
    }

    /**
     * Convert to array for backward compatibility
     */
    public function toArray(): array
    {
        return [
            'placeholder' => $this->placeholder->toArray(),
            'vault' => $this->vault,
            'key' => $this->placeholder->key,
            'valid' => $this->valid,
            'error' => $this->error,
            'secret' => $this->secret
        ];
    }
}