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
}