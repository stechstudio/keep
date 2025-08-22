<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\ConfiguresVaults;
use STS\Keep\Data\VaultConfig;
use STS\Keep\Facades\Keep;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class VaultEditCommand extends BaseCommand
{
    use ConfiguresVaults;

    protected $signature = 'vault:edit {slug? : Slug of the vault to edit}';

    protected $description = 'Edit an existing vault configuration';

    protected function process(): int
    {
        info('🔧  Edit Vault Configuration');

        $configuredVaults = Keep::getConfiguredVaults();

        if ($configuredVaults->isEmpty()) {
            error('No vaults are configured yet.');
            info('Add your first vault with: keep vault:add');

            return self::FAILURE;
        }

        // Get vault slug from argument or prompt user to select
        $slug = $this->argument('slug');

        if (! $slug) {
            // Build options for vault selection with slug as key
            $vaultOptions = [];
            foreach ($configuredVaults as $config) {
                $vaultOptions[$config->slug()] = "{$config->name()} ({$config->slug()})";
            }

            $slug = select(
                label: 'Which vault would you like to edit?',
                options: $vaultOptions
            );
        }

        // Validate vault exists
        if (! $configuredVaults->has($slug)) {
            error("Vault '{$slug}' does not exist.");

            return self::FAILURE;
        }

        /** @var VaultConfig $existingConfig */
        $existingConfig = $configuredVaults->get($slug);

        info("Editing vault: {$existingConfig->name()} ({$slug})");

        // Find the vault class for this driver
        $vaultClass = $this->findVaultClass($existingConfig->driver());
        if (! $vaultClass) {
            error("Unknown vault driver: {$existingConfig->driver()}");

            return self::FAILURE;
        }

        // Edit built-in fields
        $newSlug = text(
            label: 'Vault slug (used in template placeholders)',
            default: $slug,
            hint: 'Short identifier used in secret templates like {vault:DB_PASSWORD}'
        );

        $friendlyName = text(
            label: 'Friendly name for this vault',
            default: $existingConfig->name(),
            hint: 'A descriptive name to identify this vault configuration'
        );

        // Edit vault-specific configuration
        $updatedConfig = $this->editVaultConfig($vaultClass, $friendlyName, $existingConfig);

        // Handle slug changes
        if ($newSlug !== $slug) {
            // Check if new slug already exists
            if ($configuredVaults->has($newSlug)) {
                error("A vault with slug '{$newSlug}' already exists!");

                return self::FAILURE;
            }

            // Delete old vault file by removing it
            $oldVaultPath = getcwd()."/.keep/vaults/{$slug}.json";
            if (file_exists($oldVaultPath)) {
                unlink($oldVaultPath);
            }

            // Create new VaultConfig with updated slug and save it
            $updatedConfig['slug'] = $newSlug;
            $newVaultConfig = VaultConfig::fromArray($updatedConfig);
            $newVaultConfig->save();

            // Update default vault if this was the default
            if (Keep::getSetting('default_vault') === $slug) {
                $settings = Keep::getSettings();
                $settings = array_merge($settings, ['default_vault' => $newSlug]);
                $settingsObject = \STS\Keep\Data\Settings::fromArray($settings);
                $settingsObject->save();
            }

            info("✅ Vault configuration updated and renamed from '{$slug}' to '{$newSlug}'");
        } else {
            // Save with same slug
            $updatedConfig['slug'] = $slug;
            $vaultConfig = VaultConfig::fromArray($updatedConfig);
            $vaultConfig->save();
            info("✅ Vault '{$slug}' configuration updated successfully");
        }

        return self::SUCCESS;
    }

    private function findVaultClass(string $driver): ?string
    {
        $availableVaults = Keep::getAvailableVaults();

        foreach ($availableVaults as $vaultClass) {
            if ($vaultClass::DRIVER === $driver) {
                return $vaultClass;
            }
        }

        return null;
    }

    private function editVaultConfig(string $vaultClass, string $friendlyName, VaultConfig $existingConfig): array
    {
        info("Configuring {$friendlyName}...");

        // Get dynamic prompts from the vault class, passing existing settings as array
        $prompts = $vaultClass::configure($existingConfig->toArray());
        $config = [
            'driver' => $vaultClass::DRIVER,
            'name' => $friendlyName,
        ];

        // Process each prompt
        foreach ($prompts as $key => $prompt) {
            $value = $prompt->prompt();

            // Only store non-empty values
            if ($value !== '') {
                $config[$key] = $value;
            }
        }

        return $config;
    }
}
