<?php

namespace STS\Keep\Commands;

use STS\Keep\Exceptions\KeepException;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\IamPolicyGenerator;
use Symfony\Component\Process\Process;

class IamCommand extends BaseCommand
{
    public $signature = 'iam
        {--vault= : Generate policy for a specific vault only}
        {--all : Generate policy for all vaults and environments, ignoring workspace}
        {--browser : Open the AWS IAM console to create a policy}';

    public $description = 'Generate an IAM policy for your configured vaults';

    protected function process()
    {
        $namespace = Keep::getNamespace();
        $useAll = $this->option('all');

        $vaults = $useAll ? Keep::getAllConfiguredVaults() : Keep::getConfiguredVaults();
        $envs = $useAll ? Keep::getAllEnvs() : Keep::getEnvs();

        if ($vaultFilter = $this->option('vault')) {
            $allVaults = Keep::getAllConfiguredVaults();
            if (! $allVaults->has($vaultFilter)) {
                throw new KeepException("Vault '{$vaultFilter}' is not configured.");
            }
            $vaults = $allVaults->only($vaultFilter);
        }

        if ($vaults->isEmpty()) {
            throw (new KeepException('No vaults are configured.'))->withContext([
                'suggestion' => 'Add a vault first with: keep vault:add',
            ]);
        }

        $this->line('');
        $this->line('<info>IAM Policy for Keep</info>');

        if (! $useAll && ! $this->option('vault')) {
            $this->line('<comment>  Scoped to your workspace. Use --all for all vaults and environments.</comment>');
        }

        $this->line('');
        $this->line("  Namespace:    <comment>{$namespace}</comment>");
        $this->line('  Environments: <comment>' . implode(', ', $envs) . '</comment>');

        foreach ($vaults as $vault) {
            $region = $vault->get('region', 'us-east-1');
            $this->line("  Vault:        <comment>{$vault->slug()}</comment> ({$vault->driver()}, {$region})");
        }

        $this->line('');

        $generator = new IamPolicyGenerator();
        $policy = $generator->generate($vaults, $namespace, $envs);

        $this->line(json_encode($policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line('');
        $this->line('Create this policy in the AWS IAM console and attach it to your user or role.');

        if ($this->option('browser')) {
            $this->line('');
            $this->line('Opening AWS IAM console...');
            $this->openBrowser('https://console.aws.amazon.com/iam/home#/policies/create');
        }
    }

    protected function openBrowser(string $url): void
    {
        $commands = [
            'Darwin' => 'open',
            'Linux' => 'xdg-open',
            'Windows' => 'start',
        ];

        $os = PHP_OS_FAMILY === 'Windows' ? 'Windows' : PHP_OS;

        if (isset($commands[$os])) {
            $process = new Process([$commands[$os], $url]);
            $process->run();
        }
    }
}
