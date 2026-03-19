<?php

namespace STS\Keep\Commands\Concerns;

use function Laravel\Prompts\confirm;

trait InteractsWithFilesystem
{
    protected function writeToFile(string $path, string $content, $overwrite = false, $append = false, ?int $permissions = null): int
    {
        if ($this->filesystem->exists($path) && ! $append && ! $overwrite && ! confirm('Output file already exists. Overwrite?', false)) {
            $this->error("File [$path] already exists. Use --overwrite or --append option.");

            return self::FAILURE;
        }

        $this->filesystem->ensureDirectoryExists(dirname($path));

        if ($append && $this->filesystem->exists($path)) {
            $this->filesystem->append($path, $content.PHP_EOL);
        } else {
            $this->filesystem->put($path, $content.PHP_EOL);
        }

        if ($permissions !== null) {
            $this->filesystem->chmod($path, $permissions);
        }

        return self::SUCCESS;
    }
}
