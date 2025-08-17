<?php

namespace STS\Keep\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \STS\Keep\KeepManager
 */
class Keep extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \STS\Keep\KeepManager::class;
    }
}
