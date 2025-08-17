<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Commands\Concerns\InteractsWithVaults;
use STS\Keeper\Exceptions\KeeperException;
use function Laravel\Prompts\table;

class ListCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keeper:list {--format=env : json|env} '
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE;

    public $description = 'Get the list of environment secrets in a specified vault';

    public function handle(): int
    {
        try {
            $secrets = $this->vault()->list();
        } catch (KeeperException $e) {
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
