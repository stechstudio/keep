<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Keep Secrets Integration Mode
    |--------------------------------------------------------------------------
    |
    | This option controls how Keep secrets are made available to your Laravel
    | application. Choose the integration approach that best fits your needs.
    |
    | Supported modes:
    |   - "helper": Use keep('SECRET_NAME') function calls only. Secrets are never on disk in plaintext.
    |   - "env": Hook into env() calls seamlessly. Note that config:cache will store secrets in plaintext.
    |
    */

    'integration_mode' => env('KEEP_INTEGRATION_MODE','env'),

    /*
    |--------------------------------------------------------------------------
    | Stage to Environment Mapping
    |--------------------------------------------------------------------------
    |
    | Map Laravel environments to Keep stages. This ensures secrets cached
    | for a specific stage are only loaded when Laravel is running in the
    | corresponding environment, preventing accidental cross-environment usage.
    |
    | Example: 'development' stage maps to 'local' Laravel environment
    |
    */

    'stage_environment_mapping' => [
        'local' => 'development',
        'staging' => 'staging', 
        'production' => 'production',
    ],
];