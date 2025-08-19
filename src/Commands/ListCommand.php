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
            $secrets = $secrets->map->withMaskedValue();
        }

        match ($this->option('format')) {
            'table' => table(['Key', 'Value', 'Revision'], $secrets->map->only(['key', 'value', 'revision'])),
            'env' => $this->line($secrets->toEnvString()),
            'json' => $this->line($secrets->only(['key', 'value', 'revision'])->toJson(JSON_PRETTY_PRINT)),
            default => $this->error('Invalid format option. Supported formats are: table, json, env.'),
        };

        return self::SUCCESS;
    }
}
