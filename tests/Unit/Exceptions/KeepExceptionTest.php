<?php

use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;
use Illuminate\Console\Command;
use Mockery;

describe('KeepException', function () {
    
    it('renders basic error message to console', function () {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('error')->once()->with('Basic error message');
        
        $exception = new KeepException('Basic error message');
        $exception->renderConsole($command);
    });
    
    it('renders error with details', function () {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('error')->once()->with('Error occurred');
        $command->shouldReceive('line')->once()->with('');
        $command->shouldReceive('line')->once()->with('Additional details about the error');
        
        $exception = new KeepException('Error occurred', 'Additional details about the error');
        $exception->renderConsole($command);
    });
    
    it('renders error with full context', function () {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('error')->once()->with('Secret not found');
        $command->shouldReceive('line')->with('  Vault: aws-ssm')->once();
        $command->shouldReceive('line')->with('  Environment: production')->once();
        $command->shouldReceive('line')->with('  Key: DB_PASSWORD')->once();
        $command->shouldReceive('line')->with('  Path: /app/production/DB_PASSWORD')->once();
        $command->shouldReceive('line')->with('  Template line: 15')->once();
        $command->shouldReceive('line')->with('')->once();
        $command->shouldReceive('comment')->with("💡 Check if this secret exists using 'php artisan keeper:list'")->once();
        
        $exception = (new KeepException('Secret not found'))
            ->withContext(
                vault: 'aws-ssm',
                environment: 'production',
                key: 'DB_PASSWORD',
                path: '/app/production/DB_PASSWORD',
                lineNumber: 15,
                suggestion: "Check if this secret exists using 'php artisan keeper:list'"
            );
        
        $exception->renderConsole($command);
    });
    
    it('renders error with partial context', function () {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('error')->once()->with('Partial error');
        $command->shouldReceive('line')->with('  Vault: local')->once();
        $command->shouldReceive('line')->with('  Key: API_KEY')->once();
        
        $exception = (new KeepException('Partial error'))
            ->withContext(
                vault: 'local',
                key: 'API_KEY'
            );
        
        $exception->renderConsole($command);
    });
    
    it('preserves context in subclasses', function () {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('error')->once()->with('Secret not found');
        $command->shouldReceive('line')->with('  Vault: aws-ssm')->once();
        $command->shouldReceive('line')->with('  Key: SECRET_KEY')->once();
        
        $exception = (new SecretNotFoundException('Secret not found'))
            ->withContext(
                vault: 'aws-ssm',
                key: 'SECRET_KEY'
            );
        
        $exception->renderConsole($command);
    });
    
    it('fluently returns self from withContext', function () {
        $exception = new KeepException('Test');
        
        $result = $exception->withContext(vault: 'test');
        
        expect($result)->toBe($exception);
    });
    
    it('handles all context properties', function () {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('error')->once();
        $command->shouldReceive('line')->times(8); // 5 context lines + 2 empty + 1 details
        $command->shouldReceive('comment')->once();
        
        $exception = (new KeepException('Error', 'Extra details'))
            ->withContext(
                vault: 'vault-name',
                environment: 'staging',
                key: 'KEY_NAME',
                path: '/full/path',
                lineNumber: 42,
                suggestion: 'Try this instead'
            );
        
        $exception->renderConsole($command);
    });
});