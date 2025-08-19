<?php

namespace STS\Keep\Data;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use STS\Keep\Data\Concerns\MasksValues;

class SecretHistory implements Arrayable
{
    use MasksValues;

    public function __construct(
        protected string $key,
        protected ?string $value = null,
        protected int $version = 1,
        protected ?Carbon $lastModifiedDate = null,
        protected ?string $lastModifiedUser = null,
        protected ?string $dataType = null,
        protected array $labels = [],
        protected ?array $policies = null,
        protected ?string $description = null,
        protected bool $secure = true,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function masked(): ?string
    {
        return $this->maskValue($this->value);
    }

    public function withMaskedValue(): self
    {
        $masked = clone $this;
        $masked->value = $this->masked();

        return $masked;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function lastModifiedDate(): ?Carbon
    {
        return $this->lastModifiedDate;
    }

    public function lastModifiedUser(): ?string
    {
        return $this->lastModifiedUser;
    }

    public function dataType(): ?string
    {
        return $this->dataType;
    }

    public function labels(): array
    {
        return $this->labels;
    }

    public function policies(): ?array
    {
        return $this->policies;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function formattedDate(?string $timezone = null): ?string
    {
        if (! $this->lastModifiedDate) {
            return null;
        }

        if ($timezone) {
            return $this->lastModifiedDate->setTimezone($timezone)->format('Y-m-d H:i:s T');
        }

        return $this->lastModifiedDate->format('Y-m-d H:i:s T');
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'version' => $this->version,
            'lastModifiedDate' => $this->lastModifiedDate?->toISOString(),
            'lastModifiedUser' => $this->lastModifiedUser,
            'dataType' => $this->dataType,
            'labels' => $this->labels,
            'policies' => $this->policies,
            'description' => $this->description,
            'secure' => $this->secure,
        ];
    }
}
