<?php

namespace STS\Keep\Data;

use Illuminate\Contracts\Support\Arrayable;
use STS\Keep\Data\Concerns\MasksValues;

class SecretDiff implements Arrayable
{
    use MasksValues;

    public const string STATUS_IDENTICAL = 'identical';

    public const string STATUS_DIFFERENT = 'different';

    public const string STATUS_INCOMPLETE = 'incomplete';

    public function __construct(
        protected string $key,
        protected array $values = [], // ['vault.env' => Secret|null]
        protected string $status = self::STATUS_INCOMPLETE,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function hasValue(string $vaultEnv): bool
    {
        return isset($this->values[$vaultEnv]) && $this->values[$vaultEnv] !== null;
    }

    public function getValue(string $vaultEnv): ?Secret
    {
        return $this->values[$vaultEnv] ?? null;
    }

    public function getValueString(string $vaultEnv, bool $masked = true): string
    {
        $secret = $this->getValue($vaultEnv);

        if ($secret === null) {
            return '<fg=red>—</>';
        }

        if ($masked) {
            return '<fg=green>✓</> '.$this->maskValue($secret->value());
        }
        
        // Use formatted value for diff tables to wrap long values
        return $secret->formattedValueForDiff();
    }

    public function setValue(string $vaultEnv, ?Secret $secret): void
    {
        $this->values[$vaultEnv] = $secret;
        $this->recalculateStatus();
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IDENTICAL => '<info>Identical</info>',
            self::STATUS_DIFFERENT => '<comment>Different</comment>',
            self::STATUS_INCOMPLETE => '<error>Incomplete</error>',
            default => 'Unknown',
        };
    }

    protected function recalculateStatus(): void
    {
        $nonNullValues = array_filter($this->values, fn ($secret) => $secret !== null);

        // If any values are missing, it's incomplete
        if (count($nonNullValues) < count($this->values)) {
            $this->status = self::STATUS_INCOMPLETE;

            return;
        }

        // If no values exist at all, it's incomplete
        if (empty($nonNullValues)) {
            $this->status = self::STATUS_INCOMPLETE;

            return;
        }

        // Get all actual secret values
        $secretValues = array_map(fn (Secret $secret) => $secret->value(), $nonNullValues);
        $uniqueValues = array_unique($secretValues);

        // If all values are the same, it's identical
        if (count($uniqueValues) === 1) {
            $this->status = self::STATUS_IDENTICAL;
        } else {
            $this->status = self::STATUS_DIFFERENT;
        }
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'status' => $this->status,
            'values' => array_map(
                fn (?Secret $secret) => $secret?->toArray(),
                $this->values
            ),
        ];
    }
}
