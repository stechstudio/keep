<?php

namespace STS\Keep\Services;

use InvalidArgumentException;

class SecretKeyValidator
{
    protected const MIN_LENGTH = 1;
    protected const MAX_LENGTH = 255;
    protected const ALLOWED_PATTERN = '/^[A-Za-z0-9_-]+$/';
    
    /**
     * Validate a secret key according to Keep standards.
     * 
     * @throws InvalidArgumentException if validation fails
     */
    public function validate(string $key): void
    {
        $trimmed = trim($key);
        
        // Check for empty key
        if (empty($trimmed)) {
            throw new InvalidArgumentException('Secret key cannot be empty.');
        }
        
        // Length validation
        if (strlen($trimmed) < self::MIN_LENGTH || strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                "Secret key must be ".self::MIN_LENGTH."-".self::MAX_LENGTH." characters long."
            );
        }
        
        // Character validation - only letters, numbers, underscores, and hyphens
        if (!preg_match(self::ALLOWED_PATTERN, $trimmed)) {
            throw new InvalidArgumentException(
                'Secret key contains invalid characters. '.
                'Only letters, numbers, underscores, and hyphens are allowed.'
            );
        }
        
        // Cannot start with hyphen (could be interpreted as command flag)
        if (str_starts_with($trimmed, '-')) {
            throw new InvalidArgumentException(
                'Secret key cannot start with a hyphen.'
            );
        }
    }
    
    /**
     * Check if a key is valid without throwing exceptions.
     */
    public function isValid(string $key): bool
    {
        try {
            $this->validate($key);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
    
    /**
     * Get validation error message for a key, or null if valid.
     */
    public function getError(string $key): ?string
    {
        try {
            $this->validate($key);
            return null;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }
    
    /**
     * Get validation rules for frontend/documentation.
     */
    public function getValidationRules(): array
    {
        return [
            'pattern' => self::ALLOWED_PATTERN,
            'minLength' => self::MIN_LENGTH,
            'maxLength' => self::MAX_LENGTH,
            'allowedCharacters' => 'A-Z, a-z, 0-9, underscore (_), hyphen (-)',
            'restrictions' => [
                'Cannot start with hyphen',
                'No spaces allowed',
            ],
        ];
    }
    
    /**
     * Get JavaScript-compatible validation rules for frontend.
     */
    public function getJavaScriptRules(): array
    {
        return [
            'pattern' => '/^[A-Za-z0-9_-]+$/',
            'minLength' => self::MIN_LENGTH,
            'maxLength' => self::MAX_LENGTH,
            'validate' => "
                function validateSecretKey(key) {
                    const trimmed = key.trim();
                    if (!trimmed) return 'Secret key cannot be empty';
                    if (trimmed.length < ".self::MIN_LENGTH.") return 'Secret key is too short';
                    if (trimmed.length > ".self::MAX_LENGTH.") return 'Secret key is too long';
                    if (!/^[A-Za-z0-9_-]+$/.test(trimmed)) return 'Only letters, numbers, underscores, and hyphens allowed';
                    if (trimmed.startsWith('-')) return 'Cannot start with hyphen';
                    return null; // Valid
                }
            "
        ];
    }
}