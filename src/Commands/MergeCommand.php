<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;

use STS\Keep\Facades\Keep;
use function Laravel\Prompts\text;

class MergeCommand extends BaseCommand
{
    public $signature = 'merge {template? : Template env file with placeholders}
        {--overlay= : Optional env file to overlay on top of the template} 
        {--output= : File where to save the output (defaults to stdout)} 
        {--overwrite : Overwrite the output file if it exists} 
        {--append : Append to the output file if it exists} 
        {--missing=fail : How to handle missing secrets: fail|remove|blank|skip (default: fail) } '
        .self::CONTEXT_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Merge stage secrets into a template env file, replacing placeholders with actual secret values from a specified vault';

    protected Template $baseTemplate;

    protected Template $overlayTemplate;

    public function process()
    {
        if (! $this->prepareTemplateContents()) {
            return self::FAILURE;
        }

        $context = $this->context();
        $secrets = $this->loadSecretsFromAllReferencedVaults($context);

        $contents = $this->mergeAndConcat(
            $this->baseTemplate,
            $this->overlayTemplate,
            $secrets,
            MissingSecretStrategy::from($this->option('missing'))
        );

        if ($this->option('output')) {
            return $this->writeToFile(
                $this->option('output'),
                $contents,
                $this->option('overwrite'),
                $this->option('append')
            );
        }

        $this->line($contents);
    }

    protected function prepareTemplateContents(): bool
    {
        $template = $this->argument('template')
            ?? Keep::getSetting('template')
            ?? text('Template file with placeholders', required: true);

        if (! file_exists($template) || ! is_readable($template)) {
            $this->error("Template file [$template] does not exist or is not readable.");

            return false;
        }

        $this->info("Using base template file [$template].");
        $this->baseTemplate = new Template(file_get_contents($template));

        $overlayTemplate = $this->option('overlay') ?? $this->findStageOverlayTemplate();

        if ($overlayTemplate) {
            if (! file_exists($overlayTemplate) || ! is_readable($overlayTemplate)) {
                $this->error("Overlay file [$overlayTemplate] does not exist or is not readable.");

                return false;
            }

            $this->info("Using overlay file [$overlayTemplate].");
            $this->overlayTemplate = new Template(file_get_contents($overlayTemplate));
        } else {
            $this->overlayTemplate = new Template('');
        }

        return true;
    }

    protected function mergeAndConcat(Template $base, Template $overlay, SecretCollection $secrets, MissingSecretStrategy $strategy): string
    {
        $output = "# ----- Base stage variables -----\n\n";
        $output .= $base->merge($secrets, $strategy);

        if ($overlay->isNotEmpty()) {
            $output .= "\n\n# ----- Separator -----\n\n# Appending additional stage variables\n\n";
            $output .= $overlay->merge($secrets, $strategy);
        }

        return $output;
    }

    /**
     * Load secrets from all vaults referenced in template placeholders.
     */
    protected function loadSecretsFromAllReferencedVaults($context): SecretCollection
    {
        $vaultNames = $this->extractVaultNamesFromTemplates();
        
        // If no vault-specific placeholders found, fall back to context vault
        if (empty($vaultNames)) {
            $vault = $context->createVault();
            return $vault->list();
        }
        
        $allSecrets = new SecretCollection();
        
        foreach ($vaultNames as $vaultSlug) {
            $vault = Keep::vault($vaultSlug, $context->stage);
            $vaultSecrets = $vault->list();
            $allSecrets = $allSecrets->merge($vaultSecrets);
        }
        
        return $allSecrets;
    }

    /**
     * Extract unique vault slugs from all template placeholders.
     */
    protected function extractVaultNamesFromTemplates(): array
    {
        return array_unique(array_merge(
            $this->baseTemplate->allReferencedVaults(),
            $this->overlayTemplate->allReferencedVaults()
        ));
    }
}
