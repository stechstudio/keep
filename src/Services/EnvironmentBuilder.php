<?php

namespace STS\Keep\Services;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Template;
use STS\Keep\Vaults\AbstractVault;

class EnvironmentBuilder
{
    /**
     * Build environment variables array from secrets
     * 
     * @param SecretCollection $secrets
     * @param bool $inheritCurrent Whether to inherit current environment
     * @return array<string, string> Key-value pairs for environment
     */
    public function buildFromSecrets(
        SecretCollection $secrets,
        bool $inheritCurrent = true
    ): array {
        $env = $inheritCurrent ? getenv() : [];
        
        foreach ($secrets as $secret) {
            // Use sanitizedKey() for env-compatible keys
            $envKey = $secret->sanitizedKey();
            $env[$envKey] = $secret->value() ?? '';
        }
        
        return $env;
    }
    
    /**
     * Build environment from template file
     *
     * @param Template $template
     * @param array<AbstractVault> $vaults Array of vaults keyed by slug
     * @param bool $inheritCurrent Whether to inherit current environment
     * @return array<string, string> Key-value pairs for environment
     */
    public function buildFromTemplate(
        Template $template,
        array $vaults,
        bool $inheritCurrent = true
    ): array {
        $env = $inheritCurrent ? getenv() : [];
        
        // Get all placeholders from template
        $placeholders = $template->placeholders();
        
        foreach ($placeholders as $placeholder) {
            $vaultSlug = $placeholder->vault;
            $key = $placeholder->key;
            
            if (!isset($vaults[$vaultSlug])) {
                continue; // Skip if vault not available
            }
            
            try {
                $secret = $vaults[$vaultSlug]->get($key);
                // Use the env key from the placeholder (e.g., DB_HOST) not the secret key
                $envKey = $placeholder->envKey;
                $env[$envKey] = $secret->value() ?? '';
            } catch (\Exception $e) {
                // Secret not found, will be handled by missing strategy
                continue;
            }
        }
        
        return $env;
    }
    
    /**
     * Clear sensitive data from memory
     */
    public function clearEnvironment(array &$env): void
    {
        foreach ($env as $key => $value) {
            unset($env[$key]);
        }
    }
}