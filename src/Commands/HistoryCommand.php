<?php

namespace STS\Keep\Commands;

use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Filters\DateFilter;
use STS\Keep\Data\Filters\StringFilter;
use STS\Keep\Data\SecretHistory;

use function Laravel\Prompts\table;

class HistoryCommand extends BaseCommand
{
    public $signature = 'history 
        {--limit=10 : Maximum number of history entries to return} 
        {--format=table : table|json}
        {--user= : Filter by user who modified the secret (partial match)}
        {--since= : Filter entries since this date (e.g., "7 days ago", "2024-01-01")}
        {--before= : Filter entries before this date (e.g., "2024-12-31")} '
        .self::KEY_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE
        .self::UNMASK_SIGNATURE;

    public $description = 'Display change history for a secret';

    public function process()
    {
        $key = $this->key();
        $limit = (int) $this->option('limit');
        $context = $this->context();
        $vault = $context->createVault();

        $historyCollection = $vault->history($key, new FilterCollection(array_filter([
            'user' => $this->option('user') ? new StringFilter($this->option('user')) : null,
            'since' => $this->option('since') ? new DateFilter($this->option('since')) : null,
            'before' => $this->option('before') ? new DateFilter($this->option('before')) : null,
        ])), $limit);

        if ($historyCollection->isEmpty()) {
            // For JSON format, output empty array; for table format, show message
            if ($this->option('format') === 'json') {
                $this->line('[]');
            } else {
                $this->info("No history found for secret [{$key}]");
            }

            return self::SUCCESS;
        }

        // Apply masking if not unmasked
        if (! $this->option('unmask')) {
            $historyCollection = $historyCollection->withMaskedValues();
        }

        $result = match ($this->option('format')) {
            'table' => $this->displayTable($historyCollection, $key),
            'json' => $this->displayJson($historyCollection),
            default => false,
        };

        if ($result === false) {
            $this->error('Invalid format option. Supported formats are: table, json.');

            return self::FAILURE;
        }
    }

    protected function displayTable(SecretHistoryCollection $historyCollection, string $key): bool
    {
        $this->line("History for secret: <info>{$key}</info>");

        $rows = $historyCollection->map(function (SecretHistory $history) {
            return [
                'Version' => $history->version(),
                'Value' => $history->value() ?? '<null>',
                'Type' => $history->dataType(),
                'Modified Date' => $history->formattedDate(),
                'Modified By' => $history->lastModifiedUser() ?? '<unknown>',
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
