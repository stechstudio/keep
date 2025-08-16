<?php

namespace STS\Keeper\Commands\Concerns;

use STS\Keeper\Facades\Keeper;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait GathersInput
{
    protected const string KEY_SIGNATURE = '{key? : The secret key}';
    protected const string VALUE_SIGNATURE = '{value? : The secret value}';
    protected const string VAULT_SIGNATURE = '{--vault= : The vault to use}';
    protected const string ENV_SIGNATURE = '{--env= : The environment to use}';
    protected const string PLAIN_SIGNATURE = '{--plain : Do not encrypt the value}';

    protected string $key;
    protected string $value;
    protected string $vaultName;
    protected string $env;

    protected function key()
    {
        return $this->key ??= $this->argument('key') ?? text('Key', 'DATABASE_PASSWORD', required: true);
    }

    protected function value()
    {
        return $this->value ??= $this->argument('value') ?? text('Value', required: true);
    }

    protected function vaultName()
    {
        return $this->vaultName ??= match(true) {
            $this->option('vault') => $this->option('vault'),
            count(Keeper::available()) === 1 => Keeper::getDefaultVault(),
            default => select('Vault', Keeper::available(), Keeper::getDefaultVault()),
        };
    }

    protected function environment()
    {
        return $this->env ??= match(true) {
            $this->option('env') => $this->option('env'),
            count(Keeper::environments()) === 1 => Keeper::environments()[0],
            default => select('Environment', Keeper::environments(), Keeper::environment()),
        };
    }

    protected function secure(): bool
    {
        return ! $this->option('plain');
    }
}