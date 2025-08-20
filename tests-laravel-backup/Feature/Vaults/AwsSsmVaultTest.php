<?php

use Aws\CommandInterface;
use Aws\Result;
use Aws\Ssm\Exception\SsmException;
use Aws\Ssm\SsmClient;
use STS\Keep\Data\Secret;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Exceptions\AccessDeniedException;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Vaults\AwsSsmVault;

describe('AwsSsmVault', function () {

    beforeEach(function () {
        // Set up test configuration
        config([
            'keep.aws.region' => 'us-east-1',
            'keep.namespace' => 'test-app',
        ]);

        // We'll need to mock the SsmClient since we can't test against real AWS
        $this->mockClient = \Mockery::mock(SsmClient::class);

        // Create vault instance with test config
        $this->vault = new AwsSsmVault('test-vault', [
            'driver' => 'ssm',
            'prefix' => '/app-secrets',
        ], 'testing');

        // Inject the mock client using reflection since client() is protected
        $reflection = new ReflectionClass($this->vault);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->vault, $this->mockClient);
    });

    afterEach(function () {
        \Mockery::close();
    });

    describe('path formatting', function () {
        it('formats parameter paths correctly with prefix and namespace', function () {
            $path = $this->vault->format('DB_PASSWORD');

            expect($path)->toBe('/app-secrets/test-app/testing/DB_PASSWORD');
        });

        it('formats base path without key', function () {
            $path = $this->vault->format();

            expect($path)->toBe('/app-secrets/test-app/testing');
        });

        it('handles missing prefix in config', function () {
            $vault = new AwsSsmVault('test-vault', [
                'driver' => 'ssm',
            ], 'testing');

            $path = $vault->format('API_KEY');

            expect($path)->toBe('/test-app/testing/API_KEY');
        });

        it('supports custom key formatter', function () {
            $this->vault->formatKeyUsing(function ($key, $env, $config) {
                return "/custom/{$env}/{$key}";
            });

            $path = $this->vault->format('CUSTOM_KEY');

            expect($path)->toBe('/custom/testing/CUSTOM_KEY');
        });
    });

    describe('listing secrets', function () {
        it('retrieves all secrets with pagination', function () {
            // Mock first page of results
            $firstPage = new Result([
                'Parameters' => [
                    [
                        'Name' => '/app-secrets/test-app/testing/DB_HOST',
                        'Value' => 'localhost',
                        'Type' => 'String',
                        'Version' => 1,
                    ],
                    [
                        'Name' => '/app-secrets/test-app/testing/DB_PASSWORD',
                        'Value' => 'secret123',
                        'Type' => 'SecureString',
                        'Version' => 2,
                    ],
                ],
                'NextToken' => 'next-token-123',
            ]);

            // Mock second page of results
            $secondPage = new Result([
                'Parameters' => [
                    [
                        'Name' => '/app-secrets/test-app/testing/API_KEY',
                        'Value' => 'api-secret-456',
                        'Type' => 'SecureString',
                        'Version' => 1,
                    ],
                ],
                // No NextToken means end of pagination
            ]);

            $this->mockClient->shouldReceive('getParametersByPath')
                ->once()
                ->with([
                    'Path' => '/app-secrets/test-app/testing',
                    'Recursive' => true,
                    'WithDecryption' => true,
                    'MaxResults' => 10,
                ])
                ->andReturn($firstPage);

            $this->mockClient->shouldReceive('getParametersByPath')
                ->once()
                ->with([
                    'Path' => '/app-secrets/test-app/testing',
                    'Recursive' => true,
                    'WithDecryption' => true,
                    'MaxResults' => 10,
                    'NextToken' => 'next-token-123',
                ])
                ->andReturn($secondPage);

            $secrets = $this->vault->list();

            expect($secrets)->toBeInstanceOf(SecretCollection::class);
            expect($secrets->count())->toBe(3);

            // Check first secret
            $dbHost = $secrets->first();
            expect($dbHost->key())->toBe('DB_HOST');
            expect($dbHost->value())->toBe('localhost');
            expect($dbHost->isSecure())->toBeFalse();
            expect($dbHost->revision())->toBe(1);

            // Check secure secret - access by index since sorting might change order
            $sortedSecrets = $secrets->sortBy('key');
            $apiKey = $sortedSecrets->firstWhere(function ($secret) {
                return $secret->key() === 'API_KEY';
            });
            expect($apiKey)->not->toBeNull();
            expect($apiKey->isSecure())->toBeTrue();
            expect($apiKey->value())->toBe('api-secret-456');
        });

        it('handles empty parameter list', function () {
            $emptyResult = new Result([
                'Parameters' => [],
            ]);

            $this->mockClient->shouldReceive('getParametersByPath')
                ->once()
                ->andReturn($emptyResult);

            $secrets = $this->vault->list();

            expect($secrets)->toBeInstanceOf(SecretCollection::class);
            expect($secrets->count())->toBe(0);
        });

        it('throws AccessDeniedException on AWS access denied', function () {
            $mockCommand = \Mockery::mock(CommandInterface::class);
            $exception = new SsmException('Access denied',
                $mockCommand,
                ['code' => 'AccessDeniedException', 'message' => 'User not authorized']
            );

            $this->mockClient->shouldReceive('getParametersByPath')
                ->once()
                ->andThrow($exception);

            expect(fn () => $this->vault->list())
                ->toThrow(AccessDeniedException::class);
        });

        it('throws KeepException on other AWS errors', function () {
            $mockCommand = \Mockery::mock(CommandInterface::class);
            $exception = new SsmException('Rate limit exceeded',
                $mockCommand,
                ['code' => 'ThrottlingException', 'message' => 'Rate exceeded']
            );

            $this->mockClient->shouldReceive('getParametersByPath')
                ->once()
                ->andThrow($exception);

            expect(fn () => $this->vault->list())
                ->toThrow(KeepException::class);
        });
    });

    describe('getting individual secrets', function () {
        it('retrieves existing secret successfully', function () {
            $result = new Result([
                'Parameter' => [
                    'Name' => '/app-secrets/test-app/testing/DB_PASSWORD',
                    'Value' => 'secret123',
                    'Type' => 'SecureString',
                    'Version' => 3,
                ],
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->with([
                    'Name' => '/app-secrets/test-app/testing/DB_PASSWORD',
                    'WithDecryption' => true,
                ])
                ->andReturn($result);

            $secret = $this->vault->get('DB_PASSWORD');

            expect($secret)->toBeInstanceOf(Secret::class);
            expect($secret->key())->toBe('DB_PASSWORD');
            expect($secret->value())->toBe('secret123');
            expect($secret->isSecure())->toBeTrue();
            expect($secret->revision())->toBe(3);
            expect($secret->stage())->toBe('testing');
            expect($secret->vault())->toBe($this->vault);
        });

        it('throws SecretNotFoundException when parameter not found', function () {
            $mockCommand = \Mockery::mock(CommandInterface::class);
            $exception = new SsmException('Parameter not found',
                $mockCommand,
                ['code' => 'ParameterNotFound', 'message' => 'Parameter does not exist']
            );

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andThrow($exception);

            expect(fn () => $this->vault->get('NONEXISTENT_KEY'))
                ->toThrow(SecretNotFoundException::class);
        });

        it('throws AccessDeniedException on access denied', function () {
            $mockCommand = \Mockery::mock(CommandInterface::class);
            $exception = new SsmException('Access denied',
                $mockCommand,
                ['code' => 'AccessDeniedException', 'message' => 'Access denied']
            );

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andThrow($exception);

            expect(fn () => $this->vault->get('RESTRICTED_KEY'))
                ->toThrow(AccessDeniedException::class);
        });

        it('handles malformed parameter response', function () {
            $result = new Result([
                'Parameter' => null,
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andReturn($result);

            expect(fn () => $this->vault->get('MALFORMED_KEY'))
                ->toThrow(SecretNotFoundException::class);
        });
    });

    describe('setting secrets', function () {
        it('creates secure string parameter by default', function () {
            // Mock the putParameter call
            $this->mockClient->shouldReceive('putParameter')
                ->once()
                ->with([
                    'Name' => '/app-secrets/test-app/testing/NEW_SECRET',
                    'Value' => 'new-secret-value',
                    'Type' => 'SecureString',
                    'Overwrite' => true,
                    'KeyId' => null,
                ])
                ->andReturn(new Result([]));

            // Mock the subsequent get call
            $getResult = new Result([
                'Parameter' => [
                    'Name' => '/app-secrets/test-app/testing/NEW_SECRET',
                    'Value' => 'new-secret-value',
                    'Type' => 'SecureString',
                    'Version' => 1,
                ],
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andReturn($getResult);

            $secret = $this->vault->set('NEW_SECRET', 'new-secret-value');

            expect($secret->key())->toBe('NEW_SECRET');
            expect($secret->value())->toBe('new-secret-value');
            expect($secret->isSecure())->toBeTrue();
        });

        it('creates plain string parameter when secure=false', function () {
            $this->mockClient->shouldReceive('putParameter')
                ->once()
                ->with([
                    'Name' => '/app-secrets/test-app/testing/PLAIN_SECRET',
                    'Value' => 'plain-value',
                    'Type' => 'String',
                    'Overwrite' => true,
                    'KeyId' => null,
                ])
                ->andReturn(new Result([]));

            $getResult = new Result([
                'Parameter' => [
                    'Name' => '/app-secrets/test-app/testing/PLAIN_SECRET',
                    'Value' => 'plain-value',
                    'Type' => 'String',
                    'Version' => 1,
                ],
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andReturn($getResult);

            $secret = $this->vault->set('PLAIN_SECRET', 'plain-value', false);

            expect($secret->isSecure())->toBeFalse();
        });

        it('throws AccessDeniedException on AWS access denied', function () {
            $mockCommand = \Mockery::mock(CommandInterface::class);
            $exception = new SsmException('Access denied',
                $mockCommand,
                ['code' => 'AccessDeniedException', 'message' => 'Access denied']
            );

            $this->mockClient->shouldReceive('putParameter')
                ->once()
                ->andThrow($exception);

            expect(fn () => $this->vault->set('DENIED_KEY', 'value'))
                ->toThrow(AccessDeniedException::class);
        });
    });

    describe('saving secret objects', function () {
        it('saves secret using save method', function () {
            $secret = new Secret(
                key: 'SAVE_TEST',
                value: 'save-value',
                secure: true,
                stage: 'testing',
                revision: 0,
                vault: $this->vault
            );

            // Mock the putParameter and getParameter calls that set() will make
            $this->mockClient->shouldReceive('putParameter')
                ->once()
                ->with(\Mockery::on(function ($params) {
                    return $params['KeyId'] === null;
                }))
                ->andReturn(new Result([]));

            $getResult = new Result([
                'Parameter' => [
                    'Name' => '/app-secrets/test-app/testing/SAVE_TEST',
                    'Value' => 'save-value',
                    'Type' => 'SecureString',
                    'Version' => 2,
                ],
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andReturn($getResult);

            $savedSecret = $this->vault->save($secret);

            expect($savedSecret->key())->toBe('SAVE_TEST');
            expect($savedSecret->value())->toBe('save-value');
            expect($savedSecret->revision())->toBe(2);
        });
    });

    describe('stage handling', function () {
        it('creates vault for different environment', function () {
            $prodVault = $this->vault->forStage('production');

            expect($prodVault->format('TEST_KEY'))
                ->toBe('/app-secrets/test-app/production/TEST_KEY');

            // Original vault should be unchanged
            expect($this->vault->format('TEST_KEY'))
                ->toBe('/app-secrets/test-app/testing/TEST_KEY');
        });
    });

    describe('edge cases and error handling', function () {
        it('handles unicode values in secrets', function () {
            $unicodeValue = 'Hello 世界 🚀';

            $this->mockClient->shouldReceive('putParameter')
                ->once()
                ->with(\Mockery::on(function ($params) use ($unicodeValue) {
                    return $params['Value'] === $unicodeValue && $params['KeyId'] === null;
                }))
                ->andReturn(new Result([]));

            $getResult = new Result([
                'Parameter' => [
                    'Name' => '/app-secrets/test-app/testing/UNICODE_SECRET',
                    'Value' => $unicodeValue,
                    'Type' => 'SecureString',
                    'Version' => 1,
                ],
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andReturn($getResult);

            $secret = $this->vault->set('UNICODE_SECRET', $unicodeValue);

            expect($secret->value())->toBe($unicodeValue);
        });

        it('handles empty string values', function () {
            $this->mockClient->shouldReceive('putParameter')
                ->once()
                ->with(\Mockery::on(function ($params) {
                    return $params['Value'] === '' && $params['KeyId'] === null;
                }))
                ->andReturn(new Result([]));

            $getResult = new Result([
                'Parameter' => [
                    'Name' => '/app-secrets/test-app/testing/EMPTY_SECRET',
                    'Value' => '',
                    'Type' => 'String',
                    'Version' => 1,
                ],
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andReturn($getResult);

            $secret = $this->vault->set('EMPTY_SECRET', '', false);

            expect($secret->value())->toBe('');
        });

        it('handles parameters with missing optional fields', function () {
            $result = new Result([
                'Parameter' => [
                    'Name' => '/app-secrets/test-app/testing/MINIMAL_SECRET',
                    'Value' => 'minimal-value',
                    // Missing Type and Version
                ],
            ]);

            $this->mockClient->shouldReceive('getParameter')
                ->once()
                ->andReturn($result);

            $secret = $this->vault->get('MINIMAL_SECRET');

            expect($secret->value())->toBe('minimal-value');
            expect($secret->isSecure())->toBeFalse(); // defaults to String type
            expect($secret->revision())->toBe(0); // defaults to 0
        });
    });
});
