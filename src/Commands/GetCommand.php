<?php

namespace STS\Keep\Commands;

class GetCommand extends AbstractCommand
{
    public $signature = 'keep:get {--format=table : table|json|raw} '
        .self::KEY_SIGNATURE
        .self::VAULT_SIGNATURE
        .self::STAGE_SIGNATURE;

    public $description = 'Get the value of a stage secret in a specified vault';

    public function process(): int
    {
        $secret = $this->vault()->get($this->key());

        match ($this->option('format')) {
            'table' => $this->table(['Key', 'Value', 'Rev'], [$secret->only(['key', 'value', 'revision'])]),
            'json' => $this->line(json_encode($secret->only(['key', 'value', 'revision']), JSON_PRETTY_PRINT)),
            'raw' => $this->line($secret->value()),
            default => $this->error('Invalid format option. Supported formats are: table, json, raw.'),
        };

        return self::SUCCESS;
    }
}
