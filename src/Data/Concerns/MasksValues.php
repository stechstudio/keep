<?php

namespace STS\Keep\Data\Concerns;

trait MasksValues
{
    protected function maskValue($value, bool $truncate = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $length = strlen($value);

        // Short values always get generic mask
        if ($length <= 8) {
            return '****';
        }

        // Show first 4 characters plus asterisks
        $masked = substr($value, 0, 4) . str_repeat('*', $length - 4);
        
        if (!$truncate) {
            return $masked;
        }

        return match(true) {
            $length <= 24 => $masked,
            default => substr($masked, 0, 24) . " ({$length} chars)"
        };
    }
}
