<?php

namespace STS\Keep\Server\Controllers;

use Exception;

class SecretController extends ApiController
{
    public function list(): array
    {
        try {
            $vault = $this->getVault();
            $secrets = $vault->list();
            
            return $this->success([
                'secrets' => $secrets->toApiArray($this->isUnmasked())
            ]);
        } catch (Exception $e) {
            return [
                'secrets' => [],
                'error' => 'Could not access vault: ' . $e->getMessage()
            ];
        }
    }

    public function get(string $key): array
    {
        $vault = $this->getVault();
        $secret = $vault->get(urldecode($key));
        
        if (!$secret) {
            return $this->error('Secret not found', 404);
        }
        
        return $this->success([
            'secret' => $secret->toApiArray($this->isUnmasked())
        ]);
    }

    public function create(): array
    {
        if (!isset($this->body['key']) || !isset($this->body['value'])) {
            return $this->error('Missing key or value');
        }
        
        $vault = $this->getVault();
        $vault->set($this->body['key'], $this->body['value']);
        
        return $this->success([
            'success' => true,
            'message' => "Secret '{$this->body['key']}' created"
        ]);
    }

    public function update(string $key): array
    {
        if (!isset($this->body['value'])) {
            return $this->error('Missing value');
        }
        
        $vault = $this->getVault();
        $vault->set(urldecode($key), $this->body['value']);
        
        return $this->success([
            'success' => true,
            'message' => "Secret '{$key}' updated"
        ]);
    }

    public function delete(string $key): array
    {
        $vault = $this->getVault();
        $vault->delete(urldecode($key));
        
        return $this->success([
            'success' => true,
            'message' => "Secret '{$key}' deleted"
        ]);
    }

    public function search(): array
    {
        $q = $this->query['q'] ?? '';
        
        if (empty($q)) {
            return $this->error('Missing search query');
        }
        
        $vault = $this->getVault();
        $secrets = $vault->list();
        
        $results = $secrets->filter(function($secret) use ($q) {
            return stripos($secret->value(), $q) !== false ||
                   stripos($secret->key(), $q) !== false;
        });
        
        return $this->success([
            'secrets' => $results->map(fn($secret) => array_merge(
                $secret->toApiArray($this->isUnmasked()),
                ['match' => stripos($secret->value(), $q) !== false ? 'value' : 'key']
            ))->values()->toArray()
        ]);
    }

    public function rename(string $oldKey): array
    {
        if (!isset($this->body['newKey'])) {
            return $this->error('Missing newKey');
        }
        
        $newKey = $this->body['newKey'];
        if ($oldKey === $newKey) {
            return $this->error('New key must be different from old key');
        }
        
        $vault = $this->getVault();
        
        // Check if old key exists
        try {
            $secret = $vault->get(urldecode($oldKey));
            if (!$secret) {
                return $this->error('Secret not found', 404);
            }
        } catch (\Exception $e) {
            return $this->error('Secret not found', 404);
        }
        
        // Check if new key already exists
        try {
            $existing = $vault->get($newKey);
            if ($existing) {
                return $this->error('A secret with the new key already exists');
            }
        } catch (\Exception $e) {
            // Good, new key doesn't exist
        }
        
        // Create new secret with new key
        $vault->set($newKey, $secret->value());
        
        // Delete old secret
        $vault->delete(urldecode($oldKey));
        
        return $this->success([
            'success' => true,
            'message' => "Secret renamed from '{$oldKey}' to '{$newKey}'"
        ]);
    }

    public function copyToStage(string $key): array
    {
        if (!isset($this->body['targetStage'])) {
            return $this->error('Missing targetStage');
        }
        
        $targetStage = $this->body['targetStage'];
        
        // Get source vault (from vault/stage query params or body)
        $sourceVault = $this->getVault();
        
        // Get the secret from source
        try {
            $secret = $sourceVault->get(urldecode($key));
            if (!$secret) {
                return $this->error('Secret not found', 404);
            }
        } catch (\Exception $e) {
            return $this->error('Secret not found', 404);
        }
        
        // Get the target vault (can be different vault)
        $targetVaultName = $this->body['targetVault'] ?? ($this->body['vault'] ?? $this->query['vault'] ?? $this->manager->getDefaultVault());
        $targetVault = $this->manager->vault($targetVaultName, $targetStage);
        
        // Copy to target
        $targetVault->set(urldecode($key), $secret->value());
        
        return $this->success([
            'success' => true,
            'message' => "Secret '{$key}' copied to {$targetVaultName}:{$targetStage}"
        ]);
    }

    public function history(string $key): array
    {
        $vault = $this->getVault();
        $limit = isset($this->query['limit']) ? (int)$this->query['limit'] : 10;
        
        try {
            $historyCollection = $vault->history(urldecode($key), new \STS\Keep\Data\Collections\FilterCollection(), $limit);
            
            // Apply masking if not unmasked
            if (!$this->isUnmasked()) {
                $historyCollection = $historyCollection->withMaskedValues();
            }
            
            $history = $historyCollection->map(function($entry) {
                return [
                    'version' => $entry->version(),
                    'value' => $entry->value() ?? null,
                    'dataType' => $entry->dataType(),
                    'modifiedDate' => $entry->formattedDate(),
                    'modifiedBy' => $entry->lastModifiedUser() ?? 'unknown',
                    'timestamp' => $entry->lastModifiedDate()?->toISOString()
                ];
            })->toArray();
            
            return $this->success([
                'history' => $history,
                'key' => urldecode($key),
                'vault' => $this->query['vault'] ?? $this->manager->getDefaultVault(),
                'stage' => $this->query['stage'] ?? $this->manager->getDefaultStage()
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve history: ' . $e->getMessage());
        }
    }
}