<?php

namespace STS\Keep\Services;

use STS\Keep\Data\Template;

class VaultDiscovery
{
    /**
     * Extract unique vault names from template placeholders.
     */
    public function discoverFromTemplate(Template $template): array
    {
        return $template->allReferencedVaults();
    }
}
