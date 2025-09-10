<?php

namespace STS\Keep\Commands;

class SetCommand extends BaseCommand
{
    public $signature = 'set 
        {key? : The secret key}
        {value? : The secret value}
        {--vault= : The vault to use}
        {--stage= : The stage to use}
        {--plain : Do not encrypt the value}';

    public $description = 'Set the value of a stage secret in a specified vault';

    public function process()
    {
        $key = $this->key();
        $context = $this->vaultContext();
        $secret = $context->createVault()->set($key, $this->value(), $this->secure());

        $action = $secret->revision() === 1 ? 'created' : 'updated';
        $this->success(sprintf('Secret [<secret-name>%s</secret-name>] %s in vault [<context>%s</context>].',
            $secret->path(),
            $action,
            $secret->vault()->name()
        ));
    }

}
