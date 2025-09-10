<?php

namespace STS\Keep\Data;

use STS\Keep\Facades\Keep;

class Context
{
    public function __construct(
        public readonly string $vault,
        public readonly string $env
    ) {}

    /**
     * Parse context from input string in format "vault:env" or just "env"
     */
    public static function fromInput(string $input): self
    {
        if (str_contains($input, ':')) {
            [$vault, $env] = explode(':', $input, 2);

            return new self($vault, $env);
        }

        // No vault prefix, use default vault
        return new self(Keep::getDefaultVault(), $input);
    }

    /**
     * Get a formatted string representation
     */
    public function toString(): string
    {
        return "{$this->vault}:{$this->env}";
    }

    /**
     * Check if this context is identical to another
     */
    public function equals(Context $other): bool
    {
        return $this->vault === $other->vault && $this->env === $other->env;
    }

    /**
     * Create a vault instance for this context
     */
    public function createVault(): \STS\Keep\Vaults\AbstractVault
    {
        return Keep::vault($this->vault, $this->env);
    }
}
