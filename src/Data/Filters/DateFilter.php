<?php

namespace STS\Keep\Data\Filters;

use Carbon\CarbonImmutable;

class DateFilter
{
    protected CarbonImmutable $carbon;

    public function __construct(protected string $value)
    {
        try {
            // Carbon::parse supports relative formats like "7 days ago", "1 week ago", "30 days", "2024-01-01"
            $this->carbon = CarbonImmutable::parse($value);
        } catch (\Exception $e) {
            // If parsing fails, throw a more descriptive error
            throw new \InvalidArgumentException("Invalid date format: '{$value}'. Use relative formats like '7 days ago' or absolute dates like '2024-01-01'.");
        }
    }

    public function value(): CarbonImmutable
    {
        return $this->carbon;
    }

    public function __toString(): string
    {
        return $this->value()->format('Y-m-d');
    }
}
