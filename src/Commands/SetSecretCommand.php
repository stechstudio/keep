<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Facades\Keeper;

class SetSecretCommand extends Command
{
    use GathersInput;

    public $signature = 'keeper:set '
        . self::KEY_SIGNATURE
        . self::VALUE_SIGNATURE
        . self::VAULT_SIGNATURE
        . self::ENV_SIGNATURE
        . self::PLAIN_SIGNATURE;

    public $description = 'Set the value of a secret in the configured vault';

    public function handle(): int
    {
        Keeper::vault($this->vaultName())
            ->forEnvironment($this->environment())
            ->set($this->key(), $this->value(), $this->secure());

        $this->info("Secret [{$this->key()}] set in vault [{$this->vaultName()}].");

        return self::SUCCESS;
    }
}
