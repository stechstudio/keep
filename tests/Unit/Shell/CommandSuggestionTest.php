<?php

use STS\Keep\Shell\CommandSuggestion;

describe('CommandSuggestion', function () {
    beforeEach(function () {
        $this->suggestion = new CommandSuggestion();
    });
    
    describe('suggest()', function () {
        it('returns exact prefix matches first', function () {
            $suggestions = $this->suggestion->suggest('hel');
            
            expect($suggestions)->toContain('help');
            expect($suggestions[0])->toBe('help');
        });
        
        it('returns multiple prefix matches', function () {
            $suggestions = $this->suggestion->suggest('e');
            
            expect($suggestions)->toContain('exit');
            expect($suggestions)->toContain('export');
        });
        
        it('returns fuzzy matches with levenshtein distance', function () {
            $suggestions = $this->suggestion->suggest('hlep');
            
            expect($suggestions)->toContain('help');
        });
        
        it('returns empty array for no matches', function () {
            $suggestions = $this->suggestion->suggest('xyz123');
            
            expect($suggestions)->toBeEmpty();
        });
        
        it('handles case insensitive matching', function () {
            $suggestions = $this->suggestion->suggest('HELP');
            
            expect($suggestions)->toContain('help');
        });
        
        it('returns unique suggestions', function () {
            $suggestions = $this->suggestion->suggest('h');
            $uniqueCount = count(array_unique($suggestions));
            
            expect(count($suggestions))->toBe($uniqueCount);
        });
    });
    
    describe('formatSuggestions()', function () {
        it('formats suggestions as a readable string', function () {
            $formatted = $this->suggestion->formatSuggestions(['help', 'history']);
            
            expect($formatted)->toBe(' Did you mean: help, history?');
        });
        
        it('returns empty string for empty suggestions', function () {
            $formatted = $this->suggestion->formatSuggestions([]);
            
            expect($formatted)->toBe('');
        });
        
        it('handles single suggestion', function () {
            $formatted = $this->suggestion->formatSuggestions(['help']);
            
            expect($formatted)->toBe(' Did you mean: help?');
        });
    });
});