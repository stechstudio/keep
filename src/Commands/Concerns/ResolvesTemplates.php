<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Facades\Keep;

trait ResolvesTemplates
{
    /**
     * Resolve template file path based on environment name.
     */
    protected function resolveTemplateForEnv(string $env): string
    {
        $settings = Keep::getSettings();
        $templateDir = $settings['template_path'] ?? 'env';
        $templateFile = getcwd() . '/' . $templateDir . '/' . $env . '.env';
        
        if (! file_exists($templateFile)) {
            throw new \InvalidArgumentException(
                "No template found for environment '{$env}' at {$templateFile}.\n" .
                "Create one with: keep template:add {$env}.env --env={$env}"
            );
        }

        return $templateFile;
    }
}