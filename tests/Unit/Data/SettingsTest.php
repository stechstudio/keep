<?php

use STS\Keep\Data\Settings;

describe('Settings', function () {

    describe('validation', function () {
        it('validates required fields', function () {
            expect(fn () => Settings::fromArray([]))
                ->toThrow(InvalidArgumentException::class, 'Missing required setting: app_name');

            expect(fn () => Settings::fromArray(['app_name' => 'test']))
                ->toThrow(InvalidArgumentException::class, 'Missing required setting: namespace');

            expect(fn () => Settings::fromArray(['app_name' => 'test', 'namespace' => 'test']))
                ->toThrow(InvalidArgumentException::class, 'Missing required setting: stages');
        });

        it('validates app name', function () {
            expect(fn () => new Settings('', 'namespace', ['stage']))
                ->toThrow(InvalidArgumentException::class, 'App name cannot be empty');

            expect(fn () => new Settings('   ', 'namespace', ['stage']))
                ->toThrow(InvalidArgumentException::class, 'App name cannot be empty');

            expect(fn () => new Settings(str_repeat('a', 101), 'namespace', ['stage']))
                ->toThrow(InvalidArgumentException::class, 'App name cannot exceed 100 characters');
        });

        it('validates namespace', function () {
            expect(fn () => new Settings('app', 'invalid space', ['stage']))
                ->toThrow(InvalidArgumentException::class, 'Namespace must contain only letters');

            expect(fn () => new Settings('app', 'invalid@symbol', ['stage']))
                ->toThrow(InvalidArgumentException::class, 'Namespace must contain only letters');

            expect(fn () => new Settings('app', str_repeat('a', 101), ['stage']))
                ->toThrow(InvalidArgumentException::class, 'Namespace cannot exceed 100 characters');
        });

        it('validates stages', function () {
            expect(fn () => new Settings('app', 'namespace', []))
                ->toThrow(InvalidArgumentException::class, 'At least one stage must be defined');

            expect(fn () => new Settings('app', 'namespace', ['']))
                ->toThrow(InvalidArgumentException::class, 'All stages must be non-empty strings');

            expect(fn () => new Settings('app', 'namespace', ['UPPERCASE']))
                ->toThrow(InvalidArgumentException::class, 'Stage \'UPPERCASE\' must contain only lowercase');

            expect(fn () => new Settings('app', 'namespace', ['invalid space']))
                ->toThrow(InvalidArgumentException::class, 'Stage \'invalid space\' must contain only lowercase');

            expect(fn () => new Settings('app', 'namespace', [str_repeat('a', 51)]))
                ->toThrow(InvalidArgumentException::class, 'cannot exceed 50 characters');

            expect(fn () => new Settings('app', 'namespace', ['stage1', 'stage1']))
                ->toThrow(InvalidArgumentException::class, 'Duplicate stages are not allowed');
        });

        it('accepts valid stage formats', function () {
            $validStages = [
                'development',
                'staging',
                'production',
                'qa',
                'uat',
                'test_env',
                'stage-1',
                'env123',
            ];

            $settings = new Settings('app', 'namespace', $validStages);
            expect($settings->stages())->toBe($validStages);
        });

        it('validates default vault', function () {
            expect(fn () => new Settings('app', 'namespace', ['stage'], 'invalid space'))
                ->toThrow(InvalidArgumentException::class, 'Default vault name must contain only letters');

            expect(fn () => new Settings('app', 'namespace', ['stage'], 'invalid@symbol'))
                ->toThrow(InvalidArgumentException::class, 'Default vault name must contain only letters');
        });

        it('validates version format', function () {
            expect(fn () => new Settings('app', 'namespace', ['stage'], null, null, null, null, 'invalid'))
                ->toThrow(InvalidArgumentException::class, 'Version must be in format "major.minor" (e.g., "1.0")');

            expect(fn () => new Settings('app', 'namespace', ['stage'], null, null, null, null, '1.0.0'))
                ->toThrow(InvalidArgumentException::class, 'Version must be in format "major.minor" (e.g., "1.0")');

            $settings = new Settings('app', 'namespace', ['stage'], null, null, null, null, '2.1');
            expect($settings->version())->toBe('2.1');
        });
    });

    describe('file operations', function () {
        beforeEach(function () {
            // Create temp directory for test files
            $this->tempDir = '/tmp/keep-settings-test-'.uniqid();
            mkdir($this->tempDir, 0755, true);
        });

        afterEach(function () {
            // Clean up temp directory
            if (is_dir($this->tempDir)) {
                array_map('unlink', glob($this->tempDir.'/*'));
                rmdir($this->tempDir);
            }
        });

        it('saves and loads settings correctly', function () {
            $original = new Settings(
                appName: 'My App',
                namespace: 'my_app',
                stages: ['local', 'staging', 'production'],
                defaultVault: 'primary'
            );

            $filePath = $this->tempDir.'/settings.json';
            $original->saveToFile($filePath);

            expect(file_exists($filePath))->toBeTrue();

            $loaded = Settings::fromFile($filePath);

            expect($loaded->appName())->toBe('My App');
            expect($loaded->namespace())->toBe('my_app');
            expect($loaded->stages())->toBe(['local', 'staging', 'production']);
            expect($loaded->defaultVault())->toBe('primary');
            expect($loaded->version())->toBe('1.0');
        });

        it('creates directory if not exists', function () {
            $nestedPath = $this->tempDir.'/nested/deep/settings.json';

            $settings = new Settings('app', 'namespace', ['stage']);
            $settings->saveToFile($nestedPath);

            expect(file_exists($nestedPath))->toBeTrue();
            expect(is_dir(dirname($nestedPath)))->toBeTrue();
        });

        it('handles missing file gracefully', function () {
            $missingPath = $this->tempDir.'/missing.json';

            expect(fn () => Settings::fromFile($missingPath))
                ->toThrow(\Illuminate\Contracts\Filesystem\FileNotFoundException::class);
        });

        it('handles corrupted JSON', function () {
            $corruptPath = $this->tempDir.'/corrupt.json';
            file_put_contents($corruptPath, '{"invalid": json}');

            expect(fn () => Settings::fromFile($corruptPath))
                ->toThrow(JsonException::class, 'Syntax error');
        });

        it('handles non-object JSON', function () {
            $arrayPath = $this->tempDir.'/array.json';
            file_put_contents($arrayPath, '["not", "an", "object"]');

            expect(fn () => Settings::fromFile($arrayPath))
                ->toThrow(Exception::class); // Will be caught by fromArray validation
        });

        it('handles incomplete settings data', function () {
            $incompletePath = $this->tempDir.'/incomplete.json';
            file_put_contents($incompletePath, '{"app_name": "test"}');

            expect(fn () => Settings::fromFile($incompletePath))
                ->toThrow(InvalidArgumentException::class, 'Missing required setting: namespace');
        });
    });

    describe('immutable mutations', function () {
        it('returns new instance with withDefaultVault', function () {
            $original = new Settings('app', 'namespace', ['stage']);
            $updated = $original->withDefaultVault('new_vault');

            expect($original->defaultVault())->toBeNull();
            expect($updated->defaultVault())->toBe('new_vault');
            expect($updated)->not->toBe($original);
        });

        it('returns new instance with withStages', function () {
            $original = new Settings('app', 'namespace', ['stage1']);
            $updated = $original->withStages(['stage1', 'stage2']);

            expect($original->stages())->toBe(['stage1']);
            expect($updated->stages())->toBe(['stage1', 'stage2']);
            expect($updated)->not->toBe($original);
        });
    });

    describe('utility methods', function () {
        it('checks if stage exists', function () {
            $settings = new Settings('app', 'namespace', ['dev', 'prod']);

            expect($settings->hasStage('dev'))->toBeTrue();
            expect($settings->hasStage('prod'))->toBeTrue();
            expect($settings->hasStage('staging'))->toBeFalse();
        });

        it('converts to array correctly', function () {
            $settings = new Settings(
                appName: 'Test App',
                namespace: 'test_app',
                stages: ['local', 'prod'],
                defaultVault: 'primary'
            );

            $array = $settings->toArray();

            expect($array)->toHaveKey('app_name', 'Test App');
            expect($array)->toHaveKey('namespace', 'test_app');
            expect($array)->toHaveKey('stages', ['local', 'prod']);
            expect($array)->toHaveKey('default_vault', 'primary');
            expect($array)->toHaveKey('version', '1.0');
            expect($array)->toHaveKey('created_at');
            expect($array)->toHaveKey('updated_at');
        });

        it('provides individual setting access via get method', function () {
            $settings = new Settings(
                appName: 'My App',
                namespace: 'my_namespace',
                stages: ['dev', 'staging', 'prod'],
                defaultVault: 'primary_vault'
            );

            expect($settings->get('app_name'))->toBe('My App');
            expect($settings->get('namespace'))->toBe('my_namespace');
            expect($settings->get('stages'))->toBe(['dev', 'staging', 'prod']);
            expect($settings->get('default_vault'))->toBe('primary_vault');
            expect($settings->get('version'))->toBe('1.0');
            expect($settings->get('nonexistent_key'))->toBeNull();
            expect($settings->get('nonexistent_key', 'default_value'))->toBe('default_value');
        });
    });

    describe('timestamps', function () {

        it('preserves created_at when provided', function () {
            $fixedTime = '2024-01-01T12:00:00+00:00';

            $settings = Settings::fromArray([
                'app_name' => 'app',
                'namespace' => 'namespace',
                'stages' => ['stage'],
                'created_at' => $fixedTime,
            ]);

            expect($settings->createdAt())->toBe($fixedTime);
            expect($settings->updatedAt())->not->toBe($fixedTime); // Should be current time
        });
    });
});
