<?php

namespace STS\Keep\Server\Controllers;

use Exception;
use STS\Keep\Data\Collections\FilterCollection;

class SecretController extends ApiController
{
    public function list(): array
    {
        $vault = $this->getVault();
        $secrets = $vault->list();
        
        return $this->success([
            'secrets' => $secrets->toApiArray($this->isUnmasked())
        ]);
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
        if ($error = $this->requireFields(['key', 'value', 'vault', 'stage'])) {
            return $error;
        }
        
        try {
            $vault = $this->getVault();
            $vault->set($this->body['key'], $this->body['value']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
        
        return $this->success([
            'success' => true,
            'message' => "Secret '{$this->body['key']}' created in vault '{$this->body['vault']}' stage '{$this->body['stage']}'"
        ]);
    }

    public function update(string $key): array
    {
        if ($error = $this->requireFields(['value', 'vault', 'stage'])) {
            return $error;
        }
        
        $vault = $this->getVault();
        $vault->set(urldecode($key), $this->body['value']);
        
        return $this->success([
            'success' => true,
            'message' => "Secret '{$key}' updated in vault '{$this->body['vault']}' stage '{$this->body['stage']}'"
        ]);
    }

    public function delete(string $key): array
    {
        if ($error = $this->requireFields(['vault', 'stage'])) {
            return $error;
        }
        
        $vault = $this->getVault();
        $decodedKey = urldecode($key);
        
        try {
            $vault->delete($decodedKey);
        } catch (\STS\Keep\Exceptions\SecretNotFoundException $e) {
            // For verify test keys, provide a more helpful error message
            if (str_starts_with($decodedKey, '__keep_verify_') || str_starts_with($decodedKey, 'keep-verify-')) {
                throw new \STS\Keep\Exceptions\SecretNotFoundException(
                    "Test key '{$decodedKey}' not found. This may be an orphaned verification key. " .
                    "It might have been created in a different namespace or stage context."
                );
            }
            throw $e;
        }
        
        return $this->success([
            'success' => true,
            'message' => "Secret '{$key}' deleted"
        ]);
    }

    public function search(): array
    {
        $q = $this->getParam('q', '');
        
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
        if ($error = $this->requireFields(['newKey', 'vault', 'stage'])) {
            return $error;
        }
        
        $newKey = $this->body['newKey'];
        if ($oldKey === $newKey) {
            return $this->error('New key must be different from old key');
        }
        
        // Validate the new secret key
        $validator = new SecretKeyValidator();
        try {
            $validator->validate($newKey);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
        
        $vault = $this->getVault();
        
        // Check if old key exists
        if(!$vault->has(urldecode($oldKey))) {
            return $this->error('Secret not found', 404);
        }
        
        // Check if new key already exists
        if($vault->has($newKey)) {
            return $this->error('A secret with the new key already exists');
        }


        $vault->set($newKey, $vault->get(urldecode($oldKey))->value());
        $vault->delete(urldecode($oldKey));
        
        return $this->success([
            'success' => true,
            'message' => "Secret renamed from '{$oldKey}' to '{$newKey}'"
        ]);
    }

    public function copyToStage(string $key): array
    {
        if ($error = $this->requireFields(['targetStage'])) {
            return $error;
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
        $targetVaultName = $this->getParam('targetVault', $this->getParam('vault', $this->manager->getDefaultVault()));
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
        $limit = (int)$this->getParam('limit', 10);
        
        try {
            $historyCollection = $vault->history(urldecode($key), new FilterCollection(), $limit);

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
                'stage' => $this->query['stage']
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve history: ' . $e->getMessage());
        }
    }
    
    /**
     * Get validation rules for secret keys.
     * This endpoint provides the rules to the frontend for client-side validation.
     */
    public function validationRules(): array
    {
        return $this->success([
            'rules' => (new SecretKeyValidator)->getValidationRules()
        ]);
    }
}