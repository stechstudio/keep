<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Data\Context;
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

    protected string $fromVaultName;

    protected string $toVaultName;

    protected string $stage;

    protected string $fromStage;

    protected string $toStage;

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

    protected function vaultName($prompt = 'Vault', $cacheName = 'vaultName')
    {
        return $this->{$cacheName} ??= match (true) {
            $this->hasOption('vault') && (bool) $this->option('vault') => $this->option('vault'),
            Keep::getConfiguredVaults()->count() === 1 => Keep::getDefaultVault(),
            default => select($prompt, Keep::getConfiguredVaults()->keys()->toArray(), Keep::getDefaultVault()),
        };
    }

    protected function stage($prompt = 'Stage', $cacheName = 'stage')
    {
        return $this->{$cacheName} ??= match (true) {
            $this->hasOption('stage') && (bool) $this->option('stage') => $this->option('stage'),
            count(Keep::getStages()) === 1 => Keep::getStages()[0],
            default => select($prompt, Keep::getStages()),
        };
    }

    /**
     * Reset cached input values (used in testing)
     */
    public function resetInput(): void
    {
        unset($this->key, $this->value, $this->vaultName, $this->fromVaultName, $this->toVaultName, $this->stage, $this->fromStage, $this->toStage, $this->from, $this->to);
    }

    protected function from()
    {
        return match (true) {
            isset($this->from) => $this->from,
            (bool) $this->option('from') => $this->option('from'),
            default => $this->vaultName('From (vault)', 'fromVaultName').':'.$this->stage('From (stage)', 'fromStage'),
        };
    }

    protected function to()
    {
        return match (true) {
            isset($this->to) => $this->to,
            (bool) $this->option('to') => $this->option('to'),
            default => $this->vaultName('To (vault)', 'toVaultName').':'.$this->stage('To (stage)', 'toStage'),
        };
    }

    protected function context(string $vaultPrompt = 'Vault', string $stagePrompt = 'Stage'): Context
    {
        // Create context from separate vault/stage selection
        return new Context($this->vaultName($vaultPrompt), $this->stage($stagePrompt));
    }

    protected function secure(): bool
    {
        return ! $this->option('plain');
    }
}
