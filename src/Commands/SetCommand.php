<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithVaults;
use STS\Keep\Data\Secret;

class SetCommand extends AbstractCommand
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keep:set '
    .self::KEY_SIGNATURE
    .self::VALUE_SIGNATURE
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE
    .self::PLAIN_SIGNATURE;

    public $description = 'Set the value of an environment secret in a specified vault';

    public function process(): int
    {
        $secret = $this->vault()->save(
            new Secret($this->key(), $this->value(), $this->secure())
        );

        $this->info(
            sprintf("Secret [%s] %s in vault [%s].",
                $secret->path(),
                $secret->revision() === 1 ? 'created' : 'updated',
                $secret->vault()->name()
            )
        );

        return self::SUCCESS;
    }
}
