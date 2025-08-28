<?php

namespace STS\Keep\Commands;

use Illuminate\Filesystem\Filesystem;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Services\Export\DirectExportService;
use STS\Keep\Services\Export\TemplateParseService;
use STS\Keep\Services\Export\TemplatePreserveService;
use STS\Keep\Services\Export\CacheExportService;
use STS\Keep\Services\OutputWriter;
use STS\Keep\Services\SecretLoader;
use STS\Keep\Services\VaultDiscovery;

class ExportCommand extends BaseCommand
{
    use GathersInput;
    
    public $signature = 'export 
        {--format=env : Output format (env or json)} 
        {--template= : Template file with {vault:key} placeholders to replace}
        {--all : Include all vault secrets, not just template placeholders}
        {--missing=fail : How to handle missing secrets: fail|remove|blank|skip}
        {--file= : Output file path (default: stdout)} 
        {--overwrite : Overwrite existing output file} 
        {--cache : Export to encrypted cache in .keep/cache/} 
        {--append : Append to existing output file} 
        {--stage= : Stage to export secrets from}
        {--vault= : Vault(s) to use (comma-separated, auto-detected from template if not specified)}'
        .self::ONLY_EXCLUDE_SIGNATURE;

    public $description = 'Export secrets from vaults with flexible output options';

    protected DirectExportService $directExport;
    protected TemplatePreserveService $templatePreserve;
    protected TemplateParseService $templateParse;
    protected CacheExportService $cacheExport;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
        
        // Initialize services
        $secretLoader = new SecretLoader();
        $vaultDiscovery = new VaultDiscovery();
        $outputWriter = new OutputWriter($filesystem);
        
        $this->directExport = new DirectExportService($secretLoader, $outputWriter);
        $this->templatePreserve = new TemplatePreserveService($secretLoader, $vaultDiscovery, $outputWriter);
        $this->templateParse = new TemplateParseService($secretLoader, $vaultDiscovery, $outputWriter);
        $this->cacheExport = new CacheExportService($secretLoader, $filesystem);
    }

    public function process()
    {
        // Gather all options including stage
        $options = array_merge($this->options(), [
            'stage' => $this->stage()
        ]);

        // Route to appropriate service based on options
        if ($this->option('cache')) {
            // Cache export mode
            return $this->cacheExport->handle($options, $this->output);
        }
        
        if (!$this->option('template')) {
            // Direct export mode
            return $this->directExport->handle($options, $this->output);
        }
        
        if ($this->option('format') === 'json') {
            // Template with JSON output - parse mode
            return $this->templateParse->handle($options, $this->output);
        }
        
        // Template with env output - preserve mode
        return $this->templatePreserve->handle($options, $this->output);
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

        3. <info>Cache Mode</info> - Export to encrypted cache for Laravel integration
           <comment>keep export --stage=production --cache</comment>

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

        # Export to encrypted cache for Laravel
        <comment>keep export --stage=production --cache</comment>

        # Filter exported secrets
        <comment>keep export --stage=production --only="DB_*,API_*" --except="*_SECRET"</comment>
        HELP;
    }
}