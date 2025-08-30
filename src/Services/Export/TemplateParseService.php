<?php

namespace STS\Keep\Services\Export;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Env;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Services\OutputWriter;
use STS\Keep\Services\SecretLoader;
use STS\Keep\Services\VaultDiscovery;
use Symfony\Component\Console\Output\OutputInterface;

class TemplateParseService
{
    public function __construct(
        protected SecretLoader $secretLoader,
        protected VaultDiscovery $vaultDiscovery,
        protected OutputWriter $outputWriter
    ) {}

    /**
     * Handle template export with parsing (JSON format).
     */
    public function handle(array $options, OutputInterface $output): int
    {
        // Load template
        $template = $this->loadTemplate($options['template'], $output);

        // Determine which vaults to load from
        $vaultNames = $this->determineVaults($options, $template);

        // Load secrets
        $stage = $options['stage'];
        $allSecrets = $this->secretLoader->loadFromVaults($vaultNames, $stage);

        // Apply filters
        $allSecrets = $allSecrets->filterByPatterns(
            only: $options['only'] ?? null,
            except: $options['except'] ?? null
        );

        // Get missing secret strategy
        $missingStrategy = MissingSecretStrategy::from($options['missing'] ?? 'fail');

        // Parse template and extract keys
        $templateData = $this->parseTemplate(
            $template,
            $allSecrets,
            $missingStrategy,
            $options['all'] ?? false
        );

        // Output info
        $output->writeln("<info>Processing template [{$options['template']}] for stage '{$stage}' as JSON...</info>");
        if ($options['all'] ?? false) {
            $output->writeln('<info>Including all additional secrets beyond template placeholders</info>');
        }

        // Format as JSON (sorted by key)
        $jsonOutput = json_encode($templateData, JSON_PRETTY_PRINT);

        // Write output
        if ($options['file']) {
            $this->outputWriter->write(
                $options['file'],
                $jsonOutput,
                $options['overwrite'] ?? false,
                $options['append'] ?? false
            );
            $output->writeln("<info>Secrets exported to [{$options['file']}].</info>");
        } else {
            $output->writeln($jsonOutput);
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
        return $this->vaultDiscovery->discoverFromTemplate($template);
    }

    protected function parseTemplate(
        Template $template,
        SecretCollection $allSecrets,
        MissingSecretStrategy $strategy,
        bool $includeAll
    ): array {
        $result = [];
        $usedKeys = [];

        // Process template - merge placeholders then parse
        $merged = $template->merge($allSecrets, $strategy);
        $env = new Env($merged);

        foreach ($env->list() as $key => $value) {
            $result[$key] = $value;
        }

        // Track which keys came from placeholders
        foreach ($template->placeholders() as $placeholder) {
            $usedKeys[] = $placeholder->path ?: $placeholder->key;
        }

        // If --all flag, include remaining secrets
        if ($includeAll) {
            $unusedSecrets = $allSecrets->reject(function ($secret) use ($usedKeys) {
                return in_array($secret->key(), $usedKeys);
            });

            foreach ($unusedSecrets as $secret) {
                $result[$secret->key()] = $secret->value();
            }
        }

        // Sort by key for clean output
        ksort($result);

        return $result;
    }
}
