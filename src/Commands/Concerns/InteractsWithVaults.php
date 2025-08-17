<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Facades\Keep;
use STS\Keep\Vaults\AbstractVault;

trait InteractsWithVaults
{
    protected AbstractVault $vault;

    public function vault(): AbstractVault
    {
        return $this->vault ??= Keep::vault($this->vaultName())->forEnvironment($this->environment());
    }
}