<?php

namespace STS\Keep\Services;

use Illuminate\Filesystem\Filesystem;
use STS\Keep\Exceptions\ExceptionFactory;

class OutputWriter
{
    public function __construct(
        protected Filesystem $filesystem
    ) {}

    /**
     * Write content to file with overwrite/append options.
     */
    public function write(string $path, string $content, bool $overwrite = false, bool $append = false): void
    {
        // Ensure directory exists
        $this->filesystem->ensureDirectoryExists(dirname($path));

        if ($this->filesystem->exists($path) && !$overwrite && !$append) {
            throw ExceptionFactory::fileExists($path);
        }

        if ($append && $this->filesystem->exists($path)) {
            $this->filesystem->append($path, $content . PHP_EOL);
        } else {
            $this->filesystem->put($path, $content . PHP_EOL);
        }
    }
}