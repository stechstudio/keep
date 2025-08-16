<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Commands\Concerns\InteractsWithVaults;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Facades\Keeper;
use STS\Keeper\Secret;

class GetSecretCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keeper:get '
    .self::KEY_SIGNATURE
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE;

    public $description = 'Get the value of a secret in the configured vault';

    public function handle(): int
    {
        try {
            $secret = $this->vault()->get($this->key());
        } catch (KeeperException $e) {
            $this->error(
                sprintf("Failed to get secret [%s] in vault [%s]",
                    $this->vault()->format($this->key()),
                    $this->vaultName()
                )
            );
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->line($secret->plainValue());

        return self::SUCCESS;
    }
}
