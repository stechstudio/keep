<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use STS\Keep\Exceptions\KeepException;

abstract class AbstractCommand extends Command
{
    public function handle(): int
    {
        try {
            $result = $this->process();

            return is_int($result) ? $result : self::SUCCESS;
        } catch(KeepException $e) {
            $e->renderConsole($this);
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("An error occurred");
            $this->line($e->getMessage());
            return self::FAILURE;
        }
    }

    abstract public function process(): int;
}