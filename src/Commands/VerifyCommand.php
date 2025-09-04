<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Str;
use STS\Keep\Data\Context;
use STS\Keep\Facades\Keep;
use STS\Keep\Services\VaultPermissionTester;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class VerifyCommand extends BaseCommand
{
    public $signature = 'verify 
        {--context= : Comma-separated list of contexts to verify (e.g., "vault1:stage1,vault2:stage2")}
        {--vault= : Test only this vault} 
        {--stage= : Test only this stage}';

    public $description = 'Verify vault access permissions for reading, writing, listing, and deleting secrets';

    public function process()
    {
        $results = spin(function () {
            // If --context is provided, use specific contexts
            if ($this->option('context')) {
                $contexts = $this->parseContexts();
                $results = [];

                foreach ($contexts as $context) {
                    $results[] = $this->verifyVaultStage($context->vault, $context->stage);
                }

                return $results;
            }

            // Otherwise use existing logic
            $vaults = $this->option('vault') ? [$this->option('vault')] : Keep::getConfiguredVaults();
            $stages = $this->option('stage') ? [$this->option('stage')] : Keep::getStages();
            $results = [];

            foreach ($vaults as $vaultName => $config) {
                foreach ($stages as $stage) {
                    $results[] = $this->verifyVaultStage($vaultName, $stage);
                }
            }

            return $results;
        }, 'Checking vault access permissions...');

        $this->displayResults($results);
    }

    protected function verifyVaultStage(string $vaultName, string $stage): array
    {
        $vault = Keep::vault($vaultName, $stage);
        $tester = new VaultPermissionTester();
        $permissions = $tester->testPermissions($vault);
        
        // Transform to match our existing format (for compatibility with display methods)
        return [
            'vault' => $vaultName,
            'stage' => $stage,
            'list' => $permissions['List'],
            'write' => $permissions['Write'],
            'read' => $permissions['Read'],
            'history' => $permissions['History'],
            'cleanup' => $permissions['Delete'], // Delete permission indicates cleanup success
        ];
    }

    protected function displayResults(array $results): void
    {
        $this->info('Keep Vault Verification Results');

        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                $result['vault'],
                $result['stage'],
                $this->formatResult($result['list']),
                $this->formatResult($result['write']),
                $this->formatResult($result['read']),
                $this->formatResult($result['history']),
                $this->formatCleanupResult($result['cleanup'], $result['write']),
            ];
        }

        table(
            ['Vault', 'Stage', 'List', 'Write', 'Read', 'History', 'Delete'],
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
        $this->line("• Total vault/stage combinations tested: {$totalCombinations}");
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
            if (! in_array($context->vault, Keep::available())) {
                $this->warn("Warning: Unknown vault '{$context->vault}' - skipping context '{$input}'");

                continue;
            }

            // Validate stage exists
            if (! in_array($context->stage, Keep::stages())) {
                $this->warn("Warning: Unknown stage '{$context->stage}' - skipping context '{$input}'");

                continue;
            }

            $contexts[] = $context;
        }

        return $contexts;
    }
}
