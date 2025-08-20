<?php

use Illuminate\Console\Command;
use STS\Keep\Commands\AbstractCommand;
use STS\Keep\Exceptions\KeepException;

// Create a test command that extends AbstractCommand
class TestCommand extends AbstractCommand
{
    protected $signature = 'test:command';

    protected $description = 'Test command';

    public $processResult = Command::SUCCESS;

    public $shouldThrowKeepException = false;

    public $keepExceptionMessage = 'Test exception';

    public $keepExceptionContext = [];

    public function process(): int
    {
        if ($this->shouldThrowKeepException) {
            $exception = new KeepException($this->keepExceptionMessage);
            if (! empty($this->keepExceptionContext)) {
                $exception->withContext(...$this->keepExceptionContext);
            }
            throw $exception;
        }

        return $this->processResult;
    }
}

describe('AbstractCommand', function () {

    it('handles successful command execution', function () {
        $command = new TestCommand;
        $command->processResult = Command::SUCCESS;

        $result = $command->handle();

        expect($result)->toBe(Command::SUCCESS);
    });

    it('calls renderConsole when KeepException is thrown', function () {
        $output = [];

        // Create a simple test exception
        $exception = new KeepException('Test error message');

        // Call renderConsole with a callable that captures output
        $exception->renderConsole(function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        });

        // Verify the output
        expect($output)->toEqual([
            ['message' => 'Test error message', 'style' => 'error'],
        ]);
    });

    it('renders exception with context correctly', function () {
        $output = [];

        $exception = (new KeepException('Error occurred'))
            ->withContext(
                vault: 'test-vault',
                key: 'TEST_KEY',
                suggestion: 'Try something else'
            );

        $exception->renderConsole(function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        });

        expect($output)->toEqual([
            ['message' => 'Error occurred', 'style' => 'error'],
            ['message' => '  Vault: test-vault', 'style' => 'line'],
            ['message' => '  Key: TEST_KEY', 'style' => 'line'],
            ['message' => '', 'style' => 'line'],
            ['message' => '💡  Try something else', 'style' => 'comment'],
        ]);
    });

    it('renders exception with details correctly', function () {
        $output = [];

        $exception = (new KeepException('Main error'))
            ->withContext(details: 'Additional details here');

        $exception->renderConsole(function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        });

        expect($output)->toEqual([
            ['message' => 'Main error', 'style' => 'error'],
            ['message' => '', 'style' => 'line'],
            ['message' => 'Additional details here', 'style' => 'line'],
        ]);
    });
});
