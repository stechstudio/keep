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
    
    /**
     * Discover vaults from a PlaceholderCollection
     */
    public function discoverVaults(\STS\Keep\Data\Collections\PlaceholderCollection $placeholders, string $stage): array
    {
        $vaults = [];
        
        foreach ($placeholders as $placeholder) {
            if ($placeholder->vault) {
                $vaults[] = $placeholder->vault;
            }
        }
        
        return array_unique($vaults);
    }
}
