<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Data\Template;
use STS\Keep\Data\Placeholder;
use STS\Keep\Data\PlaceholderValidationResult;
use STS\Keep\Facades\Keep;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;

class TemplateValidateCommand extends BaseCommand
{
    use GathersInput;
    
    protected $signature = 'template:validate {template? : Path to template file} 
        {--stage= : Stage to validate against} 
        {--vault= : Vault to validate against}';
    protected $description = 'Validate template files for correct placeholder syntax and available secrets';
    
    protected function process(): int
    {
        info('🔍  Template Validation');

        $templatePath = $this->argument('template') ?? text('Template file to validate', required: true);
        
        // Check if template file exists
        if (!file_exists($templatePath)) {
            error("Template file not found: {$templatePath}");
            return self::FAILURE;
        }
        
        // Load template content
        $templateContent = file_get_contents($templatePath);
        if ($templateContent === false) {
            error("Could not read template file: {$templatePath}");
            return self::FAILURE;
        }
        
        $template = new Template($templateContent);
        
        // Check if template is empty
        if ($template->isEmpty()) {
            warning("Template file is empty: {$templatePath}");
            return self::SUCCESS;
        }
        
        // Get validation context
        $vaultName = $this->vaultName();
        $stage = $this->stage();
        
        info("Validating template: {$templatePath}");
        info("Target environment: {$vaultName}:{$stage}");
        
        // Parse template and extract placeholders
        $placeholders = $template->placeholders();
        
        if ($placeholders->isEmpty()) {
            info("✅ Template contains no placeholders to validate");
            // Still check for unused secrets even with no placeholders
            $this->checkUnusedSecrets($placeholders, $vaultName, $stage);
            info("✅ Template validation successful");
            return self::SUCCESS;
        }
        
        info("Found " . $placeholders->count() . " placeholder(s) to validate");
        
        // Validate all placeholders
        $validationResults = $placeholders->validate($vaultName, $stage);
        $hasErrors = $validationResults->contains(fn (PlaceholderValidationResult $result) => !$result->valid);
        
        // Display validation results
        $this->displayValidationResults($validationResults);
        
        // Check for unused secrets (secrets that exist but aren't referenced)
        $this->checkUnusedSecrets($placeholders, $vaultName, $stage);
        
        if ($hasErrors) {
            error("❌ Template validation failed");
            return self::FAILURE;
        }
        
        info("✅ Template validation successful");
        return self::SUCCESS;
    }
    
    
    /**
     * Display validation results in a table
     */
    protected function displayValidationResults($results): void
    {
        $tableData = [];
        
        foreach ($results as $result) {
            $status = $result->valid ? '✅ Valid' : '❌ Invalid';
            $vault = $result->vault;
            $key = $result->placeholder->key;
            $line = $result->placeholder->line;
            $error = $result->error ?? '';
            
            $tableData[] = [
                'Line' => $line,
                'Vault' => $vault,
                'Key' => $key,
                'Status' => $status,
                'Error' => $error
            ];
        }
        
        table(
            headers: ['Line', 'Vault', 'Key', 'Status', 'Error'],
            rows: $tableData
        );
    }
    
    /**
     * Check for unused secrets (secrets that exist but aren't referenced in template)
     */
    protected function checkUnusedSecrets($placeholders, string $vaultName, string $stage): void
    {
        try {
            $vault = Keep::vault($vaultName, $stage);
            $allSecrets = $vault->list();
            
            // Extract referenced keys from placeholders for this vault
            $referencedKeys = $placeholders->getReferencedKeysForVault($vaultName, $vaultName);
            
            // Find unused secrets
            $unusedSecrets = [];
            foreach ($allSecrets as $secret) {
                if (!in_array($secret->key(), $referencedKeys)) {
                    $unusedSecrets[] = $secret->key();
                }
            }
            
            if (!empty($unusedSecrets)) {
                warning("Found " . count($unusedSecrets) . " unused secret(s) in {$vaultName}:{$stage}:");
                foreach ($unusedSecrets as $key) {
                    $this->line("  • {$key}");
                }
            } else {
                info("✅ All secrets in {$vaultName}:{$stage} are referenced in the template");
            }
            
        } catch (\Exception $e) {
            warning("Could not check for unused secrets: " . $e->getMessage());
        }
    }
}