<?php

namespace STS\Keep\Commands\Concerns;

use STS\Keep\Facades\Keep;
use STS\Keep\Vaults\AbstractVault;

trait InteractsWithVaults
{
    protected ?AbstractVault $vault = null;

    public function vault(): AbstractVault
    {
        return $this->vault ??= Keep::vault($this->vaultName())->forStage($this->stage());
    }

    /**
     * Reset cached vault instance (used in testing)
     */
    public function resetVault(): void
    {
        $this->vault = null;
    }
}
