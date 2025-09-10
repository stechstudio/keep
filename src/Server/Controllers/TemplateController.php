<?php

namespace STS\Keep\Server\Controllers;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Settings;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Facades\Keep;
use STS\Keep\KeepManager;
use STS\Keep\Services\TemplateService;

class TemplateController extends ApiController
{
    private TemplateService $templateService;
    
    public function __construct(KeepManager $manager, array $query = [], array $body = [])
    {
        parent::__construct($manager, $query, $body);
        
        $settings = Settings::load();
        $templatePath = $settings ? $settings->templatePath() : 'env';
        
        $this->templateService = new TemplateService($templatePath);
    }
    
    /**
     * GET /api/templates - List all templates
     */
    public function index(): array
    {
        $templates = $this->templateService->scanTemplates();
        
        // Add friendly environment names
        $settings = Settings::load();
        $envs = $settings ? $settings->envs() : [];
        
        foreach ($templates as &$template) {
            $template['envDisplay'] = $this->getEnvDisplay($template['env'], $envs);
        }
        
        return $this->success([
            'templates' => $templates,
            'templatePath' => $settings ? $settings->templatePath() : 'env',
        ]);
    }
    
    /**
     * GET /api/templates/{filename} - Get template content
     */
    public function show(string $filename): array
    {
        $filename = urldecode($filename);
        
        if (!$filename) {
            return $this->error('Filename is required');
        }
        
        $template = $this->templateService->loadTemplate($filename);
        $placeholders = $template->placeholders();
        
        // Extract env from filename
        $env = $this->extractEnvFromFilename($filename);
        
        return $this->success([
            'filename' => $filename,
            'content' => $template->contents(),
            'env' => $env,
            'placeholders' => $placeholders->toArray(),
        ]);
    }
    
    /**
     * PUT /api/templates/{filename} - Save edited template
     */
    public function update(string $filename): array
    {
        $filename = urldecode($filename);
        $content = $this->body['content'] ?? '';
        
        if (!$filename) {
            return $this->error('Filename is required');
        }
        
        if ($content === '') {
            return $this->error('Content is required');
        }
        
        // Extract env from filename
        $env = $this->extractEnvFromFilename($filename);
        
        if (!$env) {
            return $this->error('Could not determine environment from filename');
        }
        
        // Save the template
        $filepath = $this->templateService->saveTemplate($env, $content);
        
        return $this->success([
            'message' => 'Template saved successfully',
            'filepath' => $filepath,
        ]);
    }
    
    /**
     * POST /api/templates/generate - Generate template from existing secrets
     */
    public function generate(): array
    {
        $data = $this->body;
        $env = $data['env'] ?? null;
        $vaults = $data['vaults'] ?? [];
        $filename = $data['filename'] ?? null;
        
        if (!$env) {
            return $this->error('Environment is required');
        }
        
        // Generate template content
        $content = $this->templateService->generateTemplate($env, $vaults);
        
        // If filename provided, save it
        if ($filename) {
            $filepath = $this->templateService->saveTemplate($env, $content);
            
            return $this->success([
                'content' => $content,
                'filename' => $filename,
                'filepath' => $filepath,
                'message' => 'Template generated and saved successfully',
            ]);
        }
        
        return $this->success([
            'content' => $content,
            'filename' => $this->templateService->getTemplateFilename($env),
            'message' => 'Template generated successfully',
        ]);
    }
    
