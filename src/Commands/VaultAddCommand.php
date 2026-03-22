<?php

namespace STS\Keep\Commands;

use STS\Keep\Commands\Concerns\ConfiguresVaults;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class VaultAddCommand extends BaseCommand
{
    use ConfiguresVaults;

    protected $signature = 'vault:add';

    protected $description = 'Add a new vault configuration';

    protected function process()
    {
        info('🗄️  Add New Vault');
        note('Configure a new vault to store your secrets.');

        $result = $this->configureNewVault();

        if (! $result) {
            return self::FAILURE;
        }

        if (! $this->option('no-interaction') && confirm('Would you like to generate the IAM policy JSON for this vault?', false)) {
            $this->call('iam', ['--vault' => $result['slug']]);
        }

        return self::SUCCESS;
    }
}
