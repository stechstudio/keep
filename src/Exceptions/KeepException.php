<?php

namespace STS\Keep\Exceptions;

use RuntimeException;
use Throwable;

class KeepException extends RuntimeException
{
    protected ?string $vault = null;

    protected ?string $stage = null;

    protected ?string $key = null;

    protected ?string $path = null;

    protected ?int $lineNumber = null;

    protected ?string $suggestion = null;

    protected ?string $details = null;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function withContext(
        ?string $vault = null,
        ?string $stage = null,
        ?string $key = null,
        ?string $path = null,
        ?int $lineNumber = null,
        ?string $suggestion = null,
        ?string $details = null
    ): static {
        if ($vault !== null) $this->vault = $vault;
        if ($stage !== null) $this->stage = $stage;
        if ($key !== null) $this->key = $key;
        if ($path !== null) $this->path = $path;
        if ($lineNumber !== null) $this->lineNumber = $lineNumber;
        if ($suggestion !== null) $this->suggestion = $suggestion;
        if ($details !== null) $this->details = $details;

        return $this;
    }

    public function renderConsole(callable $output): void
    {
        $output($this->getMessage(), 'error');

        // Build context details
        $contextLines = array_filter([
            'Vault' => $this->vault,
            'Stage' => $this->stage,
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
            $output('💡  '.$this->suggestion, 'comment');
        }
    }
}
