<?php

namespace STS\Keeper\Commands\Concerns;

use STS\Keeper\Facades\Keeper;
use STS\Keeper\Vaults\AbstractKeeperVault;

trait InteractsWithVaults
{
    protected AbstractKeeperVault $vault;

    public function vault(): AbstractKeeperVault
    {
        return $this->vault ??= Keeper::vault($this->vaultName())->forEnvironment($this->environment());
    }
}