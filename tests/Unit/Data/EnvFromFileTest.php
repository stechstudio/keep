<?php

use STS\Keep\Data\Env;

it('loads env file using fromFile static method', function () {
    $tempDir = createTempKeepDir();
    $envFile = $tempDir.'/.env';

    $envContent = "APP_NAME=TestApp\nAPP_KEY=secret123\nDB_HOST=localhost";
    file_put_contents($envFile, $envContent);

    $env = Env::fromFile($envFile);

    expect($env->get('APP_NAME'))->toBe('TestApp');
    expect($env->get('APP_KEY'))->toBe('secret123');
    expect($env->get('DB_HOST'))->toBe('localhost');

    cleanupTempDir($tempDir);
});

it('returns empty env when file does not exist', function () {
    $env = Env::fromFile('/non/existent/file.env');

    expect($env->get('ANY_KEY'))->toBeNull();
    expect($env->get('ANY_KEY', 'default'))->toBe('default');
    expect($env->contents())->toBe('');
});

it('gets individual values with default fallback', function () {
    $envContent = 'EXISTING_KEY=value123';
    $env = new Env($envContent);

    expect($env->get('EXISTING_KEY'))->toBe('value123');
    expect($env->get('NON_EXISTENT'))->toBeNull();
    expect($env->get('NON_EXISTENT', 'fallback'))->toBe('fallback');
});

it('handles empty values correctly in get method', function () {
    $envContent = "EMPTY_KEY=\nNULL_KEY=null\nZERO_KEY=0";
    $env = new Env($envContent);

    expect($env->get('EMPTY_KEY'))->toBe('');
    expect($env->get('NULL_KEY'))->toBe('null'); // String 'null', not null
    expect($env->get('ZERO_KEY'))->toBe('0');
});

it('handles quoted values in get method', function () {
    $envContent = "QUOTED_SINGLE='single quotes'\nQUOTED_DOUBLE=\"double quotes\"";
    $env = new Env($envContent);

    expect($env->get('QUOTED_SINGLE'))->toBe('single quotes');
    expect($env->get('QUOTED_DOUBLE'))->toBe('double quotes');
});

it('preserves file loading across multiple calls', function () {
    $tempDir = createTempKeepDir();
    $envFile = $tempDir.'/.env';

    $envContent = 'PERSISTENT_KEY=persistent_value';
    file_put_contents($envFile, $envContent);

    $env = Env::fromFile($envFile);

    // Multiple calls should work consistently
    expect($env->get('PERSISTENT_KEY'))->toBe('persistent_value');
    expect($env->get('PERSISTENT_KEY'))->toBe('persistent_value');

    // List method should also work
    expect($env->list()->get('PERSISTENT_KEY'))->toBe('persistent_value');

    cleanupTempDir($tempDir);
});
