<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\table;

class ListCommand extends AbstractCommand
{
    public $signature = 'keep:list {--format=table : table|json|env} {--unmask : Show full secret values instead of masked values} '
        .self::ONLY_EXCLUDE_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::ENV_SIGNATURE;

    public $description = 'Get the list of environment secrets in a specified vault';

    public function process(): int
    {
        $secrets = $this->vault()->list()->filterByPatterns(
            only: $this->option('only'),
            except: $this->option('except')
        );

        if (! $this->option('unmask')) {
            $secrets = $secrets->map(function ($secret) {
                // Create a new Secret with masked value
                return new \STS\Keep\Data\Secret(
                    key: $secret->key(),
                    value: $this->maskValue($secret->value()),
                    encryptedValue: $secret->encryptedValue(),
                    secure: $secret->isSecure(),
                    environment: $secret->environment(),
                    revision: $secret->revision(),
                    path: $secret->path(),
                    vault: $secret->vault()
                );
            });
        }

        match ($this->option('format')) {
            'table' => table(['Key', 'Value', 'Revision'], $secrets->map->only(['key', 'value', 'revision'])),
            'env' => $this->line($secrets->toEnvString()),
            'json' => $this->line($secrets->only(['key', 'value', 'revision'])->toJson(JSON_PRETTY_PRINT)),
            default => $this->error('Invalid format option. Supported formats are: table, json, env.'),
        };

        return self::SUCCESS;
    }

    private function maskValue(string $value): string
    {
        $length = strlen($value);

        if ($length <= 8) {
            return '****';
        }

        return substr($value, 0, 4).str_repeat('*', $length - 4);
    }
}
