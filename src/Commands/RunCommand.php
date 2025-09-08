<?php

namespace STS\Keep\Commands;

use Illuminate\Filesystem\Filesystem;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\ResolvesTemplates;
use STS\Keep\Data\Collections\SecretsCollection;
use STS\Keep\Data\Template;
use STS\Keep\Exceptions\ProcessExecutionException;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\EnvironmentBuilder;
use STS\Keep\Services\ProcessRunner;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class RunCommand extends BaseCommand
{
    use GathersInput, ResolvesTemplates;
    
    protected $signature = 'run 
        {cmd* : Command and arguments to run}
        {--vault= : Specific vault to use}
        {--stage= : Stage to use (required)}
        {--template= : Template file path, or auto-discover {stage}.env if no path given}
        {--only= : Only include secrets matching pattern}
        {--except= : Exclude secrets matching pattern}
        {--no-inherit : Do not inherit current environment variables}';
    
    protected $description = 'Execute a subprocess with secrets injected as environment variables';
    
    protected EnvironmentBuilder $environmentBuilder;
    protected ProcessRunner $processRunner;
    
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
        
        $this->environmentBuilder = new EnvironmentBuilder();
        $this->processRunner = new ProcessRunner();
    }
    
    protected function process(): int
    {
        // Get command and arguments
        $commandArgs = $this->argument('cmd');
        
        if (empty($commandArgs)) {
            error('No command specified');
            note('Usage: keep run [options] -- <command> [arguments]');
            return self::FAILURE;
        }
        
        // Validate command exists
        $command = $commandArgs[0];
        if (!$this->processRunner->commandExists($command) && !file_exists($command)) {
            error("Command not found: {$command}");
            return self::FAILURE;
        }
        
        // Gather stage and vault
        $stage = $this->stage();
        $vault = $this->vaultName();
        
        info("🚀 Preparing environment for: " . implode(' ', $commandArgs));
        
        try {
            // Build environment variables
            $environment = $this->buildEnvironment($stage, $vault);
            
            // Determine if we should inherit current environment
            $inheritCurrent = !$this->option('no-inherit');
            
            if (!$inheritCurrent) {
                note('Running with clean environment (secrets only)');
            }
            
            // Execute the command
            info("📦 Executing command with injected secrets...\n");
            
            $result = $this->processRunner->run(
                $commandArgs,
                $environment,
                getcwd()
            );
            
            // Clear sensitive data from memory
            $this->environmentBuilder->clearEnvironment($environment);
            
            // Return the exit code from the subprocess
            if (!$result->successful) {
                error("\n❌ Command failed with exit code: {$result->exitCode}");
            } else {
                info("\n✅ Command completed successfully");
            }
            
            return $result->exitCode;
            
        } catch (ProcessExecutionException $e) {
            error('Failed to execute command: ' . $e->getMessage());
            return self::FAILURE;
        } catch (\Exception $e) {
            error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
    
    /**
     * Build environment variables from secrets
     */
    protected function buildEnvironment(string $stage, string $vaultSlug): array
    {
        $templateOption = $this->option('template');
        $inheritCurrent = !$this->option('no-inherit');
        
        // Handle template-based injection
        // If --template is explicitly provided, use template mode
        // But only if no filters are specified (filters only work in direct mode)
        $hasFilters = $this->option('only') || $this->option('except');
        
        if (!$hasFilters && $this->hasOption('template') && $this->option('template') !== false) {
            // If template value is empty string, it means auto-discovery
            $templatePath = $templateOption === '' ? null : $templateOption;
            return $this->buildFromTemplate($templatePath, $stage, $vaultSlug, $inheritCurrent);
        }
        
        // Direct secret injection (all secrets from vault)
        return $this->buildFromVault($stage, $vaultSlug, $inheritCurrent);
    }
    
    /**
     * Build environment from template
     */
    protected function buildFromTemplate(?string $templatePath, string $stage, string $vaultSlug, bool $inheritCurrent): array
    {
        // Resolve template path if empty
        if ($templatePath === null) {
            $templatePath = $this->resolveTemplateForStage($stage);
            note("Using template: {$templatePath}");
        }
        
        // Load template
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template file not found: {$templatePath}");
        }
        
        $templateContent = file_get_contents($templatePath);
        $template = new Template($templateContent);
        
        // Get referenced vaults from template
        $referencedVaults = $template->allReferencedVaults();
        
        // Load vaults
        $vaults = [];
        if (empty($referencedVaults)) {
            // No vault references in template, use specified vault
            $vaults[$vaultSlug] = Keep::vault($vaultSlug, $stage);
        } else {
            // Load all referenced vaults
            foreach ($referencedVaults as $vaultRef) {
                $vaults[$vaultRef] = Keep::vault($vaultRef, $stage);
            }
        }
        
        // Build environment from template
        return $this->environmentBuilder->buildFromTemplate($template, $vaults, $inheritCurrent);
    }
    
    /**
     * Build environment from all vault secrets
     */
    protected function buildFromVault(string $stage, string $vaultSlug, bool $inheritCurrent): array
    {
        $vault = Keep::vault($vaultSlug, $stage);
        
        info("Loading secrets from {$vaultSlug}:{$stage}");
        
        // Fetch all secrets
        $secrets = $vault->list();
        
        // Apply filters if provided
        $only = $this->option('only');
        $except = $this->option('except');
        
        if ($only) {
            $secrets = $secrets->filter(function($secret) use ($only) {
                return fnmatch($only, $secret->key());
            });
        }
        
        if ($except) {
            $secrets = $secrets->filter(function($secret) use ($except) {
                return !fnmatch($except, $secret->key());
            });
        }
        
        note("Injecting {$secrets->count()} secret(s) as environment variables");
        
        // Build environment
        return $this->environmentBuilder->buildFromSecrets($secrets, $inheritCurrent);
    }
}