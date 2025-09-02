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

    protected function getVault(string $defaultStage = 'local'): array
    {
        $vaultName = $this->body['vault'] ?? $this->query['vault'] ?? $this->manager->getDefaultVault();
        $stage = $this->body['stage'] ?? $this->query['stage'] ?? $defaultStage;
        
        return [$this->manager->vault($vaultName, $stage), $vaultName, $stage];
    }

    protected function isUnmasked(): bool
    {
        return isset($this->query['unmask']) && $this->query['unmask'] === 'true';
    }
}