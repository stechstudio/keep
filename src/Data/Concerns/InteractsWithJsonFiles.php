<?php

namespace STS\Keep\Data\Concerns;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

trait InteractsWithJsonFiles
{
    abstract public function toArray(): array;

    abstract public static function fromArray(array $data): static;

    public static function fromFile(string $path): static
    {
        $data = (new Filesystem)->json($path, JSON_THROW_ON_ERROR);

        return static::fromArray($data);
    }

    public function saveToFile(string $path): void
    {
        $filesystem = new Filesystem;

        $filesystem->ensureDirectoryExists(dirname($path));

        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if ($filesystem->put($path, $json) === false) {
            throw new RuntimeException("Cannot write file: {$path}");
        }
    }
}
