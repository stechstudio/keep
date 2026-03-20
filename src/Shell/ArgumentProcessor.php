<?php

namespace STS\Keep\Shell;

/**
 * Processes positional arguments for shell commands.
 * Replaces the massive switch statement with a clean, configurable approach.
 */
class ArgumentProcessor
{
    /**
     * Command argument configurations
     * Defines how positional arguments map to command inputs
     */
    private const CONFIGURATIONS = [
        'set' => [
            'arguments' => ['key', 'value'],
        ],
        'get' => [
            'arguments' => ['key'],
        ],
        'history' => [
            'arguments' => ['key'],
            'flags' => ['unmask'],
        ],
        'delete' => [
            'arguments' => ['key'],
            'flags' => ['force'],
        ],
        'copy' => [
            'arguments' => ['key'],
            'options' => [
                1 => '--to', // Second positional becomes --to option
            ],
            'flags' => ['overwrite', 'dry-run'],
        ],
        'import' => [
            'arguments' => ['from'],
            'flags' => ['overwrite', 'skip-existing', 'dry-run'],
        ],
        'rename' => [
            'arguments' => ['old', 'new'],
            'flags' => ['force'],
        ],
        'search' => [
            'arguments' => ['query'],
            'flags' => ['unmask', 'case-sensitive'],
        ],
        'show' => [
            'flags' => ['unmask'],
        ],
        'env:add' => [
            'arguments' => ['name'],
        ],
        'diff' => [
            'collect' => '--env',
            'flags' => ['unmask'],
        ],
        'verify' => [
            'flags' => ['verbose'],
        ],
        'export' => [
            'flags' => ['all', 'unmask'],
            'options' => [
                0 => '--file',
            ],
        ],
    ];
    
    /**
     * Process positional arguments for a command
     */
    public static function process(string $command, array $positionals, array &$input): void
    {
        $config = self::CONFIGURATIONS[$command] ?? null;
        
        if (!$config) {
            return;
        }
        
        // Process regular arguments
        if (isset($config['arguments'])) {
            self::processArguments($positionals, $config['arguments'], $input);
        }
        
        // Process flags (keywords that become boolean options)
        if (isset($config['flags'])) {
            self::processFlags($positionals, $config['flags'], $input);
        }
        
        // Process options (positionals that map to specific options)
        if (isset($config['options'])) {
            self::processOptions($positionals, $config['options'], $input);
        }

        // Collect remaining positionals into a single comma-separated option
        if (isset($config['collect'])) {
            self::processCollect($positionals, $config['collect'], $config['flags'] ?? [], $input);
        }
    }
    
    /**
     * Map positional arguments to named arguments
     */
    private static function processArguments(array $positionals, array $argumentNames, array &$input): void
    {
        foreach ($argumentNames as $index => $name) {
            if (isset($positionals[$index])) {
                $input[$name] = $positionals[$index];
            }
        }
    }
    
    /**
     * Convert flag keywords to boolean options
     */
    private static function processFlags(array $positionals, array $flags, array &$input): void
    {
        foreach ($flags as $flag) {
            if (in_array($flag, $positionals)) {
                $input['--' . $flag] = true;
            }
        }
    }
    
    /**
     * Map specific positional indices to options
     */
    private static function processOptions(array $positionals, array $options, array &$input): void
    {
        foreach ($options as $index => $option) {
            if (isset($positionals[$index])) {
                $input[$option] = $positionals[$index];
            }
        }
    }

    /**
     * Collect all non-flag positionals into a single comma-separated option
     */
    private static function processCollect(array $positionals, string $option, array $flags, array &$input): void
    {
        $values = array_filter($positionals, fn ($p) => !in_array($p, $flags));

        if (!empty($values)) {
            $input[$option] = implode(',', $values);
        }
    }
}