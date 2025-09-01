<?php

use STS\Keep\Tests\Support\TestVault;

describe('SearchCommand', function () {
    beforeEach(function () {
        createTempKeepDir();
        TestVault::clearAll();
        
        // Create .keep directory and settings to initialize Keep
        mkdir('.keep');
        mkdir('.keep/vaults');

        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'test',
            'stages' => ['test', 'production'],
            'created_at' => date('c'),
            'version' => '1.0',
        ];
        
        file_put_contents('.keep/settings.json', json_encode($settings));
        
        $vault = [
            'slug' => 'test',
            'driver' => 'test',
            'name' => 'Test Vault',
            'namespace' => 'test-app',
        ];
        
        file_put_contents('.keep/vaults/test.json', json_encode($vault));
    });

    it('finds secrets containing search query', function () {
        // Create test secrets
        runCommand('set', [
            'key' => 'API_KEY',
            'value' => 'sk-1234567890abcdef',
            '--stage' => 'test',
        ]);
        runCommand('set', [
            'key' => 'DATABASE_URL',
            'value' => 'postgresql://localhost:5432/mydb',
            '--stage' => 'test',
        ]);
        runCommand('set', [
            'key' => 'SECRET_TOKEN',
            'value' => 'token-1234',
            '--stage' => 'test',
        ]);
        
        // Search for '1234'
        $tester = runCommand('search', [
            'query' => '1234',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $output = stripAnsi($tester->getDisplay());
        // Should find API_KEY and SECRET_TOKEN
        expect($output)->toContain('Found 2 secret(s) containing "1234"');
        expect($output)->toContain('API_KEY');
        expect($output)->toContain('SECRET_TOKEN');
        expect($output)->not->toContain('DATABASE_URL');
    });

    it('performs case-insensitive search by default', function () {
        runCommand('set', ['key' => 'KEY1', 'value' => 'MySecret', '--stage' => 'test']);
        runCommand('set', ['key' => 'KEY2', 'value' => 'mysecret', '--stage' => 'test']);
        runCommand('set', ['key' => 'KEY3', 'value' => 'MYSECRET', '--stage' => 'test']);
        runCommand('set', ['key' => 'KEY4', 'value' => 'other', '--stage' => 'test']);
        
        $tester = runCommand('search', [
            'query' => 'mysecret',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $output = stripAnsi($tester->getDisplay());
        // Should find all variations
        expect($output)->toContain('Found 3 secret(s)');
        expect($output)->toContain('KEY1');
        expect($output)->toContain('KEY2');
        expect($output)->toContain('KEY3');
        expect($output)->not->toContain('KEY4');
    });

    it('performs case-sensitive search when specified', function () {
        runCommand('set', ['key' => 'KEY1', 'value' => 'MySecret', '--stage' => 'test']);
        runCommand('set', ['key' => 'KEY2', 'value' => 'mysecret', '--stage' => 'test']);
        runCommand('set', ['key' => 'KEY3', 'value' => 'MYSECRET', '--stage' => 'test']);
        
        $tester = runCommand('search', [
            'query' => 'mysecret',
            '--case-sensitive' => true,
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $output = stripAnsi($tester->getDisplay());
        // Should only find exact match
        expect($output)->toContain('Found 1 secret(s)');
        expect($output)->toContain('KEY2');
        expect($output)->not->toContain('KEY1');
        expect($output)->not->toContain('KEY3');
    });

    it('masks values by default', function () {
        runCommand('set', [
            'key' => 'API_KEY',
            'value' => 'sk-verysecretkey123',
            '--stage' => 'test',
        ]);
        
        $tester = runCommand('search', [
            'query' => 'secret',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $output = stripAnsi($tester->getDisplay());
        // Value should be masked (first 4 chars + asterisks)
        expect($output)->toContain('sk-v***************');
        expect($output)->not->toContain('sk-verysecretkey123');
    });

    it('shows unmasked values with unmask option', function () {
        runCommand('set', [
            'key' => 'API_KEY',
            'value' => 'sk-verysecretkey123',
            '--stage' => 'test',
        ]);
        
        $tester = runCommand('search', [
            'query' => 'secret',
            '--unmask' => true,
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $rawOutput = $tester->getDisplay();
        $output = stripAnsi($rawOutput);
        
        // Check that ANSI color codes are present in raw output (bright yellow background)
        expect($rawOutput)->toContain("\033[30;103m");
        
        // Value should be unmasked with the query text visible (after stripping ANSI)
        expect($output)->toContain('secret');
        expect($output)->toContain('sk-verysecretkey123');
    });

    it('returns success with message when no matches found', function () {
        runCommand('set', ['key' => 'KEY1', 'value' => 'value1', '--stage' => 'test']);
        runCommand('set', ['key' => 'KEY2', 'value' => 'value2', '--stage' => 'test']);
        
        $tester = runCommand('search', [
            'query' => 'nonexistent',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('No secrets found containing "nonexistent"');
    });

    it('handles empty vault gracefully', function () {
        $tester = runCommand('search', [
            'query' => 'anything',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('No secrets found to search');
    });

    it('respects only and except filters', function () {
        runCommand('set', ['key' => 'API_KEY', 'value' => 'contains-search-term', '--stage' => 'test']);
        runCommand('set', ['key' => 'DB_PASSWORD', 'value' => 'contains-search-term', '--stage' => 'test']);
        runCommand('set', ['key' => 'CACHE_KEY', 'value' => 'contains-search-term', '--stage' => 'test']);
        runCommand('set', ['key' => 'OTHER_SECRET', 'value' => 'contains-search-term', '--stage' => 'test']);
        
        // Test with 'only' filter
        $tester = runCommand('search', [
            'query' => 'search',
            '--only' => '*_KEY',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $output = stripAnsi($tester->getDisplay());
        expect($output)->toContain('Found 2 secret(s)');
        expect($output)->toContain('API_KEY');
        expect($output)->toContain('CACHE_KEY');
        expect($output)->not->toContain('DB_PASSWORD');
        expect($output)->not->toContain('OTHER_SECRET');
    });

    it('outputs JSON format when specified', function () {
        runCommand('set', ['key' => 'KEY1', 'value' => 'search-value', '--stage' => 'test']);
        runCommand('set', ['key' => 'KEY2', 'value' => 'search-value', '--stage' => 'test']);
        
        $tester = runCommand('search', [
            'query' => 'search',
            '--format' => 'json',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $output = $tester->getDisplay();
        // Check for JSON structure
        expect($output)->toContain('[');
        expect($output)->toContain(']');
        expect($output)->toContain('"key"');
        expect($output)->toContain('"value"');
        expect($output)->toContain('"revision"');
    });

    it('searches partial matches', function () {
        runCommand('set', [
            'key' => 'STRIPE_KEY',
            'value' => 'test_key_4eC39HqLyjWDarjtT1zdp7dc',
            '--stage' => 'test',
        ]);
        runCommand('set', [
            'key' => 'AWS_KEY',
            'value' => 'TESTKEY123EXAMPLE',
            '--stage' => 'test',
        ]);
        
        // Search for partial match
        $tester = runCommand('search', [
            'query' => '39Hq',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        $output = stripAnsi($tester->getDisplay());
        expect($output)->toContain('Found 1 secret(s)');
        expect($output)->toContain('STRIPE_KEY');
        expect($output)->not->toContain('AWS_KEY');
    });
});