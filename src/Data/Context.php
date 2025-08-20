<?php

namespace STS\Keep\Data;

use STS\Keep\Facades\Keep;

class Context
{
    public function __construct(
        public readonly string $vault,
        public readonly string $stage
    ) {}

    /**
     * Parse context from input string in format "vault:stage" or just "stage"
     */
    public static function fromInput(string $input): self
    {
        if (str_contains($input, ':')) {
            [$vault, $stage] = explode(':', $input, 2);
            return new self($vault, $stage);
        }

        // No vault prefix, use default vault
        return new self(Keep::getDefaultVault(), $input);
    }

    /**
     * Get a formatted string representation
     */
    public function toString(): string
    {
        return "{$this->vault}:{$this->stage}";
    }

    /**
     * Check if this context is identical to another
     */
    public function equals(Context $other): bool
    {
        return $this->vault === $other->vault && $this->stage === $other->stage;
    }

    /**
     * Create a vault instance for this context
     */
    public function createVault(): \STS\Keep\Vaults\AbstractVault
    {

        return Keep::vault($this->vault)->forStage($this->stage);
    }
}