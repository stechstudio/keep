<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Facades\Keep;
use function Laravel\Prompts\confirm;

trait InteractsWithFilesystem
{
    protected function writeToFile(string $path, string $content, $overwrite = false, $append = false): bool
    {
        $filePath = $path;
        $flags = 0; // Default: overwrite

        if (file_exists($filePath)) {
            if ($append) {
                $flags = FILE_APPEND; // Append
            } elseif (!$overwrite && !confirm('Output file already exists. Overwrite?', false)) {
                return $this->error("File [$filePath] already exists. Use --overwrite or --append option.");
            }
        }

        file_put_contents($filePath, $content.PHP_EOL, $flags);
        $this->info("Secrets exported to [$filePath].");

        return self::SUCCESS;
    }
}
