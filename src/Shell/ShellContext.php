<?php

namespace STS\Keep\Shell;

use STS\Keep\Data\Settings;
use STS\Keep\Facades\Keep;

class ShellContext
{
    private string $currentStage;
    private string $currentVault;
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
    
    public function getAvailableStages(): array
    {
        try {
            return Keep::getStages();
        } catch (\Exception) {
            return [];
        }
    }
    
    public function getAvailableVaults(): array
    {
        try {
            return Keep::getConfiguredVaults()
                ->map->slug()
                ->values()
                ->toArray();
        } catch (\Exception) {
            return [];
        }
    }
    
    public function getCachedSecretNames(): array
    {
        if ($this->isCacheValid()) {
            return $this->cachedSecrets;
        }
        
        $this->refreshSecretCache();
        
        return $this->cachedSecrets;
    }
    
    public function invalidateCache(): void
    {
        $this->cachedSecrets = [];
        $this->cacheTimestamp = null;
    }
    
    protected function isCacheValid(): bool
    {
        return $this->cacheTimestamp 
            && (time() - $this->cacheTimestamp) < self::CACHE_TTL;
    }
    
    protected function refreshSecretCache(): void
    {
        try {
            $this->ensureKeepInitialized();
            
            $vault = Keep::vault($this->currentVault, $this->currentStage);
            $secrets = $vault->list();
            
            $this->cachedSecrets = $secrets->allKeys()->toArray();
            $this->cacheTimestamp = time();
        } catch (\Exception) {
            $this->cachedSecrets = [];
        }
    }
    
    protected function ensureKeepInitialized(): void
    {
        $container = \STS\Keep\KeepContainer::getInstance();
        
        if ($container->bound(\STS\Keep\KeepManager::class)) {
            return;
        }
        
        $settings = Settings::load();
        $vaultConfigs = \STS\Keep\Data\Collections\VaultConfigCollection::load();
        
        $container->instance(
            \STS\Keep\KeepManager::class,
            new \STS\Keep\KeepManager($settings, $vaultConfigs)
        );
    }
}