<?php

use STS\Keep\Enums\MissingSecretStrategy;

describe('MissingSecretStrategy', function () {


    it('can be created from string values', function ($value, $expected) {
        $strategy = MissingSecretStrategy::from($value);
        expect($strategy)->toBe($expected);
    })->with([
        ['fail', MissingSecretStrategy::FAIL],
        ['remove', MissingSecretStrategy::REMOVE],
        ['blank', MissingSecretStrategy::BLANK],
        ['skip', MissingSecretStrategy::SKIP],
    ]);


    it('throws exception for invalid values', function () {
        expect(fn () => MissingSecretStrategy::from('invalid'))
            ->toThrow(\ValueError::class);
    });


    it('can be used in match expressions', function ($strategy, $expected) {
        $result = match ($strategy) {
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
