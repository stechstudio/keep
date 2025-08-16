<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Commands\Concerns\InteractsWithVaults;
use STS\Keeper\Commands\Concerns\WritesOutputToFile;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Secret;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class MergeSecretsCommand extends Command
{
    use GathersInput, InteractsWithVaults, WritesOutputToFile;

    public $signature = 'keeper:merge {template? : Template env file with placeholders}
        {--overlay= : Optional env file to overlay on top of the template} 
        {--output= : File where to save the output (defaults to stdout)} 
        {--overwrite : Overwrite the output file if it exists} 
        {--append : Append to the output file if it exists} '
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE;

    public $description = 'Merge environment secrets into a template env file, replacing placeholders with actual secret values from a specified vault';

    protected string $templateContents = '';
    protected string $overlayContents = '';

    public function handle(): int
    {
        if(!$this->prepareTemplateContents()) {
            return self::FAILURE;
        }

        try {
            $secrets = $this->vault()->list();
        } catch (KeeperException $e) {
            $this->error(
                sprintf("Failed to get secrets in vault [%s]",
                    $this->vaultName()
                )
            );
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        if($this->option('output')) {
            return $this->writeToFile(
                $this->option('output'),
                $this->performMerge($secrets),
                $this->option('overwrite'),
                $this->option('append')
            );
        }

        $this->line($this->performMerge($secrets));

        return self::SUCCESS;
    }

    protected function prepareTemplateContents(): bool
    {
        $template = $this->argument('template') ?? text('Template file with placeholders', required: true);

        if (!file_exists($template) || !is_readable($template)) {
            $this->error("Template file [$template] does not exist or is not readable.");
            return false;
        }

        $this->info("Using base template file [$template].");
        $this->templateContents = file_get_contents($template);

        if($this->option('overlay')) {
            $overlay = $this->option('overlay');

            if (!file_exists($overlay) || !is_readable($overlay)) {
                $this->error("Overlay file [$overlay] does not exist or is not readable.");
                return false;
            }

            $this->info("Using overlay file [$overlay].");
            $this->overlayContents = file_get_contents($overlay);
        }

        return true;
    }

    protected function performMerge(Collection $secrets): string
    {
        $output = $this->templateContents;

        return $output;
    }
}
