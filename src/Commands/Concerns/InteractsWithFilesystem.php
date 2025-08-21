<?php

namespace STS\Keep\Commands\Concerns;

use function Laravel\Prompts\confirm;

trait InteractsWithFilesystem
{
    protected function writeToFile(string $path, string $content, $overwrite = false, $append = false, ?int $permissions = null): bool
    {
        $flags = 0; // Default: overwrite

        if (file_exists($path)) {
            if ($append) {
                $flags = FILE_APPEND; // Append
            } elseif (! $overwrite && ! confirm('Output file already exists. Overwrite?', false)) {
                return $this->error("File [$path] already exists. Use --overwrite or --append option.");
            }
        }

        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $content.PHP_EOL, $flags);

        // Set file permissions if specified
        if ($permissions !== null) {
            chmod($path, $permissions);
        }

        return self::SUCCESS;
    }
}
