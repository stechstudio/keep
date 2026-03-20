<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Settings;
use STS\Keep\Facades\Keep;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class EnvRemoveCommand extends BaseCommand
{
    private const SYSTEM_ENVS = ['local', 'staging', 'production'];

    protected $signature = 'env:remove
        {name? : The environment to remove}
        {--force : Skip confirmation prompt}';

    protected $description = 'Remove a custom environment';

    protected function process()
    {
        $settings = Settings::load();
        $envs = $settings->envs();

        $customEnvs = array_values(array_diff($envs, self::SYSTEM_ENVS));

        if (empty($customEnvs)) {
            $this->info('No custom environments to remove.');
            $this->neutral('System environments (local, staging, production) cannot be removed.');

            return self::SUCCESS;
        }

        $envName = $this->argument('name') ?? select(
            label: 'Which environment do you want to remove?',
            options: $customEnvs
        );

        if (in_array($envName, self::SYSTEM_ENVS)) {
            $this->error("Cannot remove system environment '{$envName}'.");

            return self::FAILURE;
        }

        if (! in_array($envName, $envs)) {
            $this->error("Environment '{$envName}' not found.");

            return self::FAILURE;
        }

        $this->line('Current environments: '.implode(', ', $envs));

        if (! $this->option('force')) {
            $confirmed = confirm(
                label: "Are you sure you want to remove the '{$envName}' environment?",
                default: false,
                hint: 'This removes the environment from settings only — secrets in remote vaults are not affected'
            );

            if (! $confirmed) {
                $this->neutral('Environment removal cancelled.');

                return self::SUCCESS;
            }
        }

        $settings->withEnvs(
            array_values(array_filter($envs, fn ($e) => $e !== $envName))
        )->save();

        $this->newLine();
        $this->success("Environment <secret-name>{$envName}</secret-name> has been removed.");
    }
}
