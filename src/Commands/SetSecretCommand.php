<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Commands\Concerns\InteractsWithVaults;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Facades\Keeper;
use STS\Keeper\Secret;

class SetSecretCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keeper:set '
        . self::KEY_SIGNATURE
        . self::VALUE_SIGNATURE
        . self::VAULT_SIGNATURE
        . self::ENV_SIGNATURE
        . self::PLAIN_SIGNATURE;

    public $description = 'Set the value of a secret in the configured vault';

    public function handle(): int
    {
        try {
            $secret = $this->vault()->save(
                new Secret($this->key(), $this->value(), $this->secure())
            );
        } catch (KeeperException $e) {
            $this->error("Failed to set secret [{$this->vault()->format($this->key())}] in vault [{$this->vaultName()}]");
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf("Secret [%s] %s in vault [%s].",
            $secret->path(),
            $secret->version() === 1 ? 'created' : 'updated',
            $secret->vault()->name()
        ));

        return self::SUCCESS;
    }
}
