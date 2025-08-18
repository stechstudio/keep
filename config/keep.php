<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Keep namespace
    |--------------------------------------------------------------------------
    |
    | This is the base namespace (or prefix) that Keep will use for all
    | secrets. This helps to avoid key collisions when multiple applications.
    | If not defined, the APP_NAME will be used.
    |
    */
    'namespace' => env('KEEP_NAMESPACE', env('APP_NAME', 'keep')),

    /*
    |--------------------------------------------------------------------------
    | Explicit Keep environment
    |--------------------------------------------------------------------------
    |
    | This option defines the environment that Keep should consider as current
    | no matter what APP_ENV is set to. This can be useful when your secrets
    | use an environment name that differs from your application.
    |
    */
    'environment' => env('KEEP_ENV'),

    /*
    |--------------------------------------------------------------------------
    | Environment list
    |--------------------------------------------------------------------------
    |
    | This defines all environments that Keep can interact with when
    | managing secrets. This should generally match the environments your
    | application supports. However, limiting this list is useful for
    | security to prevent accidental access to the wrong env.
    |
    | For example, if a developer should never access production secrets,
    | you can simply omit 'production' from this list in their env.
    | To truly enforce this, you should also ensure that your IAM
    | policies restrict access to only the environments needed.
    |
    */
    'environments' => explode(',', env('KEEP_ENVS', 'local,staging,production')),

    /*
    |--------------------------------------------------------------------------
    | Template env file
    |--------------------------------------------------------------------------
    |
    | This will be used as the default template file when merging secrets
    | and generating env files.
    |
    */
    'template' => env('KEEP_TEMPLATE', base_path('.env.template')),

    /*
    |--------------------------------------------------------------------------
    | Overlay environment-specific templates
    |--------------------------------------------------------------------------
    |
    | If set, this will scan for environment-specific template files to
    | overlay on top of the base template when merging secrets. For example,
    | if the environment is 'production' and this is set to 'env', it will
    | look for a file named 'production.env' in the specified directory.
    |
    */
    'environment_templates' => env('KEEP_ENV_TEMPLATES', 'env'),

    /*
    |--------------------------------------------------------------------------
    | Default vault name
    |--------------------------------------------------------------------------
    |
    | This set the default vault used for secrets.
    |
    */
    'default' => env('KEEP_VAULT', 'aws_ssm'),

    /*
    |--------------------------------------------------------------------------
    | All available vaults
    |--------------------------------------------------------------------------
    |
    | Here you can default a list of vaults that are configured and available
    | to your application. This is only needed if you have more than one
    | vault. Otherwise, the default vault will be the only available one.
    |
    */
    'available' => explode(',', env('KEEP_AVAILABLE_VAULTS', env('KEEP_VAULT', 'aws_ssm'))),

    /*
    |--------------------------------------------------------------------------
    | Vault configurations
    |--------------------------------------------------------------------------
    |
    | This defines all the vault configurations for your application. Each
    | vault refers to a supported driver. You can have multiple vaults
    | using the same driver with different settings.
    |
    */
    'vaults' => [
        'aws_ssm' => [
            'driver' => 'aws_ssm',
            'prefix' => env('KEEP_SSM_PREFIX'),
            'region' => env('KEEP_AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        ],
    ],
];
