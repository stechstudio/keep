<?php

namespace STS\Keep\Shell\Commands;

use Illuminate\Console\Application;
use STS\Keep\Shell\ShellContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;

class InteractiveExport
{
    public function __construct(
        private ShellContext $context,
        private Application $application
    ) {
    }
    
    /**
     * Execute the interactive export flow
     */
    public function execute(array $args = []): int
    {
        // If we're not in a TTY (e.g., piped input), fall back to non-interactive
        if (!posix_isatty(STDIN)) {
            return $this->runNonInteractive($args);
        }
        
        // Check if a format shortcut was provided
        $format = null;
        if (isset($args[0]) && in_array($args[0], ['json', 'env'])) {
            $format = $args[0];
        }
        
        // Build options starting with context
        $options = [
            '--stage' => $this->context->getStage(),
            '--vault' => $this->context->getVault(),
        ];
        
        // Check if a stage template exists
        $settings = \STS\Keep\Facades\Keep::getSettings();
        $templateDir = $settings['template_path'] ?? 'env';
        $stageTemplate = getcwd() . '/' . $templateDir . '/' . $this->context->getStage() . '.env';
        $hasStageTemplate = file_exists($stageTemplate);
        
        // Build export mode options
        $exportOptions = [
            'all' => 'All secrets from current context',
        ];
        
        // Add stage template option if it exists
        if ($hasStageTemplate) {
            $relativePath = $templateDir . '/' . $this->context->getStage() . '.env';
            $exportOptions['stage_template'] = "Use {$relativePath}";
        }
        
        // Always offer custom template option
        $exportOptions['template'] = 'Use custom template file';
        
        // Ask what they want to export
        $mode = select(
            label: 'What would you like to export?',
            options: $exportOptions,
            default: 'all'
        );
        
        // Handle template modes
        if ($mode === 'stage_template') {
            // Use the auto-discovered stage template
            $options['--template'] = $stageTemplate;
            $templateFile = $stageTemplate;
        } elseif ($mode === 'template') {
            // Ask for custom template path
            $templateFile = text(
                label: 'Template file path',
                placeholder: '.env.template',
                required: true
            );
            $options['--template'] = $templateFile;
        }
        
        // Handle template options (for both stage and custom templates)
        if ($mode === 'stage_template' || $mode === 'template') {
            
            if (confirm('Include all vault secrets (not just template placeholders)?', false)) {
                $options['--all'] = true;
            }
            
            $options['--missing'] = select(
                label: 'How to handle missing secrets?',
                options: [
                    'fail' => 'Stop with error (default)',
                    'remove' => 'Comment out the line',
                    'blank' => 'Leave value empty',
                    'skip' => 'Keep placeholder unchanged',
                ],
                default: 'fail'
            );
        }
        
        // Ask about format (unless already specified via shortcut)
        if ($format === null) {
            $format = select(
                label: 'Output format',
                options: [
                    'env' => '.env format (KEY=value)',
                    'json' => 'JSON format',
                ],
                default: 'env'
            );
        }
        $options['--format'] = $format;
        
        // Ask about output destination
        $destination = select(
            label: 'Where to export?',
            options: [
                'screen' => 'Display on screen',
                'file' => 'Save to file',
            ],
            default: 'screen'
        );
        
        if ($destination === 'file') {
            $defaultFile = $format === 'json' ? 'secrets.json' : '.env';
            $file = text(
                label: 'Output file path',
                placeholder: $defaultFile,
                default: $defaultFile,
                required: true
            );
            $options['--file'] = $file;
            
            // Check if file exists
            if (file_exists($file)) {
                $fileAction = select(
                    label: "File '{$file}' already exists. What would you like to do?",
                    options: [
                        'overwrite' => 'Overwrite the file',
                        'append' => 'Append to the file',
                        'cancel' => 'Cancel export',
                    ],
                    default: 'cancel'
                );
                
                if ($fileAction === 'cancel') {
                    $output = new ConsoleOutput();
                    $output->writeln('<info>Export cancelled.</info>');
                    return 0;
                }
                
                $options['--' . $fileAction] = true;
            }
        }
        
        // Now run the actual export command with all gathered options
        return $this->runExportCommand($options);
    }
    
    /**
     * Run the actual export command with the gathered options
     */
    private function runExportCommand(array $options): int
    {
        $input = array_merge(['command' => 'export'], $options);
        
        $command = $this->application->find('export');
        $arrayInput = new ArrayInput($input);
        $output = new ConsoleOutput();
        
        $exitCode = $command->run($arrayInput, $output);
        return $exitCode === null ? 0 : $exitCode;
    }
    
    /**
     * Run non-interactive export (for piped input/testing)
     */
    private function runNonInteractive(array $args): int
    {
        $options = [
            '--stage' => $this->context->getStage(),
            '--vault' => $this->context->getVault(),
        ];
        
        // Check if format was specified
        if (isset($args[0]) && in_array($args[0], ['json', 'env'])) {
            $options['--format'] = $args[0];
        }
        
        return $this->runExportCommand($options);
    }
}