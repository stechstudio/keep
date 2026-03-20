<?php

use STS\Keep\Shell\ArgumentProcessor;

describe('ArgumentProcessor', function () {

    describe('argument mapping', function () {

        it('maps positional arguments by index', function () {
            $input = [];
            ArgumentProcessor::process('set', ['MY_KEY', 'my_value'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input['value'])->toBe('my_value');
        });

        it('handles missing positional arguments gracefully', function () {
            $input = [];
            ArgumentProcessor::process('set', ['MY_KEY'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input)->not->toHaveKey('value');
        });

        it('maps single argument commands', function () {
            $input = [];
            ArgumentProcessor::process('get', ['MY_KEY'], $input);

            expect($input['key'])->toBe('MY_KEY');
        });

        it('maps rename command with old and new arguments', function () {
            $input = [];
            ArgumentProcessor::process('rename', ['OLD_KEY', 'NEW_KEY'], $input);

            expect($input['old'])->toBe('OLD_KEY');
            expect($input['new'])->toBe('NEW_KEY');
        });
    });

    describe('flag processing', function () {

        it('converts flag keywords to boolean options', function () {
            $input = [];
            ArgumentProcessor::process('delete', ['MY_KEY', 'force'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input['--force'])->toBeTrue();
        });

        it('handles multiple flags', function () {
            $input = [];
            ArgumentProcessor::process('search', ['query', 'unmask', 'case-sensitive'], $input);

            expect($input['query'])->toBe('query');
            expect($input['--unmask'])->toBeTrue();
            expect($input['--case-sensitive'])->toBeTrue();
        });

        it('does not set flags when not present', function () {
            $input = [];
            ArgumentProcessor::process('delete', ['MY_KEY'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input)->not->toHaveKey('--force');
        });

        it('supports flags in any order after arguments', function () {
            $input = [];
            ArgumentProcessor::process('rename', ['OLD', 'NEW', 'force'], $input);

            expect($input['old'])->toBe('OLD');
            expect($input['new'])->toBe('NEW');
            expect($input['--force'])->toBeTrue();
        });

        it('supports unmask flag on history', function () {
            $input = [];
            ArgumentProcessor::process('history', ['MY_KEY', 'unmask'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input['--unmask'])->toBeTrue();
        });

        it('supports unmask flag on show', function () {
            $input = [];
            ArgumentProcessor::process('show', ['unmask'], $input);

            expect($input['--unmask'])->toBeTrue();
        });
    });

    describe('option mapping', function () {

        it('maps positional index to named option', function () {
            $input = [];
            ArgumentProcessor::process('copy', ['MY_KEY', 'other-vault:production'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input['--to'])->toBe('other-vault:production');
        });

        it('maps export file option from first positional', function () {
            $input = [];
            ArgumentProcessor::process('export', ['output.env'], $input);

            expect($input['--file'])->toBe('output.env');
        });
    });

    describe('collect mode', function () {

        it('collects positionals into comma-separated option', function () {
            $input = [];
            ArgumentProcessor::process('diff', ['testing', 'production'], $input);

            expect($input['--env'])->toBe('testing,production');
        });

        it('collects multiple positionals', function () {
            $input = [];
            ArgumentProcessor::process('diff', ['local', 'testing', 'production'], $input);

            expect($input['--env'])->toBe('local,testing,production');
        });

        it('excludes flags from collected values', function () {
            $input = [];
            ArgumentProcessor::process('diff', ['testing', 'production', 'unmask'], $input);

            expect($input['--env'])->toBe('testing,production');
            expect($input['--unmask'])->toBeTrue();
        });

        it('handles single positional in collect mode', function () {
            $input = [];
            ArgumentProcessor::process('diff', ['testing'], $input);

            expect($input['--env'])->toBe('testing');
        });

        it('handles no positionals in collect mode', function () {
            $input = [];
            ArgumentProcessor::process('diff', [], $input);

            expect($input)->not->toHaveKey('--env');
        });
    });

    describe('import command flags', function () {

        it('supports overwrite flag', function () {
            $input = [];
            ArgumentProcessor::process('import', ['.env', 'overwrite'], $input);

            expect($input['from'])->toBe('.env');
            expect($input['--overwrite'])->toBeTrue();
        });

        it('supports skip-existing flag', function () {
            $input = [];
            ArgumentProcessor::process('import', ['.env', 'skip-existing'], $input);

            expect($input['from'])->toBe('.env');
            expect($input['--skip-existing'])->toBeTrue();
        });

        it('supports dry-run flag', function () {
            $input = [];
            ArgumentProcessor::process('import', ['.env', 'dry-run'], $input);

            expect($input['from'])->toBe('.env');
            expect($input['--dry-run'])->toBeTrue();
        });

        it('supports multiple flags in any order', function () {
            $input = [];
            ArgumentProcessor::process('import', ['.env', 'dry-run', 'overwrite'], $input);

            expect($input['from'])->toBe('.env');
            expect($input['--dry-run'])->toBeTrue();
            expect($input['--overwrite'])->toBeTrue();
        });
    });

    describe('copy command flags', function () {

        it('supports overwrite flag', function () {
            $input = [];
            ArgumentProcessor::process('copy', ['MY_KEY', 'other:prod', 'overwrite'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input['--to'])->toBe('other:prod');
            expect($input['--overwrite'])->toBeTrue();
        });

        it('supports dry-run flag', function () {
            $input = [];
            ArgumentProcessor::process('copy', ['MY_KEY', 'other:prod', 'dry-run'], $input);

            expect($input['key'])->toBe('MY_KEY');
            expect($input['--to'])->toBe('other:prod');
            expect($input['--dry-run'])->toBeTrue();
        });
    });

    describe('unknown commands', function () {

        it('does nothing for unconfigured commands', function () {
            $input = [];
            ArgumentProcessor::process('unknown', ['arg1', 'arg2'], $input);

            expect($input)->toBeEmpty();
        });
    });
});
