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
    |   - "helper": Use keep('SECRET_NAME') function calls (fully diskless)
    |   - "dotenv": Hook into env() calls seamlessly (config:cache limitation)
    |
    */

    'integration_mode' => 'helper',

    /*
    |--------------------------------------------------------------------------
    | Stage to Environment Mapping
    |--------------------------------------------------------------------------
    |
    | Map Keep stages to Laravel environments. This ensures secrets cached
    | for a specific stage are only loaded when Laravel is running in the
    | corresponding environment, preventing accidental cross-environment usage.
    |
    | Example: 'development' stage maps to 'local' Laravel environment
    |
    */

    'stage_environment_mapping' => [
        'development' => 'local',
        'staging' => 'staging', 
        'production' => 'production',
    ],
];