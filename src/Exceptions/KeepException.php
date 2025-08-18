<?php

namespace STS\Keep\Exceptions;

use RuntimeException;
use Throwable;

class KeepException extends RuntimeException
{
    protected string $details = '';

    protected ?string $vault = null;

    protected ?string $environment = null;

    protected ?string $key = null;

    protected ?string $path = null;

    protected ?int $lineNumber = null;

    protected ?string $suggestion = null;

    public function __construct(string $message = '', string $details = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function withContext(
        ?string $vault = null,
        ?string $environment = null,
        ?string $key = null,
        ?string $path = null,
        ?int $lineNumber = null,
        ?string $suggestion = null
    ): static {
        $this->vault = $vault;
        $this->environment = $environment;
        $this->key = $key;
        $this->path = $path;
        $this->lineNumber = $lineNumber;
        $this->suggestion = $suggestion;

        return $this;
    }

    public function renderConsole(callable $output): void
    {
        $output($this->getMessage(), 'error');

        // Build context details
        $contextLines = array_filter([
            'Vault' => $this->vault,
            'Environment' => $this->environment,
            'Key' => $this->key,
            'Path' => $this->path,
            'Template line' => $this->lineNumber,
        ]);

        $contextLines = array_map(fn ($k, $v) => "  $k: $v", array_keys($contextLines), $contextLines);

        // Output context if we have any
        if (! empty($contextLines)) {
            foreach ($contextLines as $line) {
                $output($line);
            }
        }

        // Output additional details if provided
        if ($this->details) {
            $output('');
            $output($this->details);
        }

        // Output suggestion if provided
        if ($this->suggestion) {
            $output('');
            $output('💡 '.$this->suggestion, 'comment');
        }
    }
}
