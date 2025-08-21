<?php

namespace STS\Keep\Facades;

use STS\Keep\KeepContainer;
use STS\Keep\KeepManager;

/**
 * @mixin KeepManager
 */
class Keep
{
    public static function __callStatic(string $name, array $arguments)
    {
        return KeepContainer::manager()->$name(...$arguments);
    }
}
