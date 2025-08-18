<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Facades\Keep;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait GathersInput
{
    protected const string KEY_SIGNATURE = '{key? : The secret key}';
    protected const string VALUE_SIGNATURE = '{value? : The secret value}';
    protected const string VAULT_SIGNATURE = '{--vault= : The vault to use}';
    protected const string ENV_SIGNATURE = '{--env= : The environment to use}';
    protected const string PLAIN_SIGNATURE = '{--plain : Do not encrypt the value}';
    protected const string ONLY_EXCLUDE_SIGNATURE = '{--only= : Only include keys matching this pattern (e.g. DB_*)} 
        {--except= : Exclude keys matching this pattern (e.g. MAIL_*)}';

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
            !!$this->option('vault')       => $this->option('vault'),
            count(Keep::available()) === 1 => Keep::getDefaultVault(),
            default                        => select('Vault', Keep::available(), Keep::getDefaultVault()),
        };
    }

    protected function environment()
    {
        return $this->env ??= match(true) {
            !!$this->option('env')            => $this->option('env'),
            count(Keep::environments()) === 1 => Keep::environments()[0],
            default                           => select('Environment', Keep::environments(), Keep::environment()),
        };
    }

    /**
     * Reset cached input values (used in testing)
     */
    public function resetInput(): void
    {
        unset($this->key, $this->value, $this->vaultName, $this->env);
    }

    protected function secure(): bool
    {
        return ! $this->option('plain');
    }
}