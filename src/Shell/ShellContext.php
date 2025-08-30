<?php

namespace STS\Keep\Shell;

use STS\Keep\Data\Settings;
use STS\Keep\Facades\Keep;

class ShellContext
{
    private string $currentStage;
    private string $currentVault;
    private array $history = [];
    private array $cachedSecrets = [];
    private ?int $cacheTimestamp = null;
    private const CACHE_TTL = 60; // Cache for 60 seconds
    
    public function __construct(?string $initialStage = null, ?string $initialVault = null)
    {
        $settings = Settings::load();
        
        $this->currentStage = $initialStage 
            ?? $settings->stages()[0] 
            ?? 'development';
            
        $this->currentVault = $initialVault 
            ?? $settings->defaultVault() 
            ?? 'test';
    }
    
    public function getStage(): string
    {
        return $this->currentStage;
    }
    
    public function setStage(string $stage): void
    {
        $this->currentStage = $stage;
        $this->invalidateCache();
    }
    
    public function getVault(): string
    {
        return $this->currentVault;
    }
    
    public function setVault(string $vault): void
    {
        $this->currentVault = $vault;
        $this->invalidateCache();
    }
    
    public function getPrompt(): string
    {
        return sprintf('keep (%s:%s)> ', $this->currentVault, $this->currentStage);
    }
    
    public function addToHistory(string $command): void
    {
        $this->history[] = $command;
        if (count($this->history) > 100) {
            array_shift($this->history);
        }
    }
    
    public function getHistory(): array
    {
        return $this->history;
    }
    
    public function getAvailableStages(): array
    {
        $settings = Settings::load();
        return $settings ? $settings->stages() : [];
    }
    
    public function getAvailableVaults(): array
    {
        try {
            $vaults = Keep::getConfiguredVaults();
            return $vaults->map(fn($vault) => $vault->slug())->values()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getCachedSecretNames(): array
    {
        // Check if cache is still valid
        if ($this->cacheTimestamp && (time() - $this->cacheTimestamp) < self::CACHE_TTL) {
            return $this->cachedSecrets;
        }
        
        // Reload cache
        try {
            // Check if Keep is initialized first
            if (!Keep::isInitialized()) {
                // Can't load secrets without initialization
                return [];
            }
            
            $vault = Keep::vault($this->currentVault, $this->currentStage);
            $secrets = $vault->list();
            $this->cachedSecrets = $secrets->allKeys()->toArray();
            $this->cacheTimestamp = time();
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("ShellContext: Failed to load secrets - " . $e->getMessage());
            // If we can't load secrets, return empty array
            $this->cachedSecrets = [];
        }
        
        return $this->cachedSecrets;
    }
    
    public function invalidateCache(): void
    {
        $this->cachedSecrets = [];
        $this->cacheTimestamp = null;
    }
    
    public function toArray(): array
    {
        return [
            'stage' => $this->currentStage,
            'vault' => $this->currentVault,
            'available_stages' => $this->getAvailableStages(),
            'available_vaults' => $this->getAvailableVaults(),
        ];
    }
}