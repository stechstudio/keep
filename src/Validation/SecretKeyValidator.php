<?php

namespace STS\Keep\Validation;

class SecretKeyValidator
{
    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 255;
    public const PATTERN = '/^[A-Za-z0-9_-]+$/';

    public function validate(string $key): string
    {
        $trimmed = trim($key);

        if (strlen($trimmed) < self::MIN_LENGTH || strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' must be ".self::MIN_LENGTH.'-'.self::MAX_LENGTH.' characters long.'
            );
        }

        if (!preg_match(self::PATTERN, $trimmed)) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' contains invalid characters. ".
                'Only letters, numbers, underscores, and hyphens are allowed.'
            );
        }

        if (str_starts_with($trimmed, '-')) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' cannot start with a hyphen."
            );
        }

        return $trimmed;
    }

    public function isValid(string $key): bool
    {
        try {
            $this->validate($key);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function getValidationError(string $key): ?string
    {
        try {
            $this->validate($key);
            return null;
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    public function getValidationRules(): array
    {
        return [
            'minLength' => self::MIN_LENGTH,
            'maxLength' => self::MAX_LENGTH,
            'pattern' => self::PATTERN,
            'patternDescription' => 'Only letters, numbers, underscores, and hyphens are allowed',
            'noLeadingHyphen' => true,
        ];
    }
}
