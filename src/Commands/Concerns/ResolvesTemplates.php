<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Facades\Keep;

trait ResolvesTemplates
{
    /**
     * Resolve template file path based on stage name.
     */
    protected function resolveTemplateForStage(string $stage): string
    {
        $settings = Keep::getSettings();
        $templateDir = $settings['template_path'] ?? 'env';
        $templateFile = getcwd() . '/' . $templateDir . '/' . $stage . '.env';
        
        if (! file_exists($templateFile)) {
            throw new \InvalidArgumentException(
                "No template found for stage '{$stage}' at {$templateFile}.\n" .
                "Create one with: keep template:add {$stage}.env --stage={$stage}"
            );
        }

        return $templateFile;
    }
}