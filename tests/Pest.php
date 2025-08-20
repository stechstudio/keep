<?php

use Symfony\Component\Console\Tester\CommandTester;
use STS\Keep\KeepApplication;
use STS\Keep\Enums\KeepInstall;

uses()->in('Feature');
uses()->in('Unit');

// Set environment variable to disable Laravel Prompts interactivity during tests
putenv('LARAVEL_PROMPTS_INTERACT=0');

/**
 * Helper to create a KeepApplication instance for testing
 */
function createKeepApp(): KeepApplication
{
    return new KeepApplication(KeepInstall::LOCAL);
}

/**
 * Helper to run a command and return the CommandTester
 */
function runCommand(string $commandName, array $input = []): CommandTester
{
    $app = createKeepApp();
    $command = $app->find($commandName);
    $commandTester = new CommandTester($command);
    
    // Set non-interactive mode to prevent prompts during testing
    $input['--no-interaction'] = true;
    
    $commandTester->execute($input);
    
    return $commandTester;
}

/**
 * Helper to create a temporary directory for testing
 */
function createTempKeepDir(): string
{
    $tempDir = sys_get_temp_dir() . '/keep-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    
    // Change to temp directory for tests
    chdir($tempDir);
    
    return $tempDir;
}

/**
 * Helper to clean up temp directory
 */
function cleanupTempDir(string $tempDir): void
{
    if (is_dir($tempDir)) {
        exec("rm -rf " . escapeshellarg($tempDir));
    }
}

/**
 * Helper to strip ANSI codes from command output
 */
function stripAnsi(string $text): string
{
    return preg_replace('/\x1b\[[0-9;]*m/', '', $text);
}