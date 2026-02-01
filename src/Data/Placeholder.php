<?php

namespace STS\Keep\Data;

use STS\Keep\Facades\Keep;
use STS\Keep\Validation\SecretKeyValidator;

class Placeholder
{
    public function __construct(
        public readonly int $line,
        public readonly string $envKey,
        public readonly ?string $vault,
        public readonly string $key,
        public readonly string $rawLine,
        public readonly string $placeholderText
    ) {}

    /**
     * Create a Placeholder from template pattern match data
     */
    public static function fromMatch(array $match, int $lineNumber, string $rawLine): self
    {
        $vault = $match['vault'] ?? null;
        $path = isset($match['path']) && ! empty($match['path']) ? $match['path'] : null;

        // For simple placeholders like {API_KEY}, the vault should be null
        // and the key should be the vault part if no path exists
        if (! $path) {
            $actualVault = null;
            $actualKey = $vault; // The vault part becomes the key
        } else {
            $actualVault = $vault;
            $actualKey = $path;
        }

        $placeholderText = '{'.$vault.($path ? ':'.$path : '').'}';

        return new self(
            line: $lineNumber,
            envKey: $match['key'],
            vault: $actualVault,
            key: $actualKey,
            rawLine: $rawLine,
            placeholderText: $placeholderText
        );
    }

    /**
     * Validate this placeholder and return validation result
     */
    public function validate(?string $defaultVault, string $env): PlaceholderValidationResult
    {
        // If no vault specified in placeholder and no default vault, that's an error
        if (! $this->vault && ! $defaultVault) {
            return PlaceholderValidationResult::invalid(
                $this,
                null,
                'No vault specified in placeholder (use {vault:key} format)'
            );
        }

        $vault = $this->vault ?? $defaultVault;

        try {
            // Validate key format
            $validator = new SecretKeyValidator();
            $validationError = $validator->getValidationError($this->key);
            if ($validationError !== null) {
                return PlaceholderValidationResult::invalid(
                    $this,
                    $vault,
                    $validationError
                );
            }

            // Check if vault exists
            if (! Keep::getConfiguredVaults()->has($vault)) {
                return PlaceholderValidationResult::invalid(
                    $this,
                    $vault,
                    "Vault '{$vault}' is not configured"
                );
            }

            // Try to get the secret
            $vaultInstance = Keep::vault($vault, $env);
            $secret = $vaultInstance->get($this->key);

            return PlaceholderValidationResult::valid($this, $vault, $secret);

        } catch (\Exception $e) {
            return PlaceholderValidationResult::invalid($this, $vault, $e->getMessage());
        }
    }

    protected function isValidKeyFormat(): bool
    {
        return (new SecretKeyValidator())->isValid($this->key);
    }

    /**
     * Get the effective vault name (either specified vault or default)
     */
    public function getEffectiveVault(?string $defaultVault = null): ?string
    {
        return $this->vault ?? $defaultVault;
    }

    /**
     * Convert to array for backward compatibility
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'full' => $this->envKey,
            'vault' => $this->vault,
            'key' => $this->key,
            'raw_line' => $this->rawLine,
            'env_key' => $this->envKey,
            'placeholder_text' => $this->placeholderText,
        ];
    }
}
