<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Data\Context;
use STS\Keep\Facades\Keep;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait GathersInput
{
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
        // Check if the command even has a 'key' argument before trying to access it
        if (!$this->getDefinition()->hasArgument('key')) {
            return null;
        }
        
        return $this->key ??= $this->argument('key') ?? text('Key', 'DATABASE_PASSWORD', required: true);
    }

    protected function value()
    {
        // Check if the command even has a 'value' argument before trying to access it
        if (!$this->getDefinition()->hasArgument('value')) {
            return null;
        }
        
        return $this->value ??= $this->argument('value') ?? text('Value', required: true);
    }

    protected function vaultName($prompt = 'Vault', $cacheName = 'vaultName'): string
    {
        return $this->{$cacheName} ??= match (true) {
            $this->hasOption('vault') && (bool) $this->option('vault') => $this->option('vault'),
            Keep::getConfiguredVaults()->count() === 0 => throw new \RuntimeException('No vaults are configured. Please add a vault first with: keep vault:add.'),
            Keep::getConfiguredVaults()->count() === 1 => Keep::getConfiguredVaults()->first()->slug(),
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

    protected function vaultContext(string $vaultPrompt = 'Vault', string $stagePrompt = 'Stage'): Context
    {
        // Create context from separate vault/stage selection
        return new Context($this->vaultName($vaultPrompt), $this->stage($stagePrompt));
    }

    protected function secure(): bool
    {
        return ! $this->option('plain');
    }
}
