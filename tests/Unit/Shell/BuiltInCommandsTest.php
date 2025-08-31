<?php

use STS\Keep\Shell\Commands\BuiltInCommands;
use STS\Keep\Shell\ShellContext;
use Symfony\Component\Console\Output\BufferedOutput;

describe('BuiltInCommands', function () {
    beforeEach(function () {
        $this->context = new ShellContext('development', 'test');
        $this->output = new BufferedOutput();
        $this->commands = new BuiltInCommands($this->context, $this->output);
    });
    
    describe('command registration', function () {
        it('registers exit commands', function () {
            expect($this->commands->has('exit'))->toBeTrue();
            expect($this->commands->has('quit'))->toBeTrue();
            expect($this->commands->has('q'))->toBeTrue();
        });
        
        it('registers clear commands', function () {
            expect($this->commands->has('clear'))->toBeTrue();
            expect($this->commands->has('cls'))->toBeTrue();
        });
        
        it('registers help commands', function () {
            expect($this->commands->has('help'))->toBeTrue();
            expect($this->commands->has('?'))->toBeTrue();
        });
        
        it('registers context commands', function () {
            expect($this->commands->has('context'))->toBeTrue();
            expect($this->commands->has('ctx'))->toBeTrue();
        });
        
        it('registers stage command', function () {
            expect($this->commands->has('stage'))->toBeTrue();
        });
        
        it('registers vault command', function () {
            expect($this->commands->has('vault'))->toBeTrue();
        });
        
        it('registers use command', function () {
            expect($this->commands->has('use'))->toBeTrue();
            expect($this->commands->has('u'))->toBeTrue();
        });
        
        it('returns false for unknown commands', function () {
            expect($this->commands->has('unknown'))->toBeFalse();
        });
    });
    
    describe('command handling', function () {
        it('handles known commands', function () {
            $result = $this->commands->handle('help');
            
            expect($result)->toBeTrue();
            expect($this->output->fetch())->toContain('Keep Shell Commands');
        });
        
        it('returns false for unknown commands', function () {
            $result = $this->commands->handle('unknown');
            
            expect($result)->toBeFalse();
        });
    });
});