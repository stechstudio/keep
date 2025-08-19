<?php

namespace STS\Keep\Commands;

class SetCommand extends AbstractCommand
{
    public $signature = 'keep:set '
        .self::KEY_SIGNATURE
        .self::VALUE_SIGNATURE
        .self::CONTEXT_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE
        .self::PLAIN_SIGNATURE;

    public $description = 'Set the value of a stage secret in a specified vault';

    public function process(): int
    {
        $context = $this->context();
        $secret = $context->createVault()->set($this->key(), $this->value(), $this->secure());

        $this->info(
            sprintf('Secret [%s] %s in vault [%s].',
                $secret->path(),
                $secret->revision() === 1 ? 'created' : 'updated',
                $secret->vault()->name()
            )
        );

        return self::SUCCESS;
    }
}
