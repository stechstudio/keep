<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\table;

class GetCommand extends BaseCommand
{
    public $signature = 'get 
        {key? : The secret key}
        {--format=table : table|json|raw} 
        {--vault= : The vault to use}
        {--stage= : The stage to use}';

    public $description = 'Get the value of a stage secret in a specified vault';

    public function process()
    {
        $context = $this->vaultContext();
        $secret = $context->createVault()->get($this->key());

        match ($this->option('format')) {
            'table' => table(['Key', 'Value', 'Rev'], [$secret->forTable()]),
            'json' => $this->line(json_encode($secret->only(['key', 'value', 'revision']), JSON_PRETTY_PRINT)),
            'raw' => $this->line($secret->value()),
            default => $this->error('Invalid format option. Supported formats are: table, json, raw.'),
        };
    }
}
