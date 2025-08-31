<?php

use STS\Keep\Shell\TabCompletion;
use STS\Keep\Shell\ShellContext;

describe('TabCompletion', function () {
    beforeEach(function () {
        // Create a real ShellContext with test data
        $this->context = new ShellContext('development', 'test');
        $this->completion = new TabCompletion($this->context);
    });
    
    describe('filter by prefix', function () {
        it('filters items by prefix', function () {
            $items = ['help', 'history', 'hello', 'exit'];
            
            // Use the protected method through a test subclass
            $filtered = filterByPrefix($items, 'hel');
            
            expect($filtered)->toBe(['help', 'hello']);
        });
        
        it('returns all items when no prefix', function () {
            $items = ['help', 'history', 'exit'];
            
            $filtered = filterByPrefix($items, '');
            
            expect($filtered)->toBe($items);
        });
        
        it('returns empty array for non-matching prefix', function () {
            $items = ['help', 'history', 'exit'];
            
            $filtered = filterByPrefix($items, 'xyz');
            
            expect($filtered)->toBe([]);
        });
    });
    
    describe('command resolution', function () {
        it('resolves command aliases', function () {
            $testCompletion = new class($this->context) extends TabCompletion {
                public function testResolveCommand($command) {
                    return $this->resolveCommand($command);
                }
            };
            
            expect($testCompletion->testResolveCommand('g'))->toBe('get');
            expect($testCompletion->testResolveCommand('s'))->toBe('set');
            expect($testCompletion->testResolveCommand('d'))->toBe('delete');
            expect($testCompletion->testResolveCommand('ls'))->toBe('show');
            expect($testCompletion->testResolveCommand('u'))->toBe('use');
        });
        
        it('keeps unknown commands unchanged', function () {
            $testCompletion = new class($this->context) extends TabCompletion {
                public function testResolveCommand($command) {
                    return $this->resolveCommand($command);
                }
            };
            
            expect($testCompletion->testResolveCommand('help'))->toBe('help');
            expect($testCompletion->testResolveCommand('unknown'))->toBe('unknown');
        });
    });
});

// Helper function for testing protected methods
function filterByPrefix($items, $prefix) {
    $context = new ShellContext('development', 'test');
    $completion = new class($context) extends TabCompletion {
        public function testFilterByPrefix($items, $prefix) {
            return $this->filterByPrefix($items, $prefix);
        }
    };
    
    return $completion->testFilterByPrefix($items, $prefix);
}