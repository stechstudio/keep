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

    protected string $env;

    protected string $fromEnv;

    protected string $toEnv;

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

    protected function env($prompt = 'Environment', $cacheName = 'env')
    {
        return $this->{$cacheName} ??= match (true) {
            $this->hasOption('env') && (bool) $this->option('env') => $this->option('env'),
            count(Keep::getEnvs()) === 1 => Keep::getEnvs()[0],
            default => select($prompt, Keep::getEnvs()),
        };
    }

    /**
     * Reset cached input values (used in testing)
     */
    public function resetInput(): void
    {
        unset($this->key, $this->value, $this->vaultName, $this->fromVaultName, $this->toVaultName, $this->env, $this->fromEnv, $this->toEnv, $this->from, $this->to);
    }

    protected function from()
    {
        return match (true) {
            isset($this->from) => $this->from,
            (bool) $this->option('from') => $this->option('from'),
            default => $this->vaultName('From (vault)', 'fromVaultName').':'.$this->env('From (environment)', 'fromEnv'),
        };
    }

    protected function to()
    {
        return match (true) {
            isset($this->to) => $this->to,
            (bool) $this->option('to') => $this->option('to'),
            default => $this->vaultName('To (vault)', 'toVaultName').':'.$this->env('To (environment)', 'toEnv'),
        };
    }

    protected function vaultContext(string $vaultPrompt = 'Vault', string $envPrompt = 'Environment'): Context
    {
        // Create context from separate vault/env selection
        return new Context($this->vaultName($vaultPrompt), $this->env($envPrompt));
    }

    protected function secure(): bool
    {
        return ! $this->option('plain');
    }
}
