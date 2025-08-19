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

    protected const string STAGE_SIGNATURE = '{--stage= : The stage to use}';

    protected const string PLAIN_SIGNATURE = '{--plain : Do not encrypt the value}';

    protected const string ONLY_EXCLUDE_SIGNATURE = '{--only= : Only include keys matching this pattern (e.g. DB_*)} 
        {--except= : Exclude keys matching this pattern (e.g. MAIL_*)}';

    protected const string UNMASK_SIGNATURE = '{--unmask : Show full secret values instead of masked values}';

    protected string $key;

    protected string $value;

    protected string $vaultName;

    protected string $stage;

    protected string $from;

    protected string $to;

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
        return $this->vaultName ??= match (true) {
            (bool) $this->option('vault') => $this->option('vault'),
            count(Keep::available()) === 1 => Keep::getDefaultVault(),
            default => select('Vault', Keep::available(), Keep::getDefaultVault()),
        };
    }

    protected function stage()
    {
        return $this->stage ??= match (true) {
            (bool) $this->option('stage') => $this->option('stage'),
            count(Keep::stages()) === 1 => Keep::stages()[0],
            default => select('Stage', Keep::stages(), Keep::stage()),
        };
    }

    /**
     * Reset cached input values (used in testing)
     */
    public function resetInput(): void
    {
        unset($this->key, $this->value, $this->vaultName, $this->stage, $this->from, $this->to);
    }

    protected function from()
    {
        return $this->from ??= $this->option('from') ?? text('From (vault:stage or stage)', 'development', required: true);
    }

    protected function to()
    {
        return $this->to ??= $this->option('to') ?? text('To (vault:stage or stage)', 'production', required: true);
    }

    protected function secure(): bool
    {
        return ! $this->option('plain');
    }
}
