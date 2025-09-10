<?php

namespace STS\Keep\Services\Export;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\OutputWriter;
use Symfony\Component\Console\Output\OutputInterface;

class DirectExportService
{
    public function __construct(
        protected OutputWriter $outputWriter
    ) {}

    public function handle(array $options, OutputInterface $output): int
    {
        $env = $options['env'];
        $vaultNames = $this->getVaultNames($options);

        $allSecrets = SecretCollection::loadFromVaults($vaultNames, $env);

        $allSecrets = $allSecrets->filterByPatterns(
            only: $options['only'] ?? null,
            except: $options['except'] ?? null
        );

        $formattedOutput = $this->formatOutput($allSecrets, $options['format']);
        if (count($vaultNames) === 1) {
            $output->writeln("<info>Exporting secrets from vault '{$vaultNames[0]}' for environment '{$env}'...</info>");
        } else {
            $output->writeln('<info>Exporting secrets from '.count($vaultNames).' vaults ('.implode(', ', $vaultNames).") for environment '{$env}'...</info>");
        }
        $output->writeln('<info>Found '.$allSecrets->count().' total secrets to export</info>');

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
        return Keep::getConfiguredVaults()->keys()->toArray();
    }

    protected function formatOutput(SecretCollection $secrets, string $format): string
    {
        return match ($format) {
            'json' => $secrets->toKeyValuePair()->toJson(JSON_PRETTY_PRINT),
            'csv' => $secrets->toCsvString(),
            default => $secrets->toEnvString(),
        };
    }
}
