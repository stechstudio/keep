<?php

namespace STS\Keep\Services;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\Settings;
use STS\Keep\Data\Template;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Facades\Keep;

class TemplateService
{
    protected string $templatePath;
    
    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $this->resolveTemplatePath($templatePath);
    }
    
    /**
     * Resolve template path from settings or provided override
     */
    protected function resolveTemplatePath(?string $templatePath = null): string
    {
        // Load template path from settings or use default
        $settings = Settings::load();
        $configuredPath = $templatePath 
            ?? ($settings ? $settings->get('template_path', 'env') : 'env');
        
        // Convert to absolute path, handling both "env" and "./env" formats
        if (!str_starts_with($configuredPath, '/')) {
            // Add "./" prefix if not present for relative paths
            if (!str_starts_with($configuredPath, './')) {
                $configuredPath = './' . $configuredPath;
            }
            return getcwd() . '/' . $configuredPath;
        }
        
        return $configuredPath;
    }
    
    /**
     * Get the configured template directory path
     */
    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }
    
    /**
     * Check if a template exists for the given stage
     */
    public function templateExists(string $stage): bool
    {
        $filename = $this->getTemplateFilename($stage);
        return file_exists($this->templatePath . '/' . $filename);
    }
    
    /**
     * Get the template filename for a stage
     */
    public function getTemplateFilename(string $stage): string
    {
        return $stage . '.env';
    }
    
    /**
     * Generate a template from existing secrets for a stage
     * 
     * @param string $stage The stage to generate template for
     * @param array $vaultFilter Optional array of vault names to include
     * @return string The generated template content
     */
    public function generateTemplate(string $stage, array $vaultFilter = []): string
    {
        $templates = [];
        $vaults = $this->getVaultsForStage($stage, $vaultFilter);
        
        foreach ($vaults as $vaultName) {
            try {
                $vault = Keep::vault($vaultName, $stage);
                $secrets = $vault->list();
                
                if ($secrets->isEmpty()) {
                    continue;
                }
                
                $templates[] = $this->generateVaultSection($vaultName, $secrets);
            } catch (\Exception $e) {
                // Skip vault if not accessible - this is expected for external vaults
                continue;
            }
        }
        
        if (empty($templates)) {
            throw new KeepException("No secrets found for stage '{$stage}'");
        }
        
        // Add header and non-secret examples
        $header = $this->generateTemplateHeader($stage);
        $nonSecretExamples = $this->generateNonSecretSection();
        
        return $header . "\n" . implode("\n", $templates) . "\n" . $nonSecretExamples;
    }
    
    /**
     * Save a template to the filesystem
     */
    public function saveTemplate(string $stage, string $content): string
    {
        // Ensure template directory exists
        if (!is_dir($this->templatePath)) {
            if (!mkdir($this->templatePath, 0755, true)) {
                throw new KeepException("Failed to create template directory: {$this->templatePath}");
            }
        }
        
        $filename = $this->getTemplateFilename($stage);
        $filepath = $this->templatePath . '/' . $filename;
        
        if (file_put_contents($filepath, $content) === false) {
            throw new KeepException("Failed to save template to: {$filepath}");
        }
        
        return $filepath;
    }
    
    /**
     * Convert a secret key to ENV format
     * Examples:
     * - db-password → DB_PASSWORD
     * - apiKey → APIKEY
     * - API_KEY → API_KEY (already formatted)
     */
    public function normalizeKeyToEnv(string $key): string
    {
        // Replace hyphens and other separators with underscores
        $key = str_replace(['-', '.', ' '], '_', $key);
        
        // Convert to uppercase
        return strtoupper($key);
    }
    
    /**
     * Generate a vault section for the template
     */
    protected function generateVaultSection(string $vaultName, SecretCollection $secrets): string
    {
        $lines = [];
        $lines[] = "# ===== Vault: {$vaultName} =====";
        
        foreach ($secrets as $secret) {
            $envKey = $this->normalizeKeyToEnv($secret->key());
            $placeholder = "{{$vaultName}:{$secret->key()}}";
            $lines[] = "{$envKey}={$placeholder}";
        }
        
        $lines[] = ""; // Empty line after section
        return implode("\n", $lines);
    }
    
    /**
     * Generate template header with metadata
     */
    protected function generateTemplateHeader(string $stage): string
    {
        $date = date('Y-m-d H:i:s');
        $lines = [
            "# ===================================================",
            "# Keep Template - Stage: {$stage}",
            "# Generated: {$date}",
            "# ===================================================",
            "",
        ];
        
        return implode("\n", $lines);
    }
    
    /**
     * Generate non-secret variables section with common examples
     */
    protected function generateNonSecretSection(): string
    {
        $lines = [
            "# ===== Application Settings (non-secret) =====",
            "# Uncomment and modify as needed:",
            "#",
            "# APP_NAME=MyApp",
            "# APP_ENV=production",
            "# APP_DEBUG=false",
            "# APP_URL=https://example.com",
            "# LOG_LEVEL=info",
            "# TIMEZONE=UTC",
            "",
        ];
        
        return implode("\n", $lines);
    }
    
    /**
     * Get available vaults for a stage, optionally filtered
     */
    protected function getVaultsForStage(string $stage, array $vaultFilter = []): array
    {
        // Get all configured vaults using Keep facade
        $configuredVaults = Keep::getConfiguredVaults();
        $vaults = [];
        
        foreach ($configuredVaults as $vaultName => $config) {
            // Apply filter if provided
            if (!empty($vaultFilter) && !in_array($vaultName, $vaultFilter)) {
                continue;
            }
            
            $vaults[] = $vaultName;
        }
        
        return $vaults;
    }
    
    /**
     * Scan for existing templates in the template directory
     */
    public function scanTemplates(): array
    {
        if (!is_dir($this->templatePath)) {
            return [];
        }
        
        $templates = [];
        $files = glob($this->templatePath . '/*.env');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $stage = $this->extractStageFromFilename($filename);
            
            $templates[] = [
                'filename' => $filename,
                'path' => $file,
                'stage' => $stage,
                'size' => filesize($file),
                'lastModified' => filemtime($file),
            ];
        }
        
        return $templates;
    }
    
    /**
     * Extract stage from template filename
     * Examples: production.env → production, prod.env → prod
     */
    protected function extractStageFromFilename(string $filename): ?string
    {
        if (preg_match('/^([^.]+)\.env$/', $filename, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Load a template file
     */
    public function loadTemplate(string $filename): Template
    {
        $filepath = $this->templatePath . '/' . $filename;
        
        if (!file_exists($filepath)) {
            throw new KeepException("Template file not found: {$filename}");
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new KeepException("Failed to read template file: {$filename}");
        }
        
        return new Template($content);
    }
}