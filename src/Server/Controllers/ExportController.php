<?php

namespace STS\Keep\Server\Controllers;

class ExportController extends ApiController
{
    public function export(): array
    {
        $vault = $this->getVault();
        $format = $this->body['format'] ?? 'env';
        
        $secrets = $vault->list();
        
        if ($format === 'json') {
            $output = json_encode(
                $secrets->mapWithKeys(fn($s) => [$s->key() => $s->value()])->toArray(),
                JSON_PRETTY_PRINT
            );
        } else {
            $output = $secrets->map(fn($s) => "{$s->key()}=\"{$s->value()}\"")->join("\n");
        }
        
        return $this->success([
            'content' => $output,
            'format' => $format,
            'count' => $secrets->count()
        ]);
    }
}