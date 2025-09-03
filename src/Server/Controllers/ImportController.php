<?php

namespace STS\Keep\Server\Controllers;

use STS\Keep\Services\ImportService;

class ImportController extends ApiController
{
    /**
     * Analyze an import without executing it
     * POST /api/import/analyze
     */
    public function analyze(): array
    {
        // Validate request
        if (!isset($this->body['content'])) {
            return $this->error('Missing content field');
        }
        
        $vault = $this->getVault();
        $importService = new ImportService();
        
        try {
            // Parse the env content
            $importSecrets = $importService->parseEnvContent($this->body['content']);
            
            // Get existing secrets
            $existingSecrets = $vault->list();
            
            // Analyze the import
            $analysis = $importService->analyzeImport(
                $importSecrets,
                $existingSecrets,
                $this->body['only'] ?? null,
                $this->body['except'] ?? null
            );
            
            return $this->success([
                'analysis' => $analysis
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to analyze import: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute an import
     * POST /api/import/execute
     */
    public function execute(): array
    {
        // Validate request
        if (!isset($this->body['content'])) {
            return $this->error('Missing content field');
        }
        
        if (!isset($this->body['strategy'])) {
            return $this->error('Missing strategy field');
        }
        
        // Validate strategy
        $validStrategies = [
            ImportService::STRATEGY_OVERWRITE,
            ImportService::STRATEGY_SKIP,
            ImportService::STRATEGY_FAIL
        ];
        
        if (!in_array($this->body['strategy'], $validStrategies)) {
            return $this->error('Invalid strategy. Must be one of: ' . implode(', ', $validStrategies));
        }
        
        $vault = $this->getVault();
        $importService = new ImportService();
        
        try {
            // Parse the env content
            $importSecrets = $importService->parseEnvContent($this->body['content']);
            
            // Execute the import
            $result = $importService->executeImport(
                $importSecrets,
                $vault,
                $this->body['strategy'],
                $this->body['only'] ?? null,
                $this->body['except'] ?? null,
                $this->body['dry_run'] ?? false
            );
            
            // Format response
            return $this->success([
                'imported' => $result['imported']->count(),
                'skipped' => $result['skipped']->count(),
                'failed' => $result['failed']->count(),
                'results' => $result['results'],
                'errors' => $result['errors'],
                'dry_run' => $result['dry_run']
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to execute import: ' . $e->getMessage());
        }
    }
}