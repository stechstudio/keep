<?php

namespace STS\Keep\Shell;

use Psy\Configuration;

class KeepShellConfiguration extends Configuration
{
    private ShellContext $context;
    
    public function __construct(ShellContext $context)
    {
        parent::__construct();
        
        $this->context = $context;
        
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
    
}