<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Commands\Concerns\InteractsWithVaults;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Secret;

class ListSecretsCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keeper:list '
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE;

    public $description = 'Get the list of secrets in the configured vault';

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

        $this->table(
            ['Key', 'Value', 'Version'],
            $secrets->map(fn(Secret $secret) => [$secret->key(), $secret->plainValue(), $secret->version()]),
        );

        return self::SUCCESS;
    }
}
