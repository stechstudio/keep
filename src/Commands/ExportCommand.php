<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Collections\SecretCollection;

class ExportCommand extends AbstractCommand
{
    public $signature = 'keep:export 
        {--format=env : json|env} 
        {--output= : File where to save the output (defaults to stdout)} 
        {--overwrite : Overwrite the output file if it exists} 
        {--append : Append to the output file if it exists} '
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Export all stage secrets in a specified vault';

    public function process(): int
    {
        $secrets = $this->vault()->list();

        if ($this->option('output')) {
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

    protected function formatOutput(SecretCollection $secrets): string
    {
        return $this->option('format') === 'json'
            ? $secrets
                ->toKeyValuePair()
                ->toJson(JSON_PRETTY_PRINT)
            : $secrets->toEnvString();
    }
}
