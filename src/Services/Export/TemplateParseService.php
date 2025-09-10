<?php

namespace STS\Keep\Services\Export;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Env;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Services\OutputWriter;
use Symfony\Component\Console\Output\OutputInterface;

class TemplateParseService
{
    public function __construct(
        protected OutputWriter $outputWriter
    ) {}

    public function handle(array $options, OutputInterface $output): int
    {
        $template = $this->loadTemplate($options['template'], $output);

        $vaultNames = $this->determineVaults($options, $template);

        $env = $options['env'];
        $allSecrets = SecretCollection::loadFromVaults($vaultNames, $env);

        $allSecrets = $allSecrets->filterByPatterns(
            only: $options['only'] ?? null,
            except: $options['except'] ?? null
        );

        $missingStrategy = MissingSecretStrategy::from($options['missing'] ?? 'fail');

        $templateData = $this->parseTemplate(
            $template,
            $allSecrets,
            $missingStrategy,
            $options['all'] ?? false
        );

        $format = strtoupper($options['format'] ?? 'json');
        $output->writeln("<info>Processing template [{$options['template']}] for environment '{$env}' as {$format}...</info>");
        if ($options['all'] ?? false) {
            $output->writeln('<info>Including all additional secrets beyond template placeholders</info>');
        }
        $formattedOutput = match ($options['format'] ?? 'json') {
            'csv' => $this->formatAsCsv($templateData, $allSecrets),
            default => json_encode($templateData, JSON_PRETTY_PRINT),
        };

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

    protected function formatAsCsv(array $templateData, SecretCollection $allSecrets): string
    {
        $csv = "Key,Value,Vault,Env,Modified\n";
        
        foreach ($templateData as $key => $value) {
            // Find the secret in the collection to get metadata
            $secret = $allSecrets->getByKey($key);
            
            $csvKey = $this->escapeCsvField($key);
            $csvValue = $this->escapeCsvField($value);
            $vault = $secret ? $this->escapeCsvField($secret->vault()?->name() ?? '') : '';
            $env = $secret ? $this->escapeCsvField($secret->env() ?? '') : '';
            $modified = $secret && $secret->lastModified() ? $this->escapeCsvField($secret->lastModified()->toIso8601String()) : '';
            
            $csv .= "{$csvKey},{$csvValue},{$vault},{$env},{$modified}\n";
        }
        
        return $csv;
    }

    protected function escapeCsvField(string $field): string
    {
        // If field contains comma, quotes, or newline, wrap in quotes and escape quotes
        if (preg_match('/[,"\n\r]/', $field)) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
}
