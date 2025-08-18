<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithFilesystem;
use STS\Keep\Commands\Concerns\InteractsWithVaults;
use STS\Keep\Exceptions\KeepException;

abstract class AbstractCommand extends Command
{
    use GathersInput, InteractsWithFilesystem, InteractsWithVaults;

    public function handle(): int
    {
        try {
            $result = $this->process();

            return is_int($result) ? $result : self::SUCCESS;
        } catch (KeepException $e) {
            $e->renderConsole($this->line(...));

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An error occurred');
            $this->line($e->getMessage());

            return self::FAILURE;
        }
    }

    abstract public function process(): int;
}
