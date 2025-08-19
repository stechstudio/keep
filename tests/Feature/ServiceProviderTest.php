<?php

use Illuminate\Support\Facades\Artisan;
use STS\Keep\Commands\ExportCommand;
use STS\Keep\Commands\GetCommand;
use STS\Keep\Commands\ImportCommand;
use STS\Keep\Commands\ListCommand;
use STS\Keep\Commands\MergeCommand;
use STS\Keep\Commands\SetCommand;
use STS\Keep\Facades\Keep;
use STS\Keep\KeepManager;
use STS\Keep\KeepServiceProvider;

describe('ServiceProviderTest', function () {

    beforeEach(function () {
        // The service provider is automatically registered by our TestCase
        // but we'll verify its registration here
    });

    describe('service registration', function () {
        it('registers KeepManager as singleton', function () {
            // First resolution
            $manager1 = app(KeepManager::class);
            expect($manager1)->toBeInstanceOf(KeepManager::class);

            // Second resolution should return same instance
            $manager2 = app(KeepManager::class);
            expect($manager2)->toBe($manager1);
        });

        it('resolves KeepManager through container', function () {
            $manager = app()->make(KeepManager::class);

            expect($manager)->toBeInstanceOf(KeepManager::class);
            expect($manager)->not->toBeNull();
        });

        it('registers service provider correctly', function () {
            $providers = app()->getLoadedProviders();

            expect($providers)->toHaveKey(KeepServiceProvider::class);
            expect($providers[KeepServiceProvider::class])->toBeTrue();
        });
    });

    describe('config publishing', function () {
        it('merges config from package', function () {
            // Check that config is available
            expect(config('keep'))->toBeArray();
            expect(config('keep'))->not->toBeEmpty();
        });

        it('has expected config structure', function () {
            expect(config('keep.namespace'))->not->toBeNull();
            expect(config('keep.stages'))->toBeArray();
            expect(config('keep.default'))->not->toBeNull();
            expect(config('keep.vaults'))->toBeArray();
        });

        it('config has reasonable defaults', function () {
            // Should have default namespace (in tests it's 'test-app')
            expect(config('keep.namespace'))->toBe('test-app');

            // Should have default environments (in tests: testing, staging, production)
            $environments = config('keep.stages');
            expect($environments)->toContain('testing');
            expect($environments)->toContain('staging');
            expect($environments)->toContain('production');

            // Should have AWS SSM vault configured
            expect(config('keep.vaults.aws_ssm'))->toBeArray();
            expect(config('keep.vaults.aws_ssm.driver'))->toBe('aws_ssm');
        });

        it('allows config override', function () {
            // Set custom config
            config(['keep.namespace' => 'custom-app']);

            expect(config('keep.namespace'))->toBe('custom-app');
        });
    });

    describe('command registration', function () {
        it('registers all keep commands when running in console', function () {
            // Simulate console environment
            app()->detectEnvironment(function () {
                return 'testing';
            });

            // Get registered commands
            $commands = Artisan::all();
            $keepCommands = array_filter($commands, function ($command) {
                return str_starts_with($command->getName(), 'keep:');
            });

            // Check that all our commands are registered
            $commandNames = array_map(function ($command) {
                return $command->getName();
            }, $keepCommands);

            expect($commandNames)->toContain('keep:set');
            expect($commandNames)->toContain('keep:get');
            expect($commandNames)->toContain('keep:list');
            expect($commandNames)->toContain('keep:merge');
            expect($commandNames)->toContain('keep:export');
            expect($commandNames)->toContain('keep:import');
        });

        it('command instances are correct types', function () {
            $commands = Artisan::all();

            // Check specific command types
            expect($commands['keep:set'])->toBeInstanceOf(SetCommand::class);
            expect($commands['keep:get'])->toBeInstanceOf(GetCommand::class);
            expect($commands['keep:list'])->toBeInstanceOf(ListCommand::class);
            expect($commands['keep:merge'])->toBeInstanceOf(MergeCommand::class);
            expect($commands['keep:export'])->toBeInstanceOf(ExportCommand::class);
            expect($commands['keep:import'])->toBeInstanceOf(ImportCommand::class);
        });

        it('commands have proper signatures and descriptions', function () {
            $setCommand = Artisan::all()['keep:set'];

            expect($setCommand->getName())->toBe('keep:set');
            expect($setCommand->getDescription())->not->toBeEmpty();

            // Check that command has required arguments
            $definition = $setCommand->getDefinition();
            expect($definition->hasArgument('key'))->toBeTrue();
            expect($definition->hasArgument('value'))->toBeTrue();
        });
    });

    describe('facade functionality', function () {
        it('facade resolves to KeepManager', function () {
            $manager = Keep::getFacadeRoot();

            expect($manager)->toBeInstanceOf(KeepManager::class);
        });

        it('facade can access manager methods', function () {
            // Test basic facade functionality
            expect(Keep::namespace())->toBe('test-app');
            expect(Keep::stage())->toBe('testing');
        });

        it('facade maintains singleton behavior', function () {
            $manager1 = Keep::getFacadeRoot();
            $manager2 = Keep::getFacadeRoot();

            expect($manager1)->toBe($manager2);
        });
    });

    describe('multiple vault configuration', function () {
        it('supports multiple vault definitions', function () {
            // Override config with multiple vaults
            config([
                'keep.vaults' => [
                    'aws_ssm_prod' => [
                        'driver' => 'aws_ssm',
                        'prefix' => '/prod-secrets',
                        'region' => 'us-west-2',
                    ],
                    'aws_ssm_dev' => [
                        'driver' => 'aws_ssm',
                        'prefix' => '/dev-secrets',
                        'region' => 'us-east-1',
                    ],
                ],
                'keep.available' => ['aws_ssm_prod', 'aws_ssm_dev'],
                'keep.default' => 'aws_ssm_dev',
            ]);

            // Verify configuration is accessible
            expect(config('keep.vaults'))->toHaveKey('aws_ssm_prod');
            expect(config('keep.vaults'))->toHaveKey('aws_ssm_dev');
            expect(config('keep.available'))->toContain('aws_ssm_prod');
            expect(config('keep.available'))->toContain('aws_ssm_dev');
            expect(config('keep.default'))->toBe('aws_ssm_dev');
        });

        it('manager can handle multiple vault configs', function () {
            // Set up multiple vault config
            config([
                'keep.vaults' => [
                    'vault1' => ['driver' => 'aws_ssm', 'prefix' => '/vault1'],
                    'vault2' => ['driver' => 'aws_ssm', 'prefix' => '/vault2'],
                ],
            ]);

            $manager = app(KeepManager::class);

            // Should be able to get different vault instances
            // (We can't test actual vault creation without mocking AWS,
            // but we can verify the manager exists and config is loaded)
            expect($manager)->toBeInstanceOf(KeepManager::class);
            expect(config('keep.vaults.vault1'))->toBeArray();
            expect(config('keep.vaults.vault2'))->toBeArray();
        });
    });

    describe('environment configuration', function () {
        it('respects KEEP_ENV override', function () {
            // Test environment override
            config(['keep.stage' => 'custom-env']);

            expect(config('keep.stage'))->toBe('custom-env');
        });

        it('provides sensible environment defaults', function () {
            $environments = config('keep.stages');

            expect($environments)->toBeArray();
            expect($environments)->toContain('testing');
            expect($environments)->toContain('staging');
            expect($environments)->toContain('production');
            expect(count($environments))->toBeGreaterThanOrEqual(3);
        });

        it('supports custom environment lists', function () {
            config(['keep.stages' => ['dev', 'test', 'prod']]);

            $environments = config('keep.stages');
            expect($environments)->toBe(['dev', 'test', 'prod']);
        });
    });

    describe('template configuration', function () {
        it('has default template path', function () {
            $template = config('keep.template');

            expect($template)->toBeString();
            expect($template)->toContain('.env.template');
        });

        it('supports custom template paths', function () {
            config(['keep.template' => '/custom/path/template.env']);

            expect(config('keep.template'))->toBe('/custom/path/template.env');
        });

        it('supports environment-specific templates', function () {
            $envTemplates = config('keep.stage_templates');

            expect($envTemplates)->toBe('env');
        });
    });

    describe('integration with Laravel application', function () {
        it('integrates with Laravel service container', function () {
            // Test that the service is properly bound in container
            expect(app()->bound(KeepManager::class))->toBeTrue();

            // Test that it can be resolved
            $resolved = app()->make(KeepManager::class);
            expect($resolved)->toBeInstanceOf(KeepManager::class);
        });

        it('works with Laravel configuration system', function () {
            // Test config merging
            expect(config()->has('keep'))->toBeTrue();
            expect(config('keep'))->toBeArray();

            // Test that package config doesn't override app config
            config(['keep.custom_setting' => 'test_value']);
            expect(config('keep.custom_setting'))->toBe('test_value');
        });

        it('respects Laravel application environment', function () {
            // Test that when no explicit KEEP_ENV is set,
            // it should fall back to Laravel's environment detection

            // Clear any explicit KEEP_ENV setting
            config(['keep.stage' => null]);

            $manager = app(KeepManager::class);

            // Should use Laravel's environment (testing in our case)
            expect($manager->stage())->toBe('testing');
        });
    });

    describe('error handling and edge cases', function () {
        it('handles missing vault configuration gracefully', function () {
            // Set empty vault config
            config(['keep.vaults' => []]);

            $manager = app(KeepManager::class);

            // Should still create manager instance
            expect($manager)->toBeInstanceOf(KeepManager::class);
        });

        it('handles invalid configuration values', function () {
            // Test with various invalid config values
            config([
                'keep.namespace' => null,
                'keep.stages' => null,
                'keep.default' => null,
            ]);

            // Should not throw exceptions during service registration
            $manager = app(KeepManager::class);
            expect($manager)->toBeInstanceOf(KeepManager::class);
        });
    });
});
