<?php

namespace STS\Keep\Commands;

use Illuminate\Filesystem\Filesystem;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\ResolvesTemplates;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Template;
use STS\Keep\Exceptions\ProcessExecutionException;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\ProcessRunner;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Console\OutputStyle;

class RunCommand extends BaseCommand
{
    use GathersInput, ResolvesTemplates;
    
    protected $signature = 'run 
        {cmd* : Command and arguments to run}
        {--vault= : Specific vault to use}
        {--env= : Environment to use (required)}
        {--template= : Template file path, or auto-discover {env}.env if no path given}
        {--only= : Only include secrets matching pattern}
        {--except= : Exclude secrets matching pattern}
        {--no-inherit : Do not inherit current environment variables}';
    
    protected $description = 'Execute a subprocess with secrets injected as environment variables';
    
    protected ProcessRunner $processRunner;
    
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
        
        $this->processRunner = new ProcessRunner();
    }
    
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (\Symfony\Component\Console\Exception\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Not enough arguments (missing: "cmd")')) {
                $this->output = new OutputStyle($input, $output);
                error('No command specified to run');
                note('Usage: keep run [options] -- <command> [arguments]');
                note('Example: keep run --vault=ssm --env=production -- npm start');
                note('Example: keep run --vault=ssm --env=production -- php artisan serve');
                return self::FAILURE;
            }
            throw $e;
        }
    }
    
    protected function process(): int
    {
        $commandArgs = $this->argument('cmd');
        $command = $commandArgs[0];
        if (!$this->processRunner->commandExists($command) && !file_exists($command)) {
            error("Command not found: {$command}");
            return self::FAILURE;
        }
        
        $env = $this->env();
        $vault = $this->vaultName();
        
        info("🚀 Preparing environment for: " . implode(' ', $commandArgs));
        
        try {
            $environment = $this->buildEnvironment($env, $vault);
            
            $inheritCurrent = !$this->option('no-inherit');
            
            if (!$inheritCurrent) {
                note('Running with clean environment (secrets only)');
            }
            
            info("📦 Executing command with injected secrets...\n");
            
            $result = $this->processRunner->run(
                $commandArgs,
                $environment,
                getcwd()
            );
            
            foreach ($environment as $key => $value) {
                unset($environment[$key]);
            }
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
    
    protected function buildEnvironment(string $env, string $vaultSlug): array
    {
        $templateOption = $this->option('template');
        $inheritCurrent = !$this->option('no-inherit');
        
        $hasFilters = $this->option('only') || $this->option('except');
        
        if (!$hasFilters && $templateOption !== null) {
            $templatePath = $templateOption === '' ? null : $templateOption;
            return $this->buildFromTemplate($templatePath, $env, $vaultSlug, $inheritCurrent);
        }
        
        return $this->buildFromVault($env, $vaultSlug, $inheritCurrent);
    }
    
    protected function buildFromTemplate(?string $templatePath, string $env, string $vaultSlug, bool $inheritCurrent): array
    {
        if ($templatePath === null) {
            $templatePath = $this->resolveTemplateForEnv($env);
            note("Using template: {$templatePath}");
        }
        
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template file not found: {$templatePath}");
        }
        
        $templateContent = file_get_contents($templatePath);
        $template = new Template($templateContent);
        
        $referencedVaults = $template->allReferencedVaults();
        
        $vaults = [];
        if (empty($referencedVaults)) {
            $vaults[$vaultSlug] = Keep::vault($vaultSlug, $env);
        } else {
            foreach ($referencedVaults as $vaultRef) {
                $vaults[$vaultRef] = Keep::vault($vaultRef, $env);
            }
        }
        
        return $template->toEnvironment($vaults, $inheritCurrent);
    }
    
    protected function buildFromVault(string $env, string $vaultSlug, bool $inheritCurrent): array
    {
        $vault = Keep::vault($vaultSlug, $env);
        
        info("Loading secrets from {$vaultSlug}:{$env}");
        
        $secrets = $vault->list();
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
        
        return $secrets->toEnvironment($inheritCurrent);
    }
}