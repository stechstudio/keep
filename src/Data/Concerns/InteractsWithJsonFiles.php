<?php

namespace STS\Keep\Data\Concerns;

use RuntimeException;

trait InteractsWithJsonFiles
{
    abstract public function toArray(): array;
    abstract public static function fromArray(array $data): static;

    public static function fromFile(string $path): static
    {
        if (!file_exists($path)) {
            throw new RuntimeException("File does not exist: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Cannot read file: {$path}");
        }

        $data = json_decode($contents, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Invalid JSON in file {$path}: " . json_last_error_msg()
            );
        }

        if (!is_array($data)) {
            throw new RuntimeException("File must contain a JSON object: {$path}");
        }

        return static::fromArray($data);
    }

    public function saveToFile(string $path): void
    {
        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Cannot create directory: {$directory}");
            }
        }

        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            throw new RuntimeException("Cannot encode data to JSON: " . json_last_error_msg());
        }

        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException("Cannot write file: {$path}");
        }
    }
}