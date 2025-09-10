<?php

namespace STS\Keep\Commands;

use Exception;
use Illuminate\Support\Str;
use STS\Keep\Data\Collections\PermissionsCollection;
use STS\Keep\Data\Context;
use STS\Keep\Data\VaultEnvPermissions;
use STS\Keep\Facades\Keep;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class VerifyCommand extends BaseCommand
{
    public $signature = 'verify 
        {--context= : Comma-separated list of contexts to verify (e.g., "vault1:env1,vault2:env2")}
        {--vault= : Test only this vault} 
        {--env= : Test only this environment}';

    public $description = 'Verify vault access permissions for reading, writing, listing, and deleting secrets';

    public function process()
    {
        /** @var PermissionsCollection $collection */
        $collection = spin(function () {
            // If --context is provided, use specific contexts
            if ($this->option('context')) {
                $contexts = $this->parseContexts();
                $vaults = array_unique(array_map(fn($c) => $c->vault, $contexts));
                $envs = array_unique(array_map(fn($c) => $c->env, $contexts));
                
                return $this->testBulkPermissions($vaults, $envs);
            }

            // Otherwise use existing logic
            // Use getAllConfiguredVaults() to test all vaults, not just workspace filtered ones
            $vaults = $this->option('vault') 
                ? [$this->option('vault')] 
                : Keep::getAllConfiguredVaults()->keys()->toArray();
                
            $envs = $this->option('env') 
                ? [$this->option('env')] 
                : Keep::getAllEnvs();
            
            return $this->testBulkPermissions($vaults, $envs);
        }, 'Checking vault access permissions...');

        $this->displayResults($collection->toDisplayArray());
    }

    protected function testBulkPermissions(array $vaultNames, array $envs): PermissionsCollection
    {
        $collection = new PermissionsCollection();
        
        foreach ($vaultNames as $vaultName) {
            foreach ($envs as $env) {
                try {
                    $vault = Keep::vault($vaultName, $env);
                    $results = $vault->testPermissions();
                    $permission = VaultEnvPermissions::fromTestResults($vaultName, $env, $results);
                } catch (Exception $e) {
                    $permission = VaultEnvPermissions::fromError($vaultName, $env, $e->getMessage());
                }
                
                $collection->addPermission($permission);
            }
        }
        
        return $collection;
    }


    protected function displayResults(array $results): void
    {
        $this->info('Keep Vault Verification Results');

        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                $result['vault'],
                $result['env'],
                $this->formatResult($result['list']),
                $this->formatResult($result['write']),
                $this->formatResult($result['read']),
                $this->formatResult($result['history']),
                $this->formatCleanupResult($result['cleanup'], $result['write']),
            ];
        }

        table(
            ['Vault', 'Environment', 'List', 'Write', 'Read', 'History', 'Delete'],
            $rows
        );

        $this->displaySummary($results);
    }

    protected function formatResult(?bool $result): string
    {
        return match ($result) {
            true => '<fg=green>✓</>',
            false => '<fg=red>✗</>',
            default => '<fg=red>✗</>',
        };
    }

    protected function formatCleanupResult(bool $cleanup, bool $writeSucceeded): string
    {
        if (! $writeSucceeded) {
            return '<fg=gray>-</>';  // No cleanup needed if write failed
        }

        return $cleanup ? '<fg=green>✓</>' : '<fg=yellow>⚠</>';
    }

    protected function displaySummary(array $results): void
    {
        $totalCombinations = count($results);
        $fullAccess = collect($results)->filter(fn ($r) => $r['list'] && $r['write'] && $r['read'] && $r['history'])->count();
        $readHistoryOnly = collect($results)->filter(fn ($r) => $r['list'] && ! $r['write'] && $r['read'] && $r['history'])->count();
        $readOnly = collect($results)->filter(fn ($r) => $r['list'] && ! $r['write'] && $r['read'] && ! $r['history'])->count();
        $listOnly = collect($results)->filter(fn ($r) => $r['list'] && ! $r['write'] && ! $r['read'])->count();
        $noAccess = collect($results)->filter(fn ($r) => ! $r['list'] && ! $r['write'] && ! $r['read'])->count();
        $cleanupIssues = collect($results)->filter(fn ($r) => $r['write'] && ! $r['cleanup'])->count();

        $this->info('Summary:');
        $this->line("• Total vault/environment combinations tested: {$totalCombinations}");
        $this->line("• <fg=green>Full access</> (list + write + read + history): {$fullAccess}");
        $this->line("• <fg=blue>Read + History access</> (list + read + history): {$readHistoryOnly}");
        $this->line("• <fg=blue>Read-only access</> (list + read): {$readOnly}");
        $this->line("• <fg=yellow>List-only access</> (list only): {$listOnly}");
        $this->line("• <fg=red>No access</> (none): {$noAccess}");

        if ($cleanupIssues > 0) {
            $this->newLine();
            $this->line("<fg=yellow>⚠ Warning:</> {$cleanupIssues} test secret(s) could not be cleaned up.");
            $this->line("You may need to manually delete test keys starting with 'keep-verify-'");
        }

        $this->newLine();
        $this->info('Legend:');
        $this->line('<fg=green>✓</> = Success');
        $this->line('<fg=red>✗</> = Failed/No Permission');
        $this->line('<fg=yellow>⚠</> = Cleanup failed (test secret may remain)');
        $this->line('<fg=gray>-</> = Not applicable');
    }

    protected function parseContexts(): array
    {
        $contextInputs = array_map('trim', explode(',', $this->option('context')));
        $contexts = [];

        foreach ($contextInputs as $input) {
            $context = Context::fromInput($input);

            // Validate vault exists
            if (! Keep::getAllConfiguredVaults()->has($context->vault)) {
                $this->warn("Warning: Unknown vault '{$context->vault}' - skipping context '{$input}'");

                continue;
            }

            // Validate environment exists
            if (! in_array($context->env, Keep::getAllEnvs())) {
                $this->warn("Warning: Unknown environment '{$context->env}' - skipping context '{$input}'");

                continue;
            }

            $contexts[] = $context;
        }

        return $contexts;
    }
}
