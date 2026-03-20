<?php

namespace STS\Keep\Commands;

use STS\Keep\Facades\Keep;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class VaultDeleteCommand extends BaseCommand
{
    protected $signature = 'vault:delete
        {vault? : The vault slug to delete}
        {--force : Skip confirmation prompt}';

    protected $description = 'Delete a vault configuration';

    protected function process()
    {
        $configuredVaults = Keep::getConfiguredVaults();

        if ($configuredVaults->isEmpty()) {
            $this->info('No vaults are configured.');

            return self::SUCCESS;
        }

        $slug = $this->argument('vault') ?? select(
            label: 'Which vault do you want to delete?',
            options: $configuredVaults->keys()->toArray()
        );

        if (! $configuredVaults->has($slug)) {
            $this->error("Vault '{$slug}' not found.");

            return self::FAILURE;
        }

        $defaultVault = Keep::getDefaultVault();

        if ($slug === $defaultVault) {
            $this->error('Cannot delete the default vault.');
            $this->line('Change the default vault first with: keep init');

            return self::FAILURE;
        }

        $config = $configuredVaults->get($slug);

        $this->newLine();
        $this->line('Vault to be deleted:');
        table(['Slug', 'Name', 'Driver'], [
            [$slug, $config->name(), $config->driver()],
        ]);

        if (! $this->option('force')) {
            $confirmed = confirm(
                label: 'Are you sure you want to delete this vault configuration?',
                default: false,
                hint: 'This removes the local config only — secrets in the remote vault are not affected'
            );

            if (! $confirmed) {
                $this->neutral('Vault deletion cancelled.');

                return self::SUCCESS;
            }
        }

        $vaultFile = getcwd().'/.keep/vaults/'.$slug.'.json';

        if (! $this->filesystem->exists($vaultFile)) {
            $this->error("Vault configuration file not found: {$vaultFile}");

            return self::FAILURE;
        }

        $this->filesystem->delete($vaultFile);

        $this->newLine();
        $this->success("Vault <secret-name>{$slug}</secret-name> configuration has been deleted.");
    }
}
