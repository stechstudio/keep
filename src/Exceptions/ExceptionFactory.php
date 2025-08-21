<?php

namespace STS\Keep\Exceptions;

class ExceptionFactory
{
    /**
     * Create a SecretNotFoundException with minimal required context.
     */
    public static function secretNotFound(string $key, ?string $vault = null, ?string $suggestion = null): SecretNotFoundException
    {
        $message = $vault
            ? "Unable to find secret for key [{$key}] in vault [{$vault}]"
            : "Unable to find secret for key [{$key}]";

        return (new SecretNotFoundException($message))
            ->withContext(
                vault: $vault,
                key: $key,
                suggestion: $suggestion ?: ($vault ? "Check if this secret exists using 'keep list'" : null)
            );
    }

    /**
     * Create a SecretNotFoundException for template processing with line context.
     */
    public static function secretNotFoundInTemplate(
        string $key,
        string $vault,
        ?string $path = null,
        ?int $lineNumber = null
    ): SecretNotFoundException {
        // Use path (secret key) in message if available, otherwise use env var key
        $secretKey = $path ?: $key;

        return (new SecretNotFoundException("Unable to find secret for key [{$secretKey}] in vault [{$vault}]"))
            ->withContext(
                vault: $vault,
                key: $key,
                path: $path,
                lineNumber: $lineNumber,
                suggestion: "Check if this secret exists using 'keep list'"
            );
    }

    /**
     * Create a KeepException with vault context.
     */
    public static function vaultError(string $message, ?string $vault = null, ?string $stage = null): KeepException
    {
        return (new KeepException($message))
            ->withContext(
                vault: $vault,
                stage: $stage
            );
    }

    /**
     * Create a KeepException from an AWS error with vault context.
     */
    public static function awsError(string $awsMessage, ?string $vault = null, ?string $key = null): KeepException
    {
        return (new KeepException($awsMessage))
            ->withContext(
                vault: $vault,
                key: $key
            );
    }

    /**
     * Create a KeepException with details in context.
     */
    public static function withDetails(string $message, string $details): KeepException
    {
        return (new KeepException($message))
            ->withContext(details: $details);
    }
}
