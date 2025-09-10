<?php

namespace STS\Keep\Server\Controllers;

use STS\Keep\KeepManager;

abstract class ApiController
{
    protected KeepManager $manager;
    protected array $query;
    protected array $body;

    public function __construct(KeepManager $manager, array $query = [], array $body = [])
    {
        $this->manager = $manager;
        $this->query = $query;
        $this->body = $body;
    }

    protected function success(array $data = [], int $status = 200): array
    {
        return [...$data, '_status' => $status];
    }

    protected function error(string $message, int $status = 400): array
    {
        return ['error' => $message, '_status' => $status];
    }

    protected function getVault(string $defaultEnv = 'local')
    {
        $vaultName = $this->body['vault'] ?? $this->query['vault'] ?? $this->manager->getDefaultVault();
        $env = $this->body['env'] ?? $this->query['env'] ?? $defaultEnv;
        
        return $this->manager->vault($vaultName, $env);
    }

    protected function isUnmasked(): bool
    {
        return isset($this->query['unmask']) && $this->query['unmask'] === 'true';
    }

    protected function requireFields(array $fields): ?array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($this->body[$field]) || $this->body[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            return $this->error('Missing required fields: ' . implode(', ', $missing));
        }
        
        return null;
    }

    protected function getParam(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    protected function hasParam(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }
}