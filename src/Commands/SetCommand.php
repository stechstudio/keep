<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Commands\Concerns\InteractsWithVaults;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Data\Secret;

class SetCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keeper:set '
    .self::KEY_SIGNATURE
    .self::VALUE_SIGNATURE
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE
    .self::PLAIN_SIGNATURE;

    public $description = 'Set the value of an environment secret in a specified vault';

    public function handle(): int
    {
        try {
            $secret = $this->vault()->save(
                new Secret($this->key(), $this->value(), $this->secure())
            );
        } catch (KeeperException $e) {
            $this->error(
                sprintf("Failed to set secret [%s] in vault [%s]",
                    $this->vault()->format($this->key()),
                    $this->vaultName()
                )
            );
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            sprintf("Secret [%s] %s in vault [%s].",
                $secret->path(),
                $secret->version() === 1 ? 'created' : 'updated',
                $secret->vault()->name()
            )
        );

        return self::SUCCESS;
    }
}
