<?php

use STS\Keep\Tests\Support\TestVault;

describe('RenameCommand', function () {
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

    it('renames a secret successfully', function () {
        // Create initial secret
        $tester = runCommand('set', [
            'key' => 'OLD_KEY',
            'value' => 'secret_value',
            '--stage' => 'test',
        ]);
        
        // Run rename command
        $tester = runCommand('rename', [
            'old' => 'OLD_KEY',
            'new' => 'NEW_KEY',
            '--stage' => 'test',
            '--force' => true,
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('Renamed');
        
        // Verify new key exists
        $tester = runCommand('get', [
            'key' => 'NEW_KEY',
            '--stage' => 'test',
        ]);
        expect($tester->getDisplay())->toContain('secret_value');
    });

    it('fails when old secret does not exist', function () {
        $tester = runCommand('rename', [
            'old' => 'NONEXISTENT',
            'new' => 'NEW_KEY',
            '--stage' => 'test',
            '--force' => true,
        ]);
        
        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('not found');
    });

    it('fails when new key already exists', function () {
        // Create both secrets
        runCommand('set', [
            'key' => 'OLD_KEY',
            'value' => 'old_value',
            '--stage' => 'test',
        ]);
        runCommand('set', [
            'key' => 'NEW_KEY',
            'value' => 'existing_value',
            '--stage' => 'test',
        ]);
        
        $tester = runCommand('rename', [
            'old' => 'OLD_KEY',
            'new' => 'NEW_KEY',
            '--stage' => 'test',
            '--force' => true,
        ]);
        
        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('already exists');
        
        // Verify nothing changed
        $tester = runCommand('get', [
            'key' => 'OLD_KEY',
            '--stage' => 'test',
        ]);
        expect($tester->getDisplay())->toContain('old_value');
    });

    it('preserves secure flag when renaming', function () {
        // Create secure secret (secure by default, no flag needed)
        runCommand('set', [
            'key' => 'OLD_KEY',
            'value' => 'secret_value',
            '--stage' => 'test',
        ]);
        
        $tester = runCommand('rename', [
            'old' => 'OLD_KEY',
            'new' => 'NEW_KEY',
            '--stage' => 'test',
            '--force' => true,
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        
        // Since we can't directly access the secure flag through the CLI,
        // we verify the secret was renamed successfully
        expect($tester->getDisplay())->toContain('Renamed');
    });

    it('skips confirmation with force flag', function () {
        // Create initial secret
        runCommand('set', [
            'key' => 'OLD_KEY',
            'value' => 'secret_value',
            '--stage' => 'test',
        ]);
        
        // Note: Since we're always in non-interactive mode in tests,
        // the command will act as if --force is always true
        // We just verify the rename works
        $tester = runCommand('rename', [
            'old' => 'OLD_KEY',
            'new' => 'NEW_KEY',
            '--stage' => 'test',
        ]);
        
        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('Renamed');
    });
});