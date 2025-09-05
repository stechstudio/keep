<?php

namespace STS\Keep\Commands;

use STS\Keep\Services\TemplateService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class TemplateAddCommand extends BaseCommand
{

    protected $signature = 'template:add 
        {--stage= : Stage to generate template for}
        {--path= : Custom template directory path}';

    protected $description = 'Generate a new template file from existing secrets for a specific stage';

    protected function process(): int
    {
        info('📝 Generate Template from Secrets');

        // Get stage
        $stage = $this->option('stage') ?? $this->stage();
        
        // Initialize template service
        $templateService = new TemplateService($this->option('path'));
        $templatePath = $templateService->getTemplatePath();
        
        info("Template directory: {$templatePath}");
        info("Stage: {$stage}");
        
        // Check if template already exists
        if ($templateService->templateExists($stage)) {
            $filename = $templateService->getTemplateFilename($stage);
            error("Template already exists for stage '{$stage}': {$filename}");
            note('Use template:validate to work with existing templates');
            
            return self::FAILURE;
        }
        
        // Generate template
        $content = spin(
            fn() => $templateService->generateTemplate($stage),
            "Generating template from secrets for stage '{$stage}'..."
        );
        
        // Show preview
        $this->showTemplatePreview($content);
        
        // Confirm save
        if (!confirm("Save this template as {$stage}.env?")) {
            note('Template generation cancelled');
            return self::SUCCESS;
        }
        
        // Save template
        $filepath = spin(
            fn() => $templateService->saveTemplate($stage, $content),
            "Saving template..."
        );
        
        info("✅ Template created successfully: {$filepath}");
        
        // Show next steps
        note("Next steps:");
        $this->line("  • Review and customize the generated template");
        $this->line("  • Add any non-secret configuration values");
        $this->line("  • Test with: keep template:validate {$stage}.env --stage={$stage}");
        $this->line("  • Export with: keep export --template={$stage}.env --stage={$stage}");
        
        return self::SUCCESS;
    }
    
    /**
     * Show a preview of the generated template
     */
    protected function showTemplatePreview(string $content): void
    {
        $lines = explode("\n", $content);
        $preview = array_slice($lines, 0, 20);
        
        note("Template preview:");
        foreach ($preview as $line) {
            // Highlight different line types
            if (str_starts_with($line, '# =====')) {
                $this->line("  <comment>{$line}</comment>");
            } elseif (str_starts_with($line, '#')) {
                $this->line("  <fg=gray>{$line}</>");
            } elseif (str_contains($line, '={')) {
                // Secret placeholder line
                $parts = explode('=', $line, 2);
                $this->line("  <info>{$parts[0]}</info>=<fg=cyan>{$parts[1]}</>");
            } else {
                $this->line("  {$line}");
            }
        }
        
        if (count($lines) > 20) {
            $this->line("  <fg=gray>... (" . (count($lines) - 20) . " more lines)</>");
        }
    }
}