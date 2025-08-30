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

    public function __construct(protected KeepInstall $install)
    {
        $container = KeepContainer::getInstance();

        $events = new Dispatcher($container);
        $container->instance(Dispatcher::class, $events);

        parent::__construct($container, $events, '1.0.0-alpha');
        $this->setName('Keep');

        $container->instance(
            KeepManager::class,
            new KeepManager(Settings::load(), VaultConfigCollection::load())
        );

        $this->resolveCommands([
            Commands\InfoCommand::class,
            Commands\ConfigureCommand::class,

            Commands\VaultAddCommand::class,
            Commands\VaultEditCommand::class,
            Commands\VaultListCommand::class,

            Commands\StageAddCommand::class,

            Commands\GetCommand::class,
            Commands\SetCommand::class,
            Commands\CopyCommand::class,
            Commands\DeleteCommand::class,
            Commands\HistoryCommand::class,

            Commands\ShowCommand::class,
            Commands\ImportCommand::class,
            Commands\ExportCommand::class,

            Commands\DiffCommand::class,
            Commands\VerifyCommand::class,
            Commands\TemplateValidateCommand::class,
            
            Commands\ShellCommand::class,
        ]);
    }

    #[\Override]
    protected function getDefaultInputDefinition(): InputDefinition
    {
        return tap(parent::getDefaultInputDefinition(), function ($definitions) {
            $definitions->setOptions(Arr::except($definitions->getOptions(), ['env']));
        });
    }
}
