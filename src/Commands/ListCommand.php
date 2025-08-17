<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithVaults;
use function Laravel\Prompts\table;

class ListCommand extends AbstractCommand
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keep:list {--format=table : table|json|env} '
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

        match($this->option('format')) {
            'table' => table(['Key', 'Value', 'Version'], $secrets->map->toArray(['key','value','version'])),
            'env'   => $this->line($secrets->toEnvString()),
            'json'  => $this->line($secrets->toPrettyJson(['key', 'value', 'version'])),
            default => $this->error("Invalid format option. Supported formats are: table, json, env."),
        };

        return self::SUCCESS;
    }
}
