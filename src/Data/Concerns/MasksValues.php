<?php

namespace STS\Keep\Data\Concerns;

trait MasksValues
{
    protected function maskValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $length = strlen($value);

        if ($length <= 8) {
            return '****';
        }

        return substr($value, 0, 4).str_repeat('*', $length - 4);
    }
}
