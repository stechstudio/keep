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
        if ($error = $this->requireFields(['content'])) {
            return $error;
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
                $this->getParam('only'),
                $this->getParam('except')
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
        if ($error = $this->requireFields(['content', 'strategy'])) {
            return $error;
        }
        
        // Validate strategy
        $validStrategies = [
            ImportService::STRATEGY_OVERWRITE,
            ImportService::STRATEGY_SKIP,
            ImportService::STRATEGY_FAIL
        ];
        
        $strategy = $this->getParam('strategy');
        if (!in_array($strategy, $validStrategies)) {
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
                $strategy,
                $this->getParam('only'),
                $this->getParam('except'),
                (bool)$this->getParam('dry_run', false)
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