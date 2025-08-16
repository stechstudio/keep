<?php

namespace STS\Keeper\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use STS\Keeper\Commands\Concerns\GathersInput;
use STS\Keeper\Commands\Concerns\InteractsWithVaults;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Secret;
use function Laravel\Prompts\confirm;

class ExportSecretsCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keeper:export 
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
        } catch (KeeperException $e) {
            $this->error(
                sprintf("Failed to get secrets in vault [%s]",
                    $this->vaultName()
                )
            );
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $output = $this->formatOutput($secrets);

        if($this->option('output')) {
            return $this->writeToFile($output);
        }

        $this->line($output);

        return self::SUCCESS;
    }

    protected function formatOutput(Collection $secrets): string
    {
        return $this->option('format') === 'json'
            ? $secrets
                ->mapWithKeys(fn(Secret $secret) => [$secret->key() => $secret->plainValue()])
                ->toJson(JSON_PRETTY_PRINT)
            : $secrets->map(function (Secret $secret) {
                return sprintf('%s="%s"', $secret->key(), $secret->plainValue());
            })->implode("\n");
    }

    protected function writeToFile(string $output): bool
    {
        $filePath = $this->option('output');
        $flags = 0;

        if (file_exists($filePath)) {
            if ($this->option('overwrite')) {
                $flags = 0; // Overwrite
            } elseif ($this->option('append')) {
                $flags = FILE_APPEND; // Append
            } else if(confirm("Output file already exists. Overwrite?", false)) {
                $flags = 0; // Overwrite
            } else {
                $this->error("File [$filePath] already exists. Use --overwrite or --append option.");
                return self::FAILURE;
            }
        }

        file_put_contents($filePath, $output . PHP_EOL, $flags);
        $this->info("Secrets exported to [$filePath].");

        return self::SUCCESS;
    }
}