    /**
     * POST /api/templates/validate - Validate template against env
     */
    public function validate(): array
    {
        $templateData = $this->loadTemplateFromRequest($this->body);
        
        if (isset($templateData['error'])) {
            return $this->error($templateData['error']);
        }
        
        $template = $templateData['template'];
        $env = $templateData['env'];
        
        // Extract placeholders and validate
        $placeholders = $template->placeholders();
        $validationResults = $placeholders->validate(null, $env);
        
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
        $allVaults = array_unique(array_filter($placeholders->map->vault->toArray()));
        foreach ($allVaults as $vaultName) {
            try {
                $vault = Keep::vault($vaultName, $env);
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
                // Skip vault if not accessible - this is expected for external vaults
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
    }
    
    /**
     * POST /api/templates/process - Process template with actual values
     */
    public function process(): array
    {
        $templateData = $this->loadTemplateFromRequest($this->body);
        
        if (isset($templateData['error'])) {
            return $this->error($templateData['error']);
        }
        
        $template = $templateData['template'];
        $env = $templateData['env'];
        $strategy = $this->body['strategy'] ?? 'skip';
        
        // Get missing secret strategy
        $missingStrategy = MissingSecretStrategy::from($strategy);
        
        // Discover vaults and load secrets
        $placeholders = $template->placeholders();
        $vaultNames = array_unique(array_filter($placeholders->map->vault->toArray()));
        $allSecrets = SecretCollection::loadFromVaults($vaultNames, $env);
        
        // Process the template
        $processedContent = $template->merge($allSecrets, $missingStrategy);
        
        // Also run validation to provide feedback
        $validationResults = $placeholders->validate(null, $env);
        
        return $this->success([
            'output' => $processedContent,
            'validation' => [
                'valid' => !$validationResults->contains(fn($r) => !$r->valid),
                'errorCount' => $validationResults->filter(fn($r) => !$r->valid)->count(),
            ],
            'placeholders' => $placeholders->toArray(),
        ]);
    }
    
    /**
     * GET /api/templates/placeholders - Get all available placeholders for autocomplete
     */
    public function placeholders(): array
    {
        $env = $this->query['env'] ?? null;
        
        if (!$env) {
            return $this->error('Environment is required');
        }
        
        $placeholders = [];
        
        // Get all configured vaults using Keep facade
        $configuredVaults = Keep::getConfiguredVaults();
        
        foreach ($configuredVaults as $vaultName => $config) {
            try {
                $vault = Keep::vault($vaultName, $env);
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
                // Skip vault if not accessible - this is expected for external vaults
                continue;
            }
        }
        
        return $this->success([
            'placeholders' => $placeholders,
            'count' => count($placeholders),
        ]);
    }
    
    /**
     * POST /api/templates/create - Create new template (check if exists first)
     */
    public function create(): array
    {
        $data = $this->body;
        $env = $data['env'] ?? null;
        $vaults = $data['vaults'] ?? [];
        
        if (!$env) {
            return $this->error('Environment is required');
        }
        
        // Check if template already exists
        if ($this->templateService->templateExists($env)) {
            $filename = $this->templateService->getTemplateFilename($env);
            return $this->error("Template already exists for environment '{$env}': {$filename}");
        }
        
        // Generate and save template
        $content = $this->templateService->generateTemplate($env, $vaults);
        $filepath = $this->templateService->saveTemplate($env, $content);
        $filename = $this->templateService->getTemplateFilename($env);
        
        return $this->success([
            'content' => $content,
            'filename' => $filename,
            'filepath' => $filepath,
            'message' => 'Template created successfully',
        ]);
    }
    
    /**
     * DELETE /api/templates/{filename} - Delete a template
     */
    public function delete(string $filename): array
    {
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
    }
    
    /**
     * Load template from request data with validation
     */
    private function loadTemplateFromRequest(array $data): array
    {
        $content = $data['content'] ?? '';
        $env = $data['env'] ?? null;
        $filename = $data['filename'] ?? null;
        
        if (!$content && !$filename) {
            return ['error' => 'Either content or filename is required'];
        }
        
        if (!$env) {
            return ['error' => 'Env is required'];
        }
        
        // Load template from file if filename provided
        if ($filename && !$content) {
            $template = $this->templateService->loadTemplate($filename);
        } else {
            $template = new Template($content);
        }
        
        return [
            'template' => $template,
            'env' => $env,
            'content' => $content,
            'filename' => $filename,
        ];
    }
    
    /**
     * Extract env from template filename
     */
    private function extractEnvFromFilename(string $filename): ?string
    {
        if (preg_match('/^([^.]+)\.env$/', $filename, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get friendly display name for env
     */
    private function getEnvDisplay(string $env, array $availableEnvs): string
    {
        // Direct match
        if (in_array($env, $availableEnvs)) {
            return $env;
        }
        
        // Common abbreviations
        $mappings = [
            'prod' => 'production',
            'dev' => 'development',
            'stg' => 'staging',
            'stage' => 'staging',
        ];
        
        if (isset($mappings[$env]) && in_array($mappings[$env], $availableEnvs)) {
            return $mappings[$env];
        }
        
        return $env;
    }
}