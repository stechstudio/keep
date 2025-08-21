<?php

use STS\Keep\Data\Context;

beforeEach(function () {
    // Set up KeepManager for unit tests
    setupKeepManager();
});

describe('Context', function () {
    describe('fromInput parsing', function () {
        it('parses vault:stage format correctly', function () {
            $context = Context::fromInput('ssm:production');

            expect($context->vault)->toBe('ssm');
            expect($context->stage)->toBe('production');
        });

        it('uses default vault when no prefix provided', function () {
            // Set up manager with specific default vault for this test
            setupKeepManager(['default_vault' => 'test-vault']);

            $context = Context::fromInput('development');

            expect($context->vault)->toBe('test-vault');
            expect($context->stage)->toBe('development');
        });

        it('handles complex vault names with colons in vault:stage format', function () {
            $context = Context::fromInput('aws-us-east-1:staging');

            expect($context->vault)->toBe('aws-us-east-1');
            expect($context->stage)->toBe('staging');
        });

        it('handles stage names with special characters', function () {
            $context = Context::fromInput('test-env-123');

            expect($context->stage)->toBe('test-env-123');
        });
    });



    describe('vault creation', function () {
        it('creates vault instance with correct vault and stage', function () {
            $context = new Context('test', 'development');

            $vault = $context->createVault();

            expect($vault)->toBeInstanceOf(\STS\Keep\Vaults\AbstractVault::class);
            expect($vault->name())->toBe('test');
        });
    });

    describe('real-world usage patterns', function () {
        it('handles typical single-vault scenarios', function () {
            // Set up manager with specific default vault for this test
            setupKeepManager(['default_vault' => 'main-vault']);

            // Most common: stage only
            $context = Context::fromInput('production');
            expect($context->vault)->toBe('main-vault');
            expect($context->stage)->toBe('production');
            expect($context->toString())->toBe('main-vault:production');
        });

        it('handles cross-vault scenarios', function () {
            // Cross-vault with explicit syntax
            $context = Context::fromInput('backup-vault:production');
            expect($context->vault)->toBe('backup-vault');
            expect($context->stage)->toBe('production');
            expect($context->toString())->toBe('backup-vault:production');
        });

        it('handles complex vault names', function () {
            // Real-world vault names can be complex
            $context = Context::fromInput('aws-us-east-1-prod:staging');
            expect($context->vault)->toBe('aws-us-east-1-prod');
            expect($context->stage)->toBe('staging');
        });
    });
});
