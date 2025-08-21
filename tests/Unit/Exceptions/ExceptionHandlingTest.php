<?php

use STS\Keep\Exceptions\ExceptionFactory;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;

describe('Exception Handling Improvements', function () {

    describe('ExceptionFactory', function () {
        it('creates clean secret not found exceptions', function () {
            $exception = ExceptionFactory::secretNotFound('API_KEY', 'ssm');

            expect($exception)->toBeInstanceOf(SecretNotFoundException::class);
            expect($exception->getMessage())->toBe('Unable to find secret for key [API_KEY] in vault [ssm]');
        });

        it('creates template exceptions with line context', function () {
            $exception = ExceptionFactory::secretNotFoundInTemplate(
                'ENV_VAR',
                'vault-name',
                'SECRET_KEY',
                42
            );

            expect($exception)->toBeInstanceOf(SecretNotFoundException::class);
            expect($exception->getMessage())->toBe('Unable to find secret for key [SECRET_KEY] in vault [vault-name]');
        });

        it('creates AWS error exceptions with context', function () {
            $exception = ExceptionFactory::awsError(
                'Access denied to parameter',
                'ssm-prod',
                'DB_PASSWORD'
            );

            expect($exception)->toBeInstanceOf(KeepException::class);
            expect($exception->getMessage())->toBe('Access denied to parameter');
        });

        it('handles missing path in template exceptions gracefully', function () {
            $exception = ExceptionFactory::secretNotFoundInTemplate(
                'ENV_VAR',
                'vault-name',
                null, // no path
                42
            );

            // Should use env var name when path is null
            expect($exception->getMessage())->toBe('Unable to find secret for key [ENV_VAR] in vault [vault-name]');
        });
    });

    describe('KeepException context', function () {
        it('allows building exceptions with minimal context calls', function () {
            // Before: long withContext() chains
            // Now: clean factory methods with automatic context

            $exception = ExceptionFactory::secretNotFound('API_KEY', 'production-vault')
                ->withContext(suggestion: 'Custom suggestion here');

            expect($exception->getMessage())->toContain('API_KEY');
            expect($exception->getMessage())->toContain('production-vault');
        });

        it('creates exceptions with details using factory method', function () {
            $exception = ExceptionFactory::withDetails('Error occurred', 'More detailed explanation');

            expect($exception)->toBeInstanceOf(KeepException::class);
            expect($exception->getMessage())->toBe('Error occurred');
        });

        it('allows combining details with other context', function () {
            $exception = ExceptionFactory::withDetails('Vault error', 'Connection failed')
                ->withContext(vault: 'production-ssm', stage: 'prod');

            expect($exception->getMessage())->toBe('Vault error');
        });
    });
});
