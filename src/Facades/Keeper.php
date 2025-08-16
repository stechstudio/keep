<?php

namespace STS\Keeper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \STS\Keeper\KeeperManager
 */
class Keeper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \STS\Keeper\KeeperManager::class;
    }
}
