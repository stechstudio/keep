<?php

use STS\Keep\Enums\MissingSecretStrategy;

describe('MissingSecretStrategy', function () {
    
    it('has correct enum cases', function () {
        expect(MissingSecretStrategy::FAIL->value)->toBe('fail');
        expect(MissingSecretStrategy::REMOVE->value)->toBe('remove');
        expect(MissingSecretStrategy::BLANK->value)->toBe('blank');
        expect(MissingSecretStrategy::SKIP->value)->toBe('skip');
    });
    
    it('can be created from string values', function ($value, $expected) {
        $strategy = MissingSecretStrategy::from($value);
        expect($strategy)->toBe($expected);
    })->with([
        ['fail', MissingSecretStrategy::FAIL],
        ['remove', MissingSecretStrategy::REMOVE],
        ['blank', MissingSecretStrategy::BLANK],
        ['skip', MissingSecretStrategy::SKIP],
    ]);
    
    it('returns all cases', function () {
        $cases = MissingSecretStrategy::cases();
        
        expect($cases)->toHaveCount(4);
        expect($cases)->toContain(MissingSecretStrategy::FAIL);
        expect($cases)->toContain(MissingSecretStrategy::REMOVE);
        expect($cases)->toContain(MissingSecretStrategy::BLANK);
        expect($cases)->toContain(MissingSecretStrategy::SKIP);
    });
    
    it('throws exception for invalid values', function () {
        expect(fn() => MissingSecretStrategy::from('invalid'))
            ->toThrow(\ValueError::class);
    });
    
    it('supports tryFrom for safe creation', function () {
        expect(MissingSecretStrategy::tryFrom('fail'))->toBe(MissingSecretStrategy::FAIL);
        expect(MissingSecretStrategy::tryFrom('invalid'))->toBeNull();
    });
    
    it('can be used in match expressions', function ($strategy, $expected) {
        $result = match($strategy) {
            MissingSecretStrategy::FAIL => 'throws exception',
            MissingSecretStrategy::REMOVE => 'comments out line',
            MissingSecretStrategy::BLANK => 'creates empty value',
            MissingSecretStrategy::SKIP => 'keeps placeholder',
        };
        
        expect($result)->toBe($expected);
    })->with([
        [MissingSecretStrategy::FAIL, 'throws exception'],
        [MissingSecretStrategy::REMOVE, 'comments out line'],
        [MissingSecretStrategy::BLANK, 'creates empty value'],
        [MissingSecretStrategy::SKIP, 'keeps placeholder'],
    ]);
});