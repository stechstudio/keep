<?php

namespace STS\Keep\Exceptions;

use RuntimeException;
use Throwable;

class KeepException extends RuntimeException
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function renderConsole(callable $output): void
    {
        $output($this->getMessage(), 'error');

        // Build context details
        $contextLines = array_filter([
            'Vault' => $this->context['vault'] ?? null,
            'Env' => $this->context['env'] ?? null,
            'Key' => $this->context['key'] ?? null,
            'Path' => $this->context['path'] ?? null,
            'Template line' => $this->context['lineNumber'] ?? null,
        ]);

        $contextLines = array_map(fn ($k, $v) => "  $k: $v", array_keys($contextLines), $contextLines);

        // Output context if we have any
        if (! empty($contextLines)) {
            foreach ($contextLines as $line) {
                $output($line);
            }
        }

        // Output additional details if provided
        if (isset($this->context['details'])) {
            $output('');
            $output($this->context['details']);
        }

        // Output suggestion if provided
        if (isset($this->context['suggestion'])) {
            $output('');
            $output('💡  '.$this->context['suggestion'], 'comment');
        }
    }
}
