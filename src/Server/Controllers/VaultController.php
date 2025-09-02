<?php

namespace STS\Keep\Server\Controllers;

use Exception;
use STS\Keep\KeepApplication;

class VaultController extends ApiController
{
    public function list(): array
    {
        $vaults = $this->manager->getConfiguredVaults();
        
        $vaultList = $vaults->map(function($config) {
            $slug = $config->slug();
            $name = $config->name();
            $driver = $config->driver();
            
            // Get the vault class to access its friendly NAME constant if available
            $vaultClass = null;
            foreach ($this->manager->getAvailableVaults() as $class) {
                if ($class::DRIVER === $driver) {
                    $vaultClass = $class;
                    break;
                }
            }
            
            // Use the name from config, or fall back to the class NAME constant
            $friendlyName = $name ?: ($vaultClass ? $vaultClass::NAME : ucfirst($driver));
            
            return [
                'name' => $slug,  // This is what we'll use as the value
                'display' => $friendlyName . ' (' . $slug . ')'  // This is what we'll show
            ];
        });
        
        return $this->success([
            'vaults' => $vaultList->values()->toArray()
        ]);
    }

    public function listStages(): array
    {
        $settings = $this->manager->getSettings();
        $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
        
        return $this->success([
            'stages' => $stages
        ]);
    }

    public function getSettings(): array
    {
        $settings = $this->manager->getSettings();
        
        return $this->success([
            'app_name' => $settings['app_name'] ?? 'Keep',
            'stages' => $settings['stages'] ?? ['local', 'staging', 'production'],
            'default_vault' => $this->manager->getDefaultVault(),
            'keep_version' => KeepApplication::VERSION
        ]);
    }

    public function verify(): array
    {
        $results = [];
        
        foreach ($this->manager->getConfiguredVaults() as $vaultConfig) {
            $vaultName = $vaultConfig->slug();
            try {
                $vault = $this->manager->vault($vaultName, 'local');
                // Simple verification - just try to list
                $vault->list();
                $results[$vaultName] = ['success' => true];
            } catch (Exception $e) {
                $results[$vaultName] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $this->success([
            'results' => $results
        ]);
    }

    public function diff(): array
    {
        $stages = isset($this->query['stages']) 
            ? explode(',', $this->query['stages']) 
            : ['local', 'staging', 'production'];
            
        $vaults = isset($this->query['vaults']) 
            ? explode(',', $this->query['vaults']) 
            : array_map(fn($v) => $v->slug(), $this->manager->getConfiguredVaults()->toArray());
        
        $matrix = [];
        
        foreach ($vaults as $vaultName) {
            try {
                foreach ($stages as $stage) {
                    $vault = $this->manager->vault($vaultName, $stage);
                    $secrets = $vault->list();
                    foreach ($secrets as $secret) {
                        $matrix[$secret->key()][$vaultName][$stage] = $secret->masked();
                    }
                }
            } catch (Exception $e) {
                // Skip vault if it fails
            }
        }
        
        return $this->success([
            'diff' => $matrix,
            'stages' => $stages,
            'vaults' => $vaults
        ]);
    }
}