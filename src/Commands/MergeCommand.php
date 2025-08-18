<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\SecretsCollection;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;

use function Laravel\Prompts\text;

class MergeCommand extends AbstractCommand
{
    public $signature = 'keep:merge {template? : Template env file with placeholders}
        {--overlay= : Optional env file to overlay on top of the template} 
        {--output= : File where to save the output (defaults to stdout)} 
        {--overwrite : Overwrite the output file if it exists} 
        {--append : Append to the output file if it exists} 
        {--missing=fail : How to handle missing secrets: fail|remove|blank|skip (default: fail) } '
        .self::VAULT_SIGNATURE
        .self::ENV_SIGNATURE;

    public $description = 'Merge environment secrets into a template env file, replacing placeholders with actual secret values from a specified vault';

    protected Template $baseTemplate;

    protected Template $overlayTemplate;

    public function process(): int
    {
        if (! $this->prepareTemplateContents()) {
            return self::FAILURE;
        }

        $secrets = $this->vault()->list();

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

        return self::SUCCESS;
    }

    protected function prepareTemplateContents(): bool
    {
        $template = $this->argument('template')
            ?? config('keep.template')
            ?? text('Template file with placeholders', required: true);

        if (! file_exists($template) || ! is_readable($template)) {
            $this->error("Template file [$template] does not exist or is not readable.");

            return false;
        }

        $this->info("Using base template file [$template].");
        $this->baseTemplate = new Template(file_get_contents($template));

        $overlayTemplate = $this->option('overlay') ?? $this->findEnvironmentOverlayTemplate();

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

    protected function mergeAndConcat(Template $base, Template $overlay, SecretsCollection $secrets, MissingSecretStrategy $strategy): string
    {
        $output = "# ----- Base environment variables -----\n\n";
        $output .= $base->merge($this->vault()->slug(), $secrets, $strategy);

        if ($overlay->isNotEmpty()) {
            $output .= "\n\n# ----- Separator -----\n\n# Appending additional environment variables\n\n";
            $output .= $overlay->merge($this->vault()->slug(), $secrets, $strategy);
        }

        return $output;
    }
}
