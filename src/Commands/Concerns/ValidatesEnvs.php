<?php

namespace STS\Keep\Commands\Concerns;

trait ValidatesEnvs
{
    protected function isValidEnvName(string $name): bool
    {
        return preg_match('/^[a-z0-9_-]+$/', $name) === 1;
    }

    protected function getEnvValidationError(string $name): ?string
    {
        if (empty($name)) {
            return 'Environment name is required';
        }

        if (! $this->isValidEnvName($name)) {
            return 'Environment name can only contain lowercase letters, numbers, hyphens, and underscores';
        }

        return null;
    }

    protected function envExists(string $name, array $existingEnvs): bool
    {
        return in_array($name, $existingEnvs, true);
    }

    protected function validateNewEnvName(string $name, array $existingEnvs): ?string
    {
        $error = $this->getEnvValidationError($name);
        if ($error) {
            return $error;
        }

        if ($this->envExists($name, $existingEnvs)) {
            return "Environment '{$name}' already exists";
        }

        return null;
    }
}