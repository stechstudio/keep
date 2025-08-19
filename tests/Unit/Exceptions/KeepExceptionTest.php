<?php

use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;

describe('KeepException', function () {

    it('renders basic error message to console', function () {
        $output = [];
        $outputCallback = function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        };

        $exception = new KeepException('Basic error message');
        $exception->renderConsole($outputCallback);

        expect($output)->toEqual([
            ['message' => 'Basic error message', 'style' => 'error'],
        ]);
    });

    it('renders error with details', function () {
        $output = [];
        $outputCallback = function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        };

        $exception = new KeepException('Error occurred', 'Additional details about the error');
        $exception->renderConsole($outputCallback);

        expect($output)->toEqual([
            ['message' => 'Error occurred', 'style' => 'error'],
            ['message' => '', 'style' => 'line'],
            ['message' => 'Additional details about the error', 'style' => 'line'],
        ]);
    });

    it('renders error with full context', function () {
        $output = [];
        $outputCallback = function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        };

        $exception = (new KeepException('Secret not found'))
            ->withContext(
                vault: 'aws-ssm',
                stage: 'production',
                key: 'DB_PASSWORD',
                path: '/app/production/DB_PASSWORD',
                lineNumber: 15,
                suggestion: "Check if this secret exists using 'php artisan keeper:list'"
            );

        $exception->renderConsole($outputCallback);

        expect($output)->toEqual([
            ['message' => 'Secret not found', 'style' => 'error'],
            ['message' => '  Vault: aws-ssm', 'style' => 'line'],
            ['message' => '  Stage: production', 'style' => 'line'],
            ['message' => '  Key: DB_PASSWORD', 'style' => 'line'],
            ['message' => '  Path: /app/production/DB_PASSWORD', 'style' => 'line'],
            ['message' => '  Template line: 15', 'style' => 'line'],
            ['message' => '', 'style' => 'line'],
            ['message' => "💡 Check if this secret exists using 'php artisan keeper:list'", 'style' => 'comment'],
        ]);
    });

    it('renders error with partial context', function () {
        $output = [];
        $outputCallback = function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        };

        $exception = (new KeepException('Partial error'))
            ->withContext(
                vault: 'local',
                key: 'API_KEY'
            );

        $exception->renderConsole($outputCallback);

        expect($output)->toEqual([
            ['message' => 'Partial error', 'style' => 'error'],
            ['message' => '  Vault: local', 'style' => 'line'],
            ['message' => '  Key: API_KEY', 'style' => 'line'],
        ]);
    });

    it('preserves context in subclasses', function () {
        $output = [];
        $outputCallback = function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        };

        $exception = (new SecretNotFoundException('Secret not found'))
            ->withContext(
                vault: 'aws-ssm',
                key: 'SECRET_KEY'
            );

        $exception->renderConsole($outputCallback);

        expect($output)->toEqual([
            ['message' => 'Secret not found', 'style' => 'error'],
            ['message' => '  Vault: aws-ssm', 'style' => 'line'],
            ['message' => '  Key: SECRET_KEY', 'style' => 'line'],
        ]);
    });

    it('fluently returns self from withContext', function () {
        $exception = new KeepException('Test');

        $result = $exception->withContext(vault: 'test');

        expect($result)->toBe($exception);
    });

    it('handles all context properties', function () {
        $output = [];
        $outputCallback = function ($message, $style = 'line') use (&$output) {
            $output[] = ['message' => $message, 'style' => $style];
        };

        $exception = (new KeepException('Error', 'Extra details'))
            ->withContext(
                vault: 'vault-name',
                stage: 'staging',
                key: 'KEY_NAME',
                path: '/full/path',
                lineNumber: 42,
                suggestion: 'Try this instead'
            );

        $exception->renderConsole($outputCallback);

        expect($output)->toEqual([
            ['message' => 'Error', 'style' => 'error'],
            ['message' => '  Vault: vault-name', 'style' => 'line'],
            ['message' => '  Stage: staging', 'style' => 'line'],
            ['message' => '  Key: KEY_NAME', 'style' => 'line'],
            ['message' => '  Path: /full/path', 'style' => 'line'],
            ['message' => '  Template line: 42', 'style' => 'line'],
            ['message' => '', 'style' => 'line'],
            ['message' => 'Extra details', 'style' => 'line'],
            ['message' => '', 'style' => 'line'],
            ['message' => '💡 Try this instead', 'style' => 'comment'],
        ]);
    });
});
