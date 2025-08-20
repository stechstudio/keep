<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Facades\Keep;
use function Laravel\Prompts\confirm;

trait InteractsWithFilesystem
{
    protected function writeToFile(string $path, string $content, $overwrite = false, $append = false): bool
    {
        $filePath = $path;
        $flags = 0;

        if (file_exists($filePath)) {
            if ($overwrite) {
                $flags = 0; // Overwrite
            } elseif ($append) {
                $flags = FILE_APPEND; // Append
            } else {
                if (confirm('Output file already exists. Overwrite?', false)) {
                    $flags = 0; // Overwrite
                } else {
                    $this->error("File [$filePath] already exists. Use --overwrite or --append option.");

                    return self::FAILURE;
                }
            }
        }

        file_put_contents($filePath, $content.PHP_EOL, $flags);
        $this->info("Secrets exported to [$filePath].");

        return self::SUCCESS;
    }

    protected function findStageOverlayTemplate(): ?string
    {
        $stageTemplatesPath = Keep::getSetting('stage_templates');
        
        if (! $stageTemplatesPath) {
            return null;
        }

        $path = $stageTemplatesPath.'/'.$this->stage().'.env';

        return file_exists($path) && is_readable($path) ? $path : null;
    }
}
