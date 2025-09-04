<?php

namespace STS\Keep\Commands;

use Illuminate\Filesystem\Filesystem;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Services\Export\DirectExportService;
use STS\Keep\Services\Export\TemplateParseService;
use STS\Keep\Services\Export\TemplatePreserveService;
// use STS\Keep\Services\Export\CacheExportService; // Future enhancement
use STS\Keep\Services\OutputWriter;
use STS\Keep\Services\SecretLoader;
use STS\Keep\Services\VaultDiscovery;

class ExportCommand extends BaseCommand
{
    use GathersInput;

    public $signature = 'export 
        {--format=env : Output format (env, json, or csv)} 
        {--template= : Template file path, or auto-discover {stage}.env if no path given}
        {--all : Include all vault secrets, not just template placeholders}
        {--missing=fail : How to handle missing secrets: fail|remove|blank|skip}
        {--file= : Output file path (default: stdout)} 
        {--overwrite : Overwrite existing output file} 
        {--append : Append to existing output file} 
        {--stage= : Stage to export secrets from}
        {--vault= : Vault(s) to use (comma-separated, auto-detected from template if not specified)}
        {--only= : Only include keys matching this pattern (e.g. DB_*)} 
        {--except= : Exclude keys matching this pattern (e.g. MAIL_*)}';

    // Future enhancement: Cache export
    // {--cache : Export to encrypted cache in .keep/cache/}

    public $description = 'Export secrets from vaults with flexible output options';

    protected DirectExportService $directExport;

    protected TemplatePreserveService $templatePreserve;

    protected TemplateParseService $templateParse;
    // protected CacheExportService $cacheExport; // Future enhancement

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);

        // Initialize services
        $secretLoader = new SecretLoader;
        $vaultDiscovery = new VaultDiscovery;
        $outputWriter = new OutputWriter($filesystem);

        $this->directExport = new DirectExportService($secretLoader, $outputWriter);
        $this->templatePreserve = new TemplatePreserveService($secretLoader, $vaultDiscovery, $outputWriter);
        $this->templateParse = new TemplateParseService($secretLoader, $vaultDiscovery, $outputWriter);
        // $this->cacheExport = new CacheExportService($secretLoader, $filesystem); // Future enhancement
    }

    public function process()
    {
        // Gather all options including stage
        $options = array_merge($this->options(), [
            'stage' => $this->stage(),
        ]);

        // Resolve template path if needed
        if ($this->hasOption('template')) {
            $templateOption = $this->option('template');
            
            if ($templateOption === null) {
                // Template flag provided without value, auto-discover based on stage
                $templatePath = $this->resolveTemplateForStage($options['stage']);
                $options['template'] = $templatePath;
            } elseif ($templateOption !== false) {
                // Value provided, use as-is
                $options['template'] = $templateOption;
            }
        }

        // Route to appropriate service based on options

        // Future enhancement: Cache export
        // if ($this->option('cache')) {
        //     // Cache export mode
        //     return $this->cacheExport->handle($options, $this->output);
        // }

        if (! isset($options['template'])) {
            // Direct export mode
            return $this->directExport->handle($options, $this->output);
        }

        if (in_array($this->option('format'), ['json', 'csv'])) {
            // Template with JSON/CSV output - parse mode
            return $this->templateParse->handle($options, $this->output);
        }

        // Template with env output - preserve mode
        return $this->templatePreserve->handle($options, $this->output);
    }

    /**
     * Resolve template file path based on stage name.
     */
    protected function resolveTemplateForStage(string $stage): string
    {
        $settings = \STS\Keep\Facades\Keep::getSettings();
        $templateDir = $settings['template_path'] ?? 'env';
        $templateFile = getcwd() . '/' . $templateDir . '/' . $stage . '.env';
        
        if (! file_exists($templateFile)) {
            throw new \InvalidArgumentException(
                "No template found for stage '{$stage}' at {$templateFile}.\n" .
                "Create one with: keep template:add {$stage}.env --stage={$stage}"
            );
        }
        
        return $templateFile;
    }
    
    /**
     * Override to avoid trying to get 'key' argument which doesn't exist
     */
    protected function key()
    {
        return null;
    }
    
    /**
     * Override to avoid prompting in non-interactive mode during error handling
     */
    protected function vaultName($prompt = 'Vault', $cacheName = 'vaultName'): string
    {
        // If we have the vault option, use it
        if ($this->hasOption('vault') && $this->option('vault')) {
            return $this->option('vault');
        }
        
        // In non-interactive mode, return empty string to avoid prompting
        if ($this->option('no-interaction')) {
            return '';
        }
        
        // Otherwise use parent implementation
        return parent::vaultName($prompt, $cacheName);
    }

    /**
     * Get the console command help text.
     */
    public function getHelp(): string
    {
        return <<<'HELP'
        The <info>export</info> command provides flexible options for exporting secrets from vaults.

        <comment>Operation Modes:</comment>

        1. <info>Direct Export</info> - Export all secrets from specified vaults
           <comment>keep export --stage=production --format=json</comment>

        2. <info>Template Mode</info> - Use a template file with placeholders
           <comment>keep export --stage=production --template=.env.template</comment>
           <comment>keep export --stage=production --template</comment>  # Auto-uses env/production.env

        <comment>Template Placeholders:</comment>
        Templates use the syntax <info>{vault:key}</info> which will be replaced with actual values:
        
        <comment>Example .env.template:</comment>
        # Database Configuration
        DB_HOST={aws-ssm:database/host}
        DB_PORT=3306  # Static value
        DB_PASSWORD={aws-secrets:db-password}

        <comment>Missing Secret Strategies (--missing):</comment>
        - <info>fail</info>    - Stop with error if secret not found (default)
        - <info>remove</info>  - Replace with comment: # Removed missing secret
        - <info>blank</info>   - Leave value empty: KEY=
        - <info>skip</info>    - Keep placeholder unchanged: KEY={vault:key}

        <comment>Examples:</comment>

        # Export all secrets from all vaults to stdout
        <comment>keep export --stage=production</comment>

        # Export specific vaults to JSON file
        <comment>keep export --stage=production --vault=ssm,secrets --format=json --file=secrets.json</comment>

        # Use template and include all additional secrets
        <comment>keep export --stage=production --template=.env.template --all --file=.env</comment>

        # Filter exported secrets
        <comment>keep export --stage=production --only="DB_*,API_*" --except="*_SECRET"</comment>
        HELP;
    }
}
