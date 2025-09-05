<?php

namespace STS\Keep\Services;

/**
 * Manages local, non-versioned storage for user-specific data
 * like permissions, workspace settings, and preferences.
 */
class LocalStorage
{
    protected string $localPath;
    
    public function __construct()
    {
        $this->localPath = getcwd() . '/.keep/local';
        $this->ensureLocalDirectoryExists();
    }
    
    /**
     * Get the permissions data
     */
    public function getPermissions(): array
    {
        $path = $this->localPath . '/permissions.json';
        
        if (!file_exists($path)) {
            return [];
        }
        
        $content = file_get_contents($path);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Save permissions data
     */
    public function savePermissions(array $permissions): void
    {
        $path = $this->localPath . '/permissions.json';
        $json = json_encode($permissions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json);
    }
    
    /**
     * Get permissions for a specific vault
     */
    public function getVaultPermissions(string $vaultName): array
    {
        $permissions = $this->getPermissions();
        return $permissions[$vaultName] ?? [];
    }
    
    /**
     * Save permissions for a specific vault
     */
    public function saveVaultPermissions(string $vaultName, array $stagePermissions): void
    {
        $permissions = $this->getPermissions();
        $permissions[$vaultName] = $stagePermissions;
        $permissions['verified_at'] = date('c');
        $this->savePermissions($permissions);
    }
    
    /**
     * Get the workspace configuration (future use)
     */
    public function getWorkspace(): array
    {
        $path = $this->localPath . '/workspace.json';
        
        if (!file_exists($path)) {
            return [];
        }
        
        $content = file_get_contents($path);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Save workspace configuration (future use)
     */
    public function saveWorkspace(array $workspace): void
    {
        $path = $this->localPath . '/workspace.json';
        $workspace['updated_at'] = date('c');
        $json = json_encode($workspace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json);
    }
    
    /**
     * Clear all local data (useful for testing or reset)
     */
    public function clear(): void
    {
        $files = glob($this->localPath . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Ensure the local directory exists
     */
    protected function ensureLocalDirectoryExists(): void
    {
        if (!is_dir($this->localPath)) {
            mkdir($this->localPath, 0755, true);
        }
    }
    
    /**
     * Check if local storage exists
     */
    public function exists(): bool
    {
        return is_dir($this->localPath);
    }
}