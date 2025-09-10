<?php

namespace STS\Keep\Services\Export;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Services\OutputWriter;
use Symfony\Component\Console\Output\OutputInterface;

class TemplatePreserveService
{
    public function __construct(
        protected OutputWriter $outputWriter
    ) {}

    /**
     * Handle template export with structure preservation (env format).
     */
    public function handle(array $options, OutputInterface $output): int
    {
        // Load template
        $template = $this->loadTemplate($options['template'], $output);

        // Determine which vaults to load from
        $vaultNames = $this->determineVaults($options, $template);

        // Load secrets
        $env = $options['env'];
        $allSecrets = SecretCollection::loadFromVaults($vaultNames, $env);

        // Apply filters
        $allSecrets = $allSecrets->filterByPatterns(
            only: $options['only'] ?? null,
            except: $options['except'] ?? null
        );

        // Get missing secret strategy
        $missingStrategy = MissingSecretStrategy::from($options['missing'] ?? 'fail');

        // Process template
        $contents = $this->processTemplate(
            $template,
            $allSecrets,
            $missingStrategy,
            $options['all'] ?? false
        );

        // Output info
        $output->writeln("<info>Processing template [{$options['template']}] for environment '{$env}'...</info>");
        if ($options['all'] ?? false) {
            $output->writeln('<info>Including all additional secrets beyond template placeholders</info>');
        }

        // Write output
        if ($options['file']) {
            $this->outputWriter->write(
                $options['file'],
                $contents,
                $options['overwrite'] ?? false,
                $options['append'] ?? false
            );
            $output->writeln("<info>Secrets exported to [{$options['file']}].</info>");
        } else {
            $output->writeln($contents);
        }

        return 0;
    }

    protected function loadTemplate(string $path, OutputInterface $output): Template
    {
        if (! file_exists($path) || ! is_readable($path)) {
            throw new \RuntimeException("Template file [$path] does not exist or is not readable.");
        }

        $output->writeln("<info>Using template file [$path].</info>");

        return new Template(file_get_contents($path));
    }

    protected function determineVaults(array $options, Template $template): array
    {
        // If --vault explicitly specified, use those
        if (isset($options['vault']) && $options['vault']) {
            return array_map('trim', explode(',', $options['vault']));
        }

        // Otherwise, auto-discover from template
        return $template->allReferencedVaults();
    }

    protected function processTemplate(
        Template $template,
        $allSecrets,
        MissingSecretStrategy $strategy,
        bool $includeAll
    ): string {
        // Process template
        $output = $template->merge($allSecrets, $strategy);

        // If --all flag, append remaining secrets
        if ($includeAll) {
            // Track which keys were used in template
            $usedKeys = [];
            foreach ($template->placeholders() as $placeholder) {
                $usedKeys[] = $placeholder->path ?: $placeholder->key;
            }

            $unusedSecrets = $allSecrets->reject(function ($secret) use ($usedKeys) {
                return in_array($secret->key(), $usedKeys);
            });

            if ($unusedSecrets->isNotEmpty()) {
                $output .= "\n\n# ----- Additional secrets not in template -----\n\n";
                $output .= $unusedSecrets->toEnvString();
            }
        }

        return $output;
    }
}
