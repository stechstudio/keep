<?php

namespace STS\Keep\Exceptions;

use RuntimeException;
use Throwable;

class KeepException extends RuntimeException
{
    protected string $details = '';

    public function __construct(string $message = "", string $details = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function renderConsole($command): void
    {
        $command->error($this->getMessage());
        if ($this->details) {
            $command->line($this->details);
        }
    }
}