<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\SecretHistory;

use function Laravel\Prompts\table;

class HistoryCommand extends AbstractCommand
{
    public $signature = 'keep:history 
        {--limit=10 : Maximum number of history entries to return} 
        {--format=table : table|json} '
        .self::KEY_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::ENV_SIGNATURE
        .self::UNMASK_SIGNATURE;

    public $description = 'Display change history for a secret';

    public function process(): int
    {
        $key = $this->key();
        $limit = (int) $this->option('limit');

        $history = $this->vault()->history($key, $limit);

        if ($history->isEmpty()) {
            $this->info("No history found for secret [{$key}]");

            return self::SUCCESS;
        }

        // Apply masking if not unmasked
        if (! $this->option('unmask')) {
            $history = $history->map->withMaskedValue();
        }

        $result = match ($this->option('format')) {
            'table' => $this->displayTable($history, $key),
            'json' => $this->displayJson($history),
            default => false,
        };

        if ($result === false) {
            $this->error('Invalid format option. Supported formats are: table, json.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function displayTable($history, string $key): bool
    {
        $timezone = config('keep.display_timezone', config('app.timezone', 'UTC'));

        $this->line("History for secret: <info>{$key}</info>");

        $rows = $history->map(function (SecretHistory $entry) use ($timezone) {
            return [
                'Version' => $entry->version(),
                'Value' => $entry->value() ?? '<null>',
                'Type' => $entry->dataType(),
                'Modified Date' => $entry->formattedDate($timezone),
                'Modified By' => $entry->lastModifiedUser() ?? '<unknown>',
            ];
        })->toArray();

        table(
            ['Version', 'Value', 'Type', 'Modified Date', 'Modified By'],
            $rows
        );

        return true;
    }

    protected function displayJson($history): bool
    {
        $this->line($history->toJson(JSON_PRETTY_PRINT));

        return true;
    }
}
