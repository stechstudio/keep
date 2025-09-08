<?php

namespace STS\Keep\Services;

use Symfony\Component\Process\Process;
use STS\Keep\Exceptions\ProcessExecutionException;

class ProcessRunner
{
    /**
     * Execute command with custom environment
     *
     * @param array $command Command and arguments
     * @param array $environment Environment variables
     * @param string|null $workingDirectory Working directory
     * @return ProcessResult
     * @throws ProcessExecutionException
     */
    public function run(
        array $command,
        array $environment,
        ?string $workingDirectory = null
    ): ProcessResult {
        $process = new Process(
            $command,
            $workingDirectory,
            $environment
        );
        
        // Disable timeout for long-running processes
        $process->setTimeout(null);
        
        // Enable TTY mode if available (for interactive commands)
        if (Process::isTtySupported()) {
            $process->setTty(true);
        }
        
        try {
            // Run the process with real-time output
            $process->run(function ($type, $buffer) {
                // Stream output directly to stdout/stderr
                if ($type === Process::ERR) {
                    fwrite(STDERR, $buffer);
                } else {
                    fwrite(STDOUT, $buffer);
                }
            });
        } catch (\Exception $e) {
            throw new ProcessExecutionException(
                "Failed to execute command: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        
        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            successful: $process->isSuccessful()
        );
    }
    
    /**
     * Check if a command exists in the system
     *
     * @param string $command The command to check
     * @return bool
     */
    public function commandExists(string $command): bool
    {
        // Use 'which' on Unix-like systems, 'where' on Windows
        $checkCommand = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        
        $process = new Process([$checkCommand, $command]);
        $process->run();
        
        return $process->isSuccessful();
    }
}

/**
 * Result object for process execution
 */
class ProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly bool $successful
    ) {}
}