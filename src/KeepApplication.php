<?php

namespace STS\Keep;

use Illuminate\Console\Application;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Arr;
use STS\Keep\Data\Collections\VaultConfigCollection;
use STS\Keep\Data\Settings;
use STS\Keep\Enums\KeepInstall;
use Symfony\Component\Console\Input\InputDefinition;

class KeepApplication extends Application
{
    protected KeepManager $manager;

    public const string VERSION = '1.0.0-beta';

    public function __construct(protected KeepInstall $install)
    {
        $container = KeepContainer::getInstance();

        $events = new Dispatcher($container);
        $container->instance(Dispatcher::class, $events);

        parent::__construct($container, $events, self::VERSION);
        $this->setName('Keep');

        $container->instance(
            KeepManager::class,
            new KeepManager(Settings::load(), VaultConfigCollection::load())
        );

        $this->resolveCommands([
            Commands\InfoCommand::class,
            Commands\InitCommand::class,

            Commands\VaultAddCommand::class,
            Commands\VaultEditCommand::class,
            Commands\VaultListCommand::class,

            Commands\EnvAddCommand::class,
            
            Commands\WorkspaceConfigureCommand::class,

            Commands\GetCommand::class,
            Commands\SetCommand::class,
            Commands\CopyCommand::class,
            Commands\DeleteCommand::class,
            Commands\RenameCommand::class,
            Commands\HistoryCommand::class,
            Commands\SearchCommand::class,

            Commands\ShowCommand::class,
            Commands\ImportCommand::class,
            Commands\ExportCommand::class,

            Commands\DiffCommand::class,
            Commands\VerifyCommand::class,
            Commands\TemplateValidateCommand::class,
            Commands\TemplateAddCommand::class,
            
            Commands\ShellCommand::class,
            Commands\ServerCommand::class,
            Commands\RunCommand::class,
        ]);
        
        // Make shell the default command when running 'keep' without arguments
        // Second parameter false = still allow other commands to be run
        $this->setDefaultCommand('shell', false);
    }

    #[\Override]
    protected function getDefaultInputDefinition(): InputDefinition
    {
        return tap(parent::getDefaultInputDefinition(), function ($definitions) {
            $definitions->setOptions(Arr::except($definitions->getOptions(), ['env']));
        });
    }
}
