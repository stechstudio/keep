<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithVaults;
use STS\Keep\Commands\Concerns\InteractsWithFilesystem;
use STS\Keep\Data\SecretsCollection;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Data\Secret;

class ExportCommand extends Command
{
    use GathersInput, InteractsWithVaults, InteractsWithFilesystem;

    public $signature = 'keep:export 
        {--format=env : json|env} 
        {--output= : File where to save the output (defaults to stdout)} 
        {--overwrite : Overwrite the output file if it exists} 
        {--append : Append to the output file if it exists} '
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE;

    public $description = 'Export all environment secrets in a specified vault';

    public function handle(): int
    {
        try {
            $secrets = $this->vault()->list();
        } catch (KeepException $e) {
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
                $this->formatOutput($secrets),
                $this->option('overwrite'),
                $this->option('append')
            );
        }

        $this->line($this->formatOutput($secrets));

        return self::SUCCESS;
    }

    protected function formatOutput(SecretsCollection $secrets): string
    {
        return $this->option('format') === 'json'
            ? $secrets
                ->toKeyValuePair()
                ->toJson(JSON_PRETTY_PRINT)
            : $secrets->map(function (Secret $secret) {
                return sprintf('%s="%s"', $secret->key(), $secret->plainValue());
            })->implode("\n");
    }
}
