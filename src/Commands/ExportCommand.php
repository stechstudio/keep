<?php

namespace STS\Keep\Commands;

use Illuminate\Filesystem\Filesystem;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Services\Export\DirectExportService;
use STS\Keep\Services\Export\TemplateParseService;
use STS\Keep\Services\Export\TemplatePreserveService;
use STS\Keep\Services\OutputWriter;
use STS\Keep\Services\SecretLoader;
use STS\Keep\Services\VaultDiscovery;

class ExportCommand extends BaseCommand
{
    use GathersInput;
    
    public $signature = 'export 
        {--format=env : json|env} 
        {--template= : Optional template file with placeholders}
        {--all : With template: also append non-placeholder secrets}
        {--missing=fail : Strategy for missing secrets in template: fail|remove|blank|skip}
        {--output= : File where to save the output (defaults to stdout)} 
        {--overwrite : Overwrite the output file if it exists} 
        {--append : Append to the output file if it exists} 
        {--stage= : The stage to export secrets for}
        {--vault= : The vault(s) to export from (comma-separated, defaults to all configured vaults)}'
        .self::ONLY_EXCLUDE_SIGNATURE;

    public $description = 'Export stage secrets from vault(s) with optional template processing';

    protected DirectExportService $directExport;
    protected TemplatePreserveService $templatePreserve;
    protected TemplateParseService $templateParse;

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
    }

    public function process()
    {
        // Gather all options including stage
        $options = array_merge($this->options(), [
            'stage' => $this->stage()
        ]);

        // Route to appropriate service based on options
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
}