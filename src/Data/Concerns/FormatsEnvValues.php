<?php

namespace STS\Keep\Data\Concerns;

trait FormatsEnvValues
{
    /**
     * Format a value for .env file output.
     *
     * Follows Laravel's conservative approach:
     * - Only alphanumeric values are left unquoted
     * - Everything else gets quoted for safety
     * - Empty strings return empty (no quotes)
     * - Null values return empty string
     */
    protected function formatEnvValue(?string $value): string
    {
        // Null values become empty
        if ($value === null) {
            return '';
        }

        // Empty string stays empty (no quotes)
        if ($value === '') {
            return '';
        }

        // Check if value needs quotes (anything that's not pure alphanumeric)
        $needsQuotes = ! preg_match('/^[a-zA-Z0-9]+$/', $value);

        if (! $needsQuotes) {
            return $value;
        }

        // Quote the value, choosing quote style based on content
        return $this->quoteValue($value);
    }

    /**
     * Quote a value for .env file, choosing appropriate quote style.
     */
    protected function quoteValue(string $value): string
    {
        // If value contains double quotes, use single quotes
        if (str_contains($value, '"')) {
            // Escape backslashes and single quotes for single-quoted string
            $escaped = addslashes($value);
            // But don't escape the double quotes inside
            $escaped = str_replace('\\"', '"', $escaped);

            return "'".$escaped."'";
        }

        // Otherwise use double quotes
        // Escape backslashes and double quotes
        $escaped = addslashes($value);
        // But don't escape single quotes inside double-quoted strings
        $escaped = str_replace("\\'", "'", $escaped);

        return '"'.$escaped.'"';
    }
}
