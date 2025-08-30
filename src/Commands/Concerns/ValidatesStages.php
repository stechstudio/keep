<?php

namespace STS\Keep\Commands\Concerns;

trait ValidatesStages
{
    protected function isValidStageName(string $name): bool
    {
        return preg_match('/^[a-z0-9_-]+$/', $name) === 1;
    }

    protected function getStageValidationError(string $name): ?string
    {
        if (empty($name)) {
            return 'Stage name is required';
        }

        if (! $this->isValidStageName($name)) {
            return 'Stage name can only contain lowercase letters, numbers, hyphens, and underscores';
        }

        return null;
    }

    protected function stageExists(string $name, array $existingStages): bool
    {
        return in_array($name, $existingStages, true);
    }

    protected function validateNewStageName(string $name, array $existingStages): ?string
    {
        $error = $this->getStageValidationError($name);
        if ($error) {
            return $error;
        }

        if ($this->stageExists($name, $existingStages)) {
            return "Stage '{$name}' already exists";
        }

        return null;
    }
}
