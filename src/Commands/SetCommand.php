<?php

namespace STS\Keep\Commands;

class SetCommand extends BaseCommand
{
    public $signature = 'set 
        {key? : The secret key}
        {value? : The secret value}
        {--vault= : The vault to use}
        {--stage= : The stage to use}
        {--plain : Do not encrypt the value}';

    public $description = 'Set the value of a stage secret in a specified vault';

    public function process()
    {
        // Validate key using strict user input validation
        $key = $this->key();
        $this->validateUserKey($key);

        $context = $this->vaultContext();
        $secret = $context->createVault()->set($key, $this->value(), $this->secure());

        $action = $secret->revision() === 1 ? 'created' : 'updated';
        $this->success(sprintf('Secret [<secret-name>%s</secret-name>] %s in vault [<context>%s</context>].',
            $secret->path(),
            $action,
            $secret->vault()->name()
        ));
    }

    /**
     * Validate a user-provided key for safe vault operations.
     * More permissive than .env requirements to support various use cases.
     */
    protected function validateUserKey(string $key): void
    {
        $trimmed = trim($key);

        // Allow letters, digits, underscores, and hyphens (common in cloud services)
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $trimmed)) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' contains invalid characters. ".
                'Only letters, numbers, underscores, and hyphens are allowed.'
            );
        }

        // Length validation (reasonable limits for secret names)
        if (strlen($trimmed) < 1 || strlen($trimmed) > 255) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' must be 1-255 characters long."
            );
        }

        // Cannot start with hyphen (could be interpreted as command flag)
        if (str_starts_with($trimmed, '-')) {
            throw new \InvalidArgumentException(
                "Secret key '{$key}' cannot start with hyphen."
            );
        }
    }
}
