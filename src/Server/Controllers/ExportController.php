<?php

namespace STS\Keep\Server\Controllers;

class ExportController extends ApiController
{
    public function export(): array
    {
        $vault = $this->getVault();
        $format = $this->body['format'] ?? 'env';
        
        $secrets = $vault->list();
        
        // Use the existing SecretCollection formatting methods
        $output = match ($format) {
            'json' => $secrets->toKeyValuePair()->toJson(JSON_PRETTY_PRINT),
            'csv' => $secrets->toCsvString(),
            default => $secrets->toEnvString(),
        };
        
        return $this->success([
            'content' => $output,
            'format' => $format,
            'count' => $secrets->count()
        ]);
    }
}