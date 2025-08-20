<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;

class DemoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('demo')
             ->setDescription('Demo Laravel Prompts integration');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        info('Welcome to Keep with Laravel Prompts!');
        
        $name = text(
            label: 'What is your name?',
            placeholder: 'Enter your name',
            default: 'Developer'
        );
        
        $vault = select(
            label: 'Which vault driver would you like to use?',
            options: [
                'aws_ssm' => 'AWS Systems Manager Parameter Store',
                'aws_secrets_manager' => 'AWS Secrets Manager',
                'hashicorp' => 'HashiCorp Vault',
                'local' => 'Local (for development)'
            ],
            default: 'aws_ssm'
        );
        
        $stages = multiselect(
            label: 'Which stages do you need?',
            options: [
                'development' => 'Development',
                'testing' => 'Testing',
                'staging' => 'Staging',
                'production' => 'Production'
            ],
            default: ['development', 'production']
        );
        
        $proceed = confirm(
            label: 'Create configuration?',
            default: true
        );
        
        if ($proceed) {
            info("Great! Here's what we'll set up for {$name}:");
            info("Vault: {$vault}");
            info("Stages: " . implode(', ', $stages));
            warning('This is just a demo - no actual configuration created.');
        } else {
            error('Configuration cancelled.');
        }
        
        return Command::SUCCESS;
    }
}