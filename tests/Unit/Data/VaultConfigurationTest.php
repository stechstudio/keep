<?php

use STS\Keep\Data\VaultConfig;

describe('VaultConfiguration', function () {
    beforeEach(function () {
        // Create temp directory for test files
        $this->tempDir = '/tmp/keep-vault-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        chdir($this->tempDir);
        mkdir('.keep/vaults', 0755, true);
    });

    afterEach(function () {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            exec('rm -rf '.escapeshellarg($this->tempDir));
        }
    });

    describe('slug validation', function () {
        it('requires slug field', function () {
            expect(fn () => VaultConfig::fromArray([
                'driver' => 'test',
                'name' => 'Test Vault',
            ]))->toThrow(InvalidArgumentException::class, 'Missing required field: slug');
        });

        it('validates slug format', function () {
            expect(fn () => new VaultConfig('invalid slug', 'test', 'Test Vault'))
                ->toThrow(InvalidArgumentException::class, 'Slug must contain only lowercase letters');
        });

        it('accepts valid slug formats', function () {
            $validSlugs = [
                'simple',
                'with-hyphen',
                'with_underscore',
                'mixed-123_test',
                'numbers123',
            ];

            foreach ($validSlugs as $slug) {
                $config = new VaultConfig($slug, 'test', 'Test Vault');
                expect($config->slug())->toBe($slug);
            }
        });
    });

    describe('save functionality', function () {
        it('saves vault configuration to correct path', function () {
            $config = new VaultConfig(
                slug: 'test-vault',
                driver: 'test',
                name: 'Test Vault',
                config: ['namespace' => 'test-app']
            );

            $config->save();

            expect(file_exists('.keep/vaults/test-vault.json'))->toBeTrue();

            $saved = json_decode(file_get_contents('.keep/vaults/test-vault.json'), true);
            expect($saved['slug'])->toBe('test-vault');
            expect($saved['driver'])->toBe('test');
            expect($saved['name'])->toBe('Test Vault');
            expect($saved['namespace'])->toBe('test-app');
        });

        it('creates directories if they do not exist', function () {
            // Remove the directory we created in beforeEach
            rmdir('.keep/vaults');
            rmdir('.keep');

            $config = new VaultConfig('test', 'test', 'Test Vault');
            $config->save();

            expect(file_exists('.keep/vaults/test.json'))->toBeTrue();
        });
    });

    describe('array conversion', function () {
        it('includes slug in toArray output', function () {
            $config = new VaultConfig(
                slug: 'my-vault',
                driver: 'aws-ssm',
                name: 'My Vault',
                config: ['region' => 'us-east-1']
            );

            $array = $config->toArray();

            expect($array['slug'])->toBe('my-vault');
            expect($array['driver'])->toBe('aws-ssm');
            expect($array['name'])->toBe('My Vault');
            expect($array['region'])->toBe('us-east-1');
        });
    });
});
