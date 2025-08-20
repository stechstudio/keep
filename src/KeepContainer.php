<?php

namespace STS\Keep;

use Illuminate\Container\Container;
use STS\Keep\KeepManager;

class KeepContainer extends Container
{
    public function runningUnitTests(): bool
    {
        // Return true when running tests to enable fallbacks
        return ($_ENV['LARAVEL_PROMPTS_INTERACT'] ?? null) === '0' ||
               getenv('LARAVEL_PROMPTS_INTERACT') === '0' ||
               defined('PHPUNIT_COMPOSER_INSTALL');
    }
    
    public function environment(...$environments): bool
    {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
        
        if (empty($environments)) {
            return $env;
        }
        
        return in_array($env, $environments);
    }
    
    /**
     * Get the global KeepManager instance
     */
    public static function manager(): KeepManager
    {
        return static::getInstance()->make(KeepManager::class);
    }
}