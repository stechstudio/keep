<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithVaults;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Facades\Keep;
use STS\Keep\Data\Secret;

class GetCommand extends Command
{
    use GathersInput, InteractsWithVaults;

    public $signature = 'keep:get '
    .self::KEY_SIGNATURE
    .self::VAULT_SIGNATURE
    .self::ENV_SIGNATURE;

    public $description = 'Get the value of an environment secret in a specified vault';

    public function handle(): int
    {
        try {
            $secret = $this->vault()->get($this->key());
        } catch (KeepException $e) {
            $this->error(
                sprintf("Failed to get secret [%s] in vault [%s]",
                    $this->vault()->format($this->key()),
                    $this->vaultName()
                )
            );
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->line($secret->plainValue());

        return self::SUCCESS;
    }
}
