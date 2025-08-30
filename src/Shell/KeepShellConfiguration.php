<?php

namespace STS\Keep\Shell;

use Psy\Configuration;
use Psy\TabCompletion\Matcher\AbstractMatcher;
use STS\Keep\Shell\Completers\CommandCompleter;
use STS\Keep\Shell\Completers\SecretCompleter;
use STS\Keep\Shell\Completers\StageCompleter;
use STS\Keep\Shell\Completers\VaultCompleter;

class KeepShellConfiguration extends Configuration
{
    private ShellContext $context;
    private CommandCompleter $commandCompleter;
    private SecretCompleter $secretCompleter;
    private StageCompleter $stageCompleter;
    private VaultCompleter $vaultCompleter;
    
    public function __construct(ShellContext $context)
    {
        parent::__construct();
        
        $this->context = $context;
        $this->commandCompleter = new CommandCompleter();
        $this->secretCompleter = new SecretCompleter($context);
        $this->stageCompleter = new StageCompleter($context);
        $this->vaultCompleter = new VaultCompleter($context);
        
        // Configure PsySH
        $this->setHistorySize(500);
        $this->setColorMode(Configuration::COLOR_MODE_AUTO);
        $this->setInteractiveMode(Configuration::INTERACTIVE_MODE_AUTO);
    }
    
    public function getPrompt(): string
    {
        return $this->context->getPrompt();
    }
    
    /**
     * Custom startup message for Keep Shell
     */
    public function getStartupMessage(): string
    {
        $message = "\n<info>Welcome to Keep Shell v1.0.0</info>\n";
        $message .= "Type 'help' for available commands or 'exit' to quit.\n";
        $message .= "Current context: <comment>{$this->context->getVault()}:{$this->context->getStage()}</comment>\n";
        $message .= "<comment>Tab completion is available for commands and secret names!</comment>\n";
        
        return $message;
    }
    
    /**
     * Custom tab completion matchers for PsySH
     */
    public function getTabCompletionMatchers(): array
    {
        return [
            new KeepCommandMatcher($this->commandCompleter, $this->secretCompleter, $this->stageCompleter, $this->vaultCompleter),
        ];
    }
}