<?php

namespace STS\Keep\Server\Controllers;

use STS\Keep\Data\Settings;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Services\TemplateService;
use STS\Keep\Services\SecretLoader;
use STS\Keep\Services\VaultDiscovery;

class TemplateController extends ApiController
{
    private TemplateService $templateService;
    private SecretLoader $secretLoader;
    private VaultDiscovery $vaultDiscovery;
    
    public function __construct(\STS\Keep\KeepManager $manager, array $query = [], array $body = [])
    {
        parent::__construct($manager, $query, $body);
        
        $settings = Settings::load();
        $templatePath = $settings ? $settings->templatePath() : './env';
        
        $this->templateService = new TemplateService($templatePath);
        $this->secretLoader = new SecretLoader();
        $this->vaultDiscovery = new VaultDiscovery();
    }
    
    /**
     * GET /api/templates - List all templates
     */
    public function index(): array
    {
        try {
            $templates = $this->templateService->scanTemplates();
            
            // Add friendly stage names
            $settings = Settings::load();
            $stages = $settings ? $settings->stages() : [];
            
            foreach ($templates as &$template) {
                $template['stageDisplay'] = $this->getStageDisplay($template['stage'], $stages);
            }
            
            return $this->success([
                'templates' => $templates,
                'templatePath' => $settings ? $settings->templatePath() : 'env',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * GET /api/templates/{filename} - Get template content
     */
    public function show(string $filename): array
    {
        try {
            $filename = urldecode($filename);
            
            if (!$filename) {
                return $this->error('Filename is required');
            }
            
            $template = $this->templateService->loadTemplate($filename);
            $placeholders = $template->placeholders();
            
            // Extract stage from filename
            $stage = $this->extractStageFromFilename($filename);
            
            return $this->success([
                'filename' => $filename,
                'content' => $template,
                'stage' => $stage,
                'placeholders' => $placeholders->toArray(),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * PUT /api/templates/{filename} - Save edited template
     */
    public function update(string $filename): array
    {
        try {
            $filename = urldecode($filename);
            $content = $this->body['content'] ?? '';
            
            if (!$filename) {
                return $this->error('Filename is required');
            }
            
            if ($content === '') {
                return $this->error('Content is required');
            }
            
            // Extract stage from filename
            $stage = $this->extractStageFromFilename($filename);
            
            if (!$stage) {
                return $this->error('Could not determine stage from filename');
            }
            
            // Save the template
            $filepath = $this->templateService->saveTemplate($stage, $content);
            
            return $this->success([
                'message' => 'Template saved successfully',
                'filepath' => $filepath,
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * POST /api/templates/generate - Generate template from existing secrets
     */
    public function generate(): array
    {
        try {
            $data = $this->body;
            $stage = $data['stage'] ?? null;
            $vaults = $data['vaults'] ?? [];
            $filename = $data['filename'] ?? null;
            
            if (!$stage) {
                return $this->error('Stage is required');
            }
            
            // Generate template content
            $content = $this->templateService->generateTemplate($stage, $vaults);
            
            // If filename provided, save it
            if ($filename) {
                $filepath = $this->templateService->saveTemplate($stage, $content);
                
                return $this->success([
                    'content' => $content,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'message' => 'Template generated and saved successfully',
                ]);
            }
            
            return $this->success([
                'content' => $content,
                'filename' => $this->templateService->getTemplateFilename($stage),
                'message' => 'Template generated successfully',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * POST /api/templates/validate - Validate template against stage
     */
    public function validate(): array
    {
        try {
            $data = $this->body;
            $content = $data['content'] ?? '';
            $stage = $data['stage'] ?? null;
            $filename = $data['filename'] ?? null;
            
            if (!$content && !$filename) {
                return $this->error('Either content or filename is required');
            }
            
            if (!$stage) {
                return $this->error('Stage is required');
            }
            
            // Load template from file if filename provided
            if ($filename && !$content) {
                $template = $this->templateService->loadTemplate($filename);
            } else {
                $template = new Template($content);
            }
            
            // Extract placeholders and validate
            $placeholders = $template->placeholders();
            $validationResults = $placeholders->validate(null, $stage);
            
            // Process validation results
            $errors = [];
            $warnings = [];
            $valid = true;
            
            foreach ($validationResults as $result) {
                if (!$result->valid) {
                    $valid = false;
                    $errors[] = [
                        'line' => $result->placeholder->line,
                        'key' => $result->placeholder->key,
                        'vault' => $result->vault,
                        'error' => $result->error,
                    ];
                }
            }
            
            // Check for unused secrets (warnings)
            $allVaults = $this->vaultDiscovery->discoverVaults($placeholders, $stage);
            foreach ($allVaults as $vaultName) {
                try {
                    $vault = \STS\Keep\Facades\Keep::vault($vaultName, $stage);
                    $allSecrets = $vault->list();
                    $referencedKeys = $placeholders->getReferencedKeysForVault($vaultName, $vaultName);
                    
                    foreach ($allSecrets as $secret) {
                        if (!in_array($secret->key(), $referencedKeys)) {
                            $warnings[] = [
                                'type' => 'unused',
                                'vault' => $vaultName,
                                'key' => $secret->key(),
                                'message' => "Secret exists but is not referenced in template",
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Skip vault if not accessible
                    continue;
                }
            }
            
            return $this->success([
                'valid' => $valid,
                'errors' => $errors,
                'warnings' => $warnings,
                'unusedSecrets' => array_filter($warnings, fn($w) => $w['type'] === 'unused'),
                'placeholderCount' => count($placeholders),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * POST /api/templates/process - Process template with actual values
     */
    public function process(): array
    {
        try {
            $data = $this->body;
            $content = $data['content'] ?? '';
            $stage = $data['stage'] ?? null;
            $filename = $data['filename'] ?? null;
            $strategy = $data['strategy'] ?? 'skip';
            
            if (!$content && !$filename) {
                return $this->error('Either content or filename is required');
            }
            
            if (!$stage) {
                return $this->error('Stage is required');
            }
            
            // Load template from file if filename provided
            if ($filename && !$content) {
                $template = $this->templateService->loadTemplate($filename);
            } else {
                $template = new Template($content);
            }
            
            // Get missing secret strategy
            $missingStrategy = MissingSecretStrategy::from($strategy);
            
            // Discover vaults and load secrets
            $placeholders = $template->placeholders();
            $vaultNames = $this->vaultDiscovery->discoverVaults($placeholders, $stage);
            $allSecrets = $this->secretLoader->loadFromVaults($vaultNames, $stage);
            
            // Process the template
            $processedContent = $template->merge($allSecrets, $missingStrategy);
            
            // Also run validation to provide feedback
            $validationResults = $placeholders->validate(null, $stage);
            
            return $this->success([
                'output' => $processedContent,
                'validation' => [
                    'valid' => !$validationResults->contains(fn($r) => !$r->valid),
                    'errorCount' => $validationResults->filter(fn($r) => !$r->valid)->count(),
                ],
                'placeholders' => $placeholders->toArray(),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * GET /api/templates/placeholders - Get all available placeholders for autocomplete
     */
    public function placeholders(): array
    {
        try {
            $stage = $this->query['stage'] ?? null;
            
            if (!$stage) {
                return $this->error('Stage is required');
            }
            
            $placeholders = [];
            
            // Get all configured vaults
            $vaultFiles = glob(getcwd() . '/.keep/vaults/*.json');
            
            foreach ($vaultFiles as $file) {
                $vaultName = basename($file, '.json');
                
                try {
                    $vault = \STS\Keep\Facades\Keep::vault($vaultName, $stage);
                    $secrets = $vault->list();
                    
                    foreach ($secrets as $secret) {
                        $placeholders[] = [
                            'placeholder' => "{{$vaultName}:{$secret->key()}}",
                            'vault' => $vaultName,
                            'key' => $secret->key(),
                            'value' => $secret->maskedValue(),
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip vault if not accessible
                    continue;
                }
            }
            
            return $this->success([
                'placeholders' => $placeholders,
                'count' => count($placeholders),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * POST /api/templates/create - Create new template (check if exists first)
     */
    public function create(): array
    {
        try {
            $data = $this->body;
            $stage = $data['stage'] ?? null;
            $vaults = $data['vaults'] ?? [];
            
            if (!$stage) {
                return $this->error('Stage is required');
            }
            
            // Check if template already exists
            if ($this->templateService->templateExists($stage)) {
                $filename = $this->templateService->getTemplateFilename($stage);
                return $this->error("Template already exists for stage '{$stage}': {$filename}");
            }
            
            // Generate and save template
            $content = $this->templateService->generateTemplate($stage, $vaults);
            $filepath = $this->templateService->saveTemplate($stage, $content);
            $filename = $this->templateService->getTemplateFilename($stage);
            
            return $this->success([
                'content' => $content,
                'filename' => $filename,
                'filepath' => $filepath,
                'message' => 'Template created successfully',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * DELETE /api/templates/{filename} - Delete a template
     */
    public function delete(string $filename): array
    {
        try {
            $filename = urldecode($filename);
            
            if (!$filename) {
                return $this->error('Filename is required');
            }
            
            $filepath = $this->templateService->getTemplatePath() . '/' . $filename;
            
            if (!file_exists($filepath)) {
                return $this->error('Template not found');
            }
            
            if (!unlink($filepath)) {
                return $this->error('Failed to delete template');
            }
            
            return $this->success([
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Extract stage from template filename
     */
    private function extractStageFromFilename(string $filename): ?string
    {
        if (preg_match('/^([^.]+)\.env$/', $filename, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get friendly display name for stage
     */
    private function getStageDisplay(string $stage, array $availableStages): string
    {
        // Direct match
        if (in_array($stage, $availableStages)) {
            return $stage;
        }
        
        // Common abbreviations
        $mappings = [
            'prod' => 'production',
            'dev' => 'development',
            'stg' => 'staging',
            'stage' => 'staging',
        ];
        
        if (isset($mappings[$stage]) && in_array($mappings[$stage], $availableStages)) {
            return $mappings[$stage];
        }
        
        return $stage;
    }
}