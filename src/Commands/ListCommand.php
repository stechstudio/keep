<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\table;

class ListCommand extends BaseCommand
{
    public $signature = 'list {--format=table : table|json|env} {--unmask : Show full secret values instead of masked values} '
        .self::ONLY_EXCLUDE_SIGNATURE
        .self::CONTEXT_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Get the list of stage secrets in a specified vault';

    public function process(): int
    {
        $format = $this->option('format');
        
        if (!in_array($format, ['table', 'env', 'json'])) {
            $this->error('Invalid format option. Supported formats are: table, json, env.');
            return self::FAILURE;
        }

        $context = $this->context();
        $secrets = $context->createVault()->list()->filterByPatterns(
            only: $this->option('only'),
            except: $this->option('except')
        );

        if (! $this->option('unmask')) {
            $secrets = $secrets->map->withMaskedValue();
        }

        match ($format) {
            'table' => table(['Key', 'Value', 'Revision'], $secrets->map->only(['key', 'value', 'revision'])),
            'env' => $this->line($secrets->toEnvString()),
            'json' => $this->line($secrets->only(['key', 'value', 'revision'])->toJson(JSON_PRETTY_PRINT)),
        };

        return self::SUCCESS;
    }
}
