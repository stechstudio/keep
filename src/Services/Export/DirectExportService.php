<?php

namespace STS\Keep\Services\Export;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Services\OutputWriter;
use STS\Keep\Services\SecretLoader;
use Symfony\Component\Console\Output\OutputInterface;

class DirectExportService
{
    public function __construct(
        protected SecretLoader $secretLoader,
        protected OutputWriter $outputWriter
    ) {}

    /**
     * Handle direct export (no template).
     */
    public function handle(array $options, OutputInterface $output): int
    {
        $stage = $options['stage'];
        $vaultNames = $this->getVaultNames($options);

        // Load secrets from vaults
        $allSecrets = $this->secretLoader->loadFromVaults($vaultNames, $stage);

        // Apply filters
        $allSecrets = $allSecrets->filterByPatterns(
            only: $options['only'] ?? null,
            except: $options['except'] ?? null
        );

        // Format output
        $formattedOutput = $this->formatOutput($allSecrets, $options['format']);

        // Output info
        if (count($vaultNames) === 1) {
            $output->writeln("<info>Exporting secrets from vault '{$vaultNames[0]}' for stage '{$stage}'...</info>");
        } else {
            $output->writeln('<info>Exporting secrets from '.count($vaultNames).' vaults ('.implode(', ', $vaultNames).") for stage '{$stage}'...</info>");
        }
        $output->writeln('<info>Found '.$allSecrets->count().' total secrets to export</info>');

        // Write output
        if ($options['file']) {
            $this->outputWriter->write(
                $options['file'],
                $formattedOutput,
                $options['overwrite'] ?? false,
                $options['append'] ?? false
            );
            $output->writeln("<info>Secrets exported to [{$options['file']}].</info>");
        } else {
            $output->writeln($formattedOutput);
        }

        return 0;
    }

    protected function getVaultNames(array $options): array
    {
        // If --vault specified, use those (comma-separated)
        if (isset($options['vault']) && $options['vault']) {
            return array_map('trim', explode(',', $options['vault']));
        }

        // Otherwise, use ALL configured vaults
        return $this->secretLoader->getAllVaultNames();
    }

    protected function formatOutput(SecretCollection $secrets, string $format): string
    {
        return $format === 'json'
            ? $secrets
                ->toKeyValuePair()
                ->toJson(JSON_PRETTY_PRINT)
            : $secrets->toEnvString();
    }
}
