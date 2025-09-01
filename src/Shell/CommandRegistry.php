<?php

namespace STS\Keep\Shell;

/**
 * Central registry for shell commands, aliases, and metadata.
 * Single source of truth for all command-related configuration.
 */
class CommandRegistry
{
    /**
     * Command aliases mapping short forms to full commands
     */
    private const ALIASES = [
        'g' => 'get',
        's' => 'set',
        'd' => 'delete',
        'l' => 'show',
        'ls' => 'show',
        'u' => 'use',
        'ctx' => 'context',
        'cls' => 'clear',
        'q' => 'quit',
        '?' => 'help',
    ];
    
    /**
     * Commands that don't require stage context
     */
    private const NO_STAGE_COMMANDS = ['diff', 'copy', 'info', 'verify'];
    
    /**
     * Commands that don't require vault context
     */
    private const NO_VAULT_COMMANDS = ['export', 'copy', 'info', 'verify'];
    
    /**
     * Commands that modify data
     */
    private const WRITE_COMMANDS = ['set', 'delete', 'copy', 'import', 'rename'];
    
    /**
     * Built-in shell commands (not Keep commands)
     */
    private const BUILTIN_COMMANDS = [
        'exit', 'quit', 'q', 'clear', 'cls', 'help', '?', 
        'context', 'ctx', 'stage', 'vault', 'use', 'u', 'colors'
    ];
    
    /**
     * Keep commands available in the shell
     */
    private const KEEP_COMMANDS = [
        'get', 'set', 'delete', 'show', 'copy', 'export',
        'diff', 'verify', 'info', 'history', 'rename', 'search', 'import'
    ];
    
    /**
     * Commands that use interactive flow in the shell
     */
    private const INTERACTIVE_COMMANDS = ['export'];
    
    /**
     * Resolve an alias to its full command name
     */
    public static function resolveAlias(string $command): string
    {
        return self::ALIASES[$command] ?? $command;
    }
    
    /**
     * Check if a command has an alias
     */
    public static function hasAlias(string $command): bool
    {
        return isset(self::ALIASES[$command]);
    }
    
    /**
     * Get all command aliases
     */
    public static function getAliases(): array
    {
        return self::ALIASES;
    }
    
    /**
     * Check if a command requires stage context
     */
    public static function requiresStage(string $command): bool
    {
        return !in_array($command, self::NO_STAGE_COMMANDS);
    }
    
    /**
     * Check if a command requires vault context
     */
    public static function requiresVault(string $command): bool
    {
        return !in_array($command, self::NO_VAULT_COMMANDS);
    }
    
    /**
     * Check if a command modifies data
     */
    public static function isWriteCommand(string $command): bool
    {
        return in_array($command, self::WRITE_COMMANDS);
    }
    
    /**
     * Check if a command is a built-in shell command
     */
    public static function isBuiltIn(string $command): bool
    {
        return in_array($command, self::BUILTIN_COMMANDS);
    }
    
    /**
     * Check if a command is a Keep command
     */
    public static function isKeepCommand(string $command): bool
    {
        $resolved = self::resolveAlias($command);
        return in_array($resolved, self::KEEP_COMMANDS);
    }
    
    /**
     * Get all available commands for tab completion
     */
    public static function getAllCommands(): array
    {
        return array_merge(
            self::KEEP_COMMANDS,
            self::BUILTIN_COMMANDS,
            array_keys(self::ALIASES)
        );
    }
    
    /**
     * Get all Keep commands
     */
    public static function getKeepCommands(): array
    {
        return self::KEEP_COMMANDS;
    }
    
    /**
     * Get all built-in commands
     */
    public static function getBuiltInCommands(): array
    {
        return self::BUILTIN_COMMANDS;
    }
    
    /**
     * Check if a command uses interactive flow in the shell
     */
    public static function isInteractive(string $command): bool
    {
        return in_array($command, self::INTERACTIVE_COMMANDS);
    }
}