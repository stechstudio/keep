<?php

namespace STS\Keep\Data;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use STS\Keep\Data\Concerns\MasksValues;
use STS\Keep\Vaults\AbstractVault;

class Secret implements Arrayable
{
    use MasksValues;

    protected string $key;
    
    /**
     * Maximum width for table display values
     */
    const TABLE_VALUE_WIDTH = 80;

    public function __construct(
        string $key,
        protected ?string $value = null,
        protected ?string $encryptedValue = null,
        protected bool $secure = true,
        protected ?string $stage = null,
        protected null|int|string $revision = 0,
        protected ?string $path = null,
        protected ?AbstractVault $vault = null,
        protected ?Carbon $lastModified = null,
        protected bool $skipValidation = false,
    ) {
        $this->key = $skipValidation ? trim($key) : $this->validateKey($key);
    }

    /**
     * Validate a secret key for safe vault operations.
     * Allows common naming conventions (letters, digits, underscores, hyphens)
     * while preventing characters that could break vault API calls.
     *
     * @param  string  $key  The raw key to validate
     * @return string The validated key
     *
     * @throws \InvalidArgumentException If key contains invalid characters
     */
    protected function validateKey(string $key): string
    {
        $trimmed = trim($key);

        // Allow letters, digits, underscores, and hyphens (common in cloud services)
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $trimmed)) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' contains invalid characters. ".
                'Only letters, numbers, underscores, and hyphens are allowed.'
            );
        }

        // Length validation (reasonable limits for secret names)
        if (strlen($trimmed) < 1 || strlen($trimmed) > 255) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' must be 1-255 characters long."
            );
        }

        // Cannot start with hyphen (could be interpreted as command flag)
        if (str_starts_with($trimmed, '-')) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' cannot start with hyphen."
            );
        }

        return $trimmed;
    }

    /**
     * Create a Secret from vault data (permissive key validation).
     * Use this when reading existing secrets from external sources.
     */
    public static function fromVault(
        string $key,
        ?string $value = null,
        ?string $encryptedValue = null,
        bool $secure = true,
        ?string $stage = null,
        null|int|string $revision = 0,
        ?string $path = null,
        ?AbstractVault $vault = null,
        ?Carbon $lastModified = null,
    ): static {
        return new static(
            key: $key,
            value: $value,
            encryptedValue: $encryptedValue,
            secure: $secure,
            stage: $stage,
            revision: $revision,
            path: $path,
            vault: $vault,
            lastModified: $lastModified,
            skipValidation: true,
        );
    }

    /**
     * Create a Secret from user input (strict key validation).
     * Use this when accepting user-provided secret names.
     */
    public static function fromUser(
        string $key,
        ?string $value = null,
        ?string $encryptedValue = null,
        bool $secure = true,
        ?string $stage = null,
        null|int|string $revision = 0,
        ?string $path = null,
        ?AbstractVault $vault = null,
        ?Carbon $lastModified = null,
    ): static {
        return new static(
            key: $key,
            value: $value,
            encryptedValue: $encryptedValue,
            secure: $secure,
            stage: $stage,
            revision: $revision,
            path: $path,
            vault: $vault,
            lastModified: $lastModified,
            skipValidation: false,
        );
    }

    public function key()
    {
        return $this->key;
    }

    /**
     * Get a sanitized version of the key safe for .env files.
     * Converts non-alphanumeric characters to underscores and ensures valid .env format.
     */
    public function sanitizedKey(): string
    {
        $sanitized = $this->key;

        // Replace invalid characters with underscores
        $sanitized = preg_replace('/[^A-Za-z0-9_]/', '_', $sanitized);

        // Remove leading underscores
        $sanitized = ltrim($sanitized, '_');

        // Remove leading digits by prefixing with 'KEY_'
        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = 'KEY_'.$sanitized;
        }

        // Handle empty string case
        if (empty($sanitized)) {
            $sanitized = 'UNNAMED_KEY';
        }

        // Convert to uppercase (common .env convention)
        return strtoupper($sanitized);
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

    public function lastModified(): ?Carbon
    {
        return $this->lastModified;
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
    
    /**
     * Format value for table display by wrapping long lines
     */
    public function formattedValue(): ?string
    {
        if ($this->value === null) {
            return null;
        }
        
        // For masked values, don't wrap (they're already short)
        if (str_contains($this->value, '•') || str_contains($this->value, '*')) {
            return $this->value;
        }
        
        // If value already contains line breaks, respect them but still wrap long lines
        $lines = explode("\n", $this->value);
        $wrappedLines = [];
        
        foreach ($lines as $line) {
            if (strlen($line) <= self::TABLE_VALUE_WIDTH) {
                $wrappedLines[] = $line;
            } else {
                // Wrap long lines at word boundaries if possible
                $wrappedLines = array_merge($wrappedLines, $this->wrapLine($line));
            }
        }
        
        return implode("\n", $wrappedLines);
    }
    
    /**
     * Wrap a single line of text
     */
    private function wrapLine(string $line): array
    {
        $wrapped = [];
        $words = explode(' ', $line);
        $currentLine = '';
        
        foreach ($words as $word) {
            // If word itself is longer than width, chunk it
            if (strlen($word) > self::TABLE_VALUE_WIDTH) {
                if ($currentLine) {
                    $wrapped[] = $currentLine;
                    $currentLine = '';
                }
                $wrapped = array_merge($wrapped, str_split($word, self::TABLE_VALUE_WIDTH));
                continue;
            }
            
            $testLine = $currentLine ? $currentLine . ' ' . $word : $word;
            
            if (strlen($testLine) <= self::TABLE_VALUE_WIDTH) {
                $currentLine = $testLine;
            } else {
                if ($currentLine) {
                    $wrapped[] = $currentLine;
                }
                $currentLine = $word;
            }
        }
        
        if ($currentLine) {
            $wrapped[] = $currentLine;
        }
        
        return $wrapped ?: [''];
    }
    
    /**
     * Get data formatted for table display
     */
    public function forTable(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->formattedValue(),
            'revision' => $this->revision,
        ];
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
            'lastModified' => $this->lastModified?->toISOString(),
        ];
    }

    /**
     * Convert to array format suitable for API responses.
     * Handles masking and only includes fields needed by API clients.
     */
    public function toApiArray(bool $unmask = false): array
    {
        return [
            'key' => $this->key,
            'value' => $unmask ? $this->value : $this->masked(),
            'revision' => $this->revision,
            'modified' => $this->lastModified?->toISOString(),
        ];
    }
}
