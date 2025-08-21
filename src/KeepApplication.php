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

        $container->instance(KeepManager::class, new KeepManager(Settings::load(), VaultConfigCollection::load()));

        $this->addCommands([
            new Commands\InfoCommand,
            new Commands\ConfigureCommand,

            new Commands\VaultAddCommand,
            new Commands\VaultEditCommand,
            new Commands\VaultListCommand,

            new Commands\GetCommand,
            new Commands\SetCommand,
            new Commands\CopyCommand,
            new Commands\DeleteCommand,
            new Commands\HistoryCommand,

            new Commands\ListCommand,
            new Commands\ImportCommand,
            new Commands\ExportCommand,
            new Commands\MergeCommand,

            new Commands\DiffCommand,
            new Commands\VerifyCommand,
            new Commands\TemplateValidateCommand,
            new Commands\CacheCommand,
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
