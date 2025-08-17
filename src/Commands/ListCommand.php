<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithVaults;
use STS\Keep\Exceptions\KeepException;
use function Laravel\Prompts\table;

class ListCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keep:list {--format=env : json|env} '
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE;

    public $description = 'Get the list of environment secrets in a specified vault';

    public function handle(): int
    {
        try {
            $secrets = $this->vault()->list();
        } catch (KeepException $e) {
            $this->error(
                sprintf("Failed to get secrets in vault [%s]",
                    $this->vaultName()
                )
            );
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('format') === 'json') {
            $this->line(
                $secrets->toKeyValuePair()->toJson(JSON_PRETTY_PRINT)
            );

            return self::SUCCESS;
        }

        table(
            ['Key', 'Value', 'Version'],
            $secrets->map->toArray(['key','plainValue','version']),
        );

        return self::SUCCESS;
    }
}
