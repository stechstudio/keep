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
    
    protected function resolveTemplatePath(?string $templatePath = null): string
    {
        $settings = Settings::load();
        $configuredPath = $templatePath 
            ?? ($settings ? $settings->get('template_path', 'env') : 'env');
        
        if (!str_starts_with($configuredPath, '/')) {
            if (!str_starts_with($configuredPath, './')) {
                $configuredPath = './' . $configuredPath;
            }
            return getcwd() . '/' . $configuredPath;
        }
        
        return $configuredPath;
    }
    
    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }
    
    public function templateExists(string $stage): bool
    {
        $filename = $this->getTemplateFilename($stage);
        return file_exists($this->templatePath . '/' . $filename);
    }
    
    public function getTemplateFilename(string $stage): string
    {
        return $stage . '.env';
    }
    
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
                continue;
            }
        }
        
        if (empty($templates)) {
            throw new KeepException("No secrets found for stage '{$stage}'");
        }
        
        $header = $this->generateTemplateHeader($stage);
        $nonSecretExamples = $this->generateNonSecretSection();
        
        return $header . "\n" . implode("\n", $templates) . "\n" . $nonSecretExamples;
    }
    
    public function saveTemplate(string $stage, string $content): string
    {
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
    
    public function normalizeKeyToEnv(string $key): string
    {
        $key = str_replace(['-', '.', ' '], '_', $key);
        return strtoupper($key);
    }
    
    protected function generateVaultSection(string $vaultName, SecretCollection $secrets): string
    {
        $lines = [];
        $lines[] = "# ===== Vault: {$vaultName} =====";
        
        foreach ($secrets as $secret) {
            $envKey = $this->normalizeKeyToEnv($secret->key());
            $placeholder = "{{$vaultName}:{$secret->key()}}";
            $lines[] = "{$envKey}={$placeholder}";
        }
        
        $lines[] = "";
        return implode("\n", $lines);
    }
    
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
    
    protected function getVaultsForStage(string $stage, array $vaultFilter = []): array
    {
        $configuredVaults = Keep::getConfiguredVaults();
        $vaults = [];
        
        foreach ($configuredVaults as $vaultName => $config) {
            if (!empty($vaultFilter) && !in_array($vaultName, $vaultFilter)) {
                continue;
            }
            
            $vaults[] = $vaultName;
        }
        
        return $vaults;
    }
    
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
    
    protected function extractStageFromFilename(string $filename): ?string
    {
        if (preg_match('/^([^.]+)\.env$/', $filename, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
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