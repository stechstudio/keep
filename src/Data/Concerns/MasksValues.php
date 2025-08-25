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

        // If not truncating, use original behavior
        if (!$truncate) {
            if ($length <= 8) {
                return '****';
            }
            return substr($value, 0, 4).str_repeat('*', $length - 4);
        }

        // New truncated masking with length indicators
        if ($length <= 10) {
            return '****';
        } elseif ($length <= 50) {
            return '**********';
        } elseif ($length <= 200) {
            return '********** (' . $length . ' chars)';
        } else {
            // Format large sizes in K for readability
            $size = $length > 1024 
                ? round($length / 1024, 1) . 'K' 
                : $length . ' chars';
            return '********** (' . $size . ')';
        }
    }
}
