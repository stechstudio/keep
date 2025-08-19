<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Str;
use STS\Keep\Facades\Keep;

use function Laravel\Prompts\table;

class VerifyCommand extends AbstractCommand
{
    public $signature = 'keep:verify {--vault= : Test only this vault} {--stage= : Test only this stage}';

    public $description = 'Verify vault access permissions for reading, writing, listing, and deleting secrets';

    public function process(): int
    {
        $this->info('Keep Vault Verification');
        $this->newLine();

        $vaults = $this->option('vault') ? [$this->option('vault')] : Keep::available();
        $stages = $this->option('stage') ? [$this->option('stage')] : Keep::stages();

        $results = [];

        foreach ($vaults as $vaultName) {
            foreach ($stages as $stage) {
                $results[] = $this->verifyVaultStage($vaultName, $stage);
            }
        }

        $this->displayResults($results);

        return self::SUCCESS;
    }

    protected function verifyVaultStage(string $vaultName, string $stage): array
    {
        $vault = Keep::vault($vaultName)->forStage($stage);
        $testKey = 'keep-verify-'.Str::random(8);

        $result = [
            'vault' => $vaultName,
            'stage' => $stage,
            'list' => false,
            'write' => false,
            'read' => null, // null = unknown/untestable, false = tested and failed, true = success
            'history' => null, // null = unknown/untestable, false = tested and failed, true = success
            'cleanup' => false,
        ];

        $existingSecrets = null;

        // Test LIST operation
        try {
            $existingSecrets = $vault->list();
            $result['list'] = true;
        } catch (\Exception $e) {
            // List failed - this is fine, just mark as false
        }

        // Test WRITE operation
        try {
            $vault->set($testKey, 'test-verification-value', false);
            $result['write'] = true;
        } catch (\Exception $e) {
            // Write failed - this is fine, just mark as false
        }

        // Test READ operation
        if ($result['write']) {
            // If write succeeded, try to read our test secret
            try {
                $secret = $vault->get($testKey);
                if ($secret->value() === 'test-verification-value') {
                    $result['read'] = true;
                } else {
                    $result['read'] = false; // Could read but value was wrong
                }
            } catch (\Exception $e) {
                $result['read'] = false; // Tested and failed
            }
        } elseif ($result['list'] && $existingSecrets && $existingSecrets->count() > 0) {
            // If write failed but list succeeded and there are existing secrets,
            // try to read the first existing secret to test read permissions
            try {
                $firstSecret = $existingSecrets->first();
                $vault->get($firstSecret->key());
                $result['read'] = true;
            } catch (\Exception $e) {
                $result['read'] = false; // Tested and failed
            }
        }
        // If we reach here and read is still null, it means we couldn't test read
        // (write failed and no existing secrets to test against)

        // Test HISTORY operation
        if ($result['write']) {
            // If write succeeded, try to get history for our test secret
            try {
                $vault->history($testKey, new \STS\Keep\Data\FilterCollection([]), 1);
                $result['history'] = true;
            } catch (\Exception $e) {
                $result['history'] = false; // Tested and failed
            }
        } elseif ($result['list'] && $existingSecrets && $existingSecrets->count() > 0) {
            // If write failed but list succeeded and there are existing secrets,
            // try to get history for the first existing secret
            try {
                $firstSecret = $existingSecrets->first();
                $vault->history($firstSecret->key(), new \STS\Keep\Data\FilterCollection([]), 1);
                $result['history'] = true;
            } catch (\Exception $e) {
                $result['history'] = false; // Tested and failed
            }
        }
        // If we reach here and history is still null, it means we couldn't test history

        // CLEANUP - try to delete the test key (only if write succeeded)
        if ($result['write']) {
            try {
                $vault->delete($testKey);
                $result['cleanup'] = true;
            } catch (\Exception $e) {
                // Cleanup failed - this could be a problem but not critical
            }
        }

        return $result;
    }

    protected function displayResults(array $results): void
    {
        $this->info('Verification Results:');
        $this->newLine();

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

        $this->newLine();
        $this->displaySummary($results);
    }

    protected function formatResult(?bool $result): string
    {
        return match ($result) {
            true => '<fg=green>✓</>',
            false => '<fg=red>✗</>',
            null => '<fg=blue>?</>',
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
        $fullAccess = collect($results)->filter(fn ($r) => $r['list'] && $r['write'] && $r['read'] === true && $r['history'] === true)->count();
        $readHistoryOnly = collect($results)->filter(fn ($r) => $r['list'] && ! $r['write'] && $r['read'] === true && $r['history'] === true)->count();
        $readOnly = collect($results)->filter(fn ($r) => $r['list'] && ! $r['write'] && $r['read'] === true && $r['history'] !== true)->count();
        $listOnly = collect($results)->filter(fn ($r) => $r['list'] && ! $r['write'] && $r['read'] === false)->count();
        $noAccess = collect($results)->filter(fn ($r) => ! $r['list'] && ! $r['write'] && in_array($r['read'], [false, null]))->count();
        $unknownRead = collect($results)->filter(fn ($r) => $r['read'] === null)->count();
        $unknownHistory = collect($results)->filter(fn ($r) => $r['history'] === null)->count();
        $cleanupIssues = collect($results)->filter(fn ($r) => $r['write'] && ! $r['cleanup'])->count();

        $this->info('Summary:');
        $this->line("• Total vault/stage combinations tested: {$totalCombinations}");
        $this->line("• <fg=green>Full access</> (list + write + read + history): {$fullAccess}");
        $this->line("• <fg=blue>Read + History access</> (list + read + history): {$readHistoryOnly}");
        $this->line("• <fg=blue>Read-only access</> (list + read): {$readOnly}");
        $this->line("• <fg=yellow>List-only access</> (list only): {$listOnly}");
        $this->line("• <fg=red>No access</> (none): {$noAccess}");
        if ($unknownRead > 0) {
            $this->line("• <fg=blue>Unknown read access</> (unable to test): {$unknownRead}");
        }
        if ($unknownHistory > 0) {
            $this->line("• <fg=blue>Unknown history access</> (unable to test): {$unknownHistory}");
        }

        if ($cleanupIssues > 0) {
            $this->newLine();
            $this->line("<fg=yellow>⚠ Warning:</> {$cleanupIssues} test secret(s) could not be cleaned up.");
            $this->line("You may need to manually delete test keys starting with 'keep-verify-'");
        }

        $this->newLine();
        $this->info('Legend:');
        $this->line('<fg=green>✓</> = Success');
        $this->line('<fg=red>✗</> = Failed/No Permission');
        $this->line('<fg=blue>?</> = Unknown (unable to test)');
        $this->line('<fg=yellow>⚠</> = Cleanup failed (test secret may remain)');
        $this->line('<fg=gray>-</> = Not applicable');
    }
}
