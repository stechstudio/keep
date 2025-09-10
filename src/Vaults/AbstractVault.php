<?php

namespace STS\Keep\Vaults;

use Exception;
use Illuminate\Support\Str;
use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Secret;

abstract class AbstractVault
{
    public const string DRIVER = '';

    public const string NAME = '';

    public function __construct(protected string $name, protected array $config, protected string $stage) {}

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return Str::slug($this->name());
    }

    public function stage(): string
    {
        return $this->stage;
    }

    abstract public function list(): SecretCollection;

    abstract public function has(string $key): bool;

    abstract public function get(string $key): Secret;

    abstract public function set(string $key, string $value, bool $secure = true): Secret;

    abstract public function save(Secret $secret): Secret;

    abstract public function delete(string $key): bool;

    abstract public function history(string $key, FilterCollection $filters, ?int $limit = 10): SecretHistoryCollection;

    /**
     * Rename a secret by changing its key while preserving value and metadata.
     * 
     * Most vault providers (AWS SSM, Secrets Manager) don't support native rename operations,
     * so this default implementation uses copy + delete. Vault implementations can override
     * this method if they support atomic rename operations.
     *
     * @throws \STS\Keep\Exceptions\SecretNotFoundException if the old key doesn't exist
     * @throws \STS\Keep\Exceptions\KeepException if the new key already exists
     */
    public function rename(string $oldKey, string $newKey): Secret
    {
        // Get the existing secret
        $oldSecret = $this->get($oldKey);
        
        // Check if new key already exists
        if ($this->has($newKey)) {
            throw new \STS\Keep\Exceptions\KeepException(
                sprintf('Cannot rename: secret [%s] already exists', $newKey)
            );
        }
        
        // Create new secret with same value and security setting
        $newSecret = $this->set($newKey, $oldSecret->value(), $oldSecret->isSecure());
        
        // Delete the old secret
        $this->delete($oldKey);
        
        return $newSecret;
    }

    /**
     * Test permissions for this vault by attempting various operations.
     */
    public function testPermissions(): array
    {
        $permissions = [
            'Read' => false,
            'Write' => false,
            'List' => false,
            'Delete' => false,
            'History' => false,
        ];
        
        $testKey = 'keep-verify-'.bin2hex(random_bytes(4));
        $writeSucceeded = false;
        $existingSecrets = null;
        
        // Test List permission
        try {
            $existingSecrets = $this->list();
            $permissions['List'] = true;
        } catch (Exception) {
            // List failed
        }
        
        // Test Write permission
        try {
            $this->set($testKey, 'test_value');
            $permissions['Write'] = true;
            $writeSucceeded = true;
        } catch (Exception) {
            // Write failed
        }
        
        // Test Read permission
        if ($writeSucceeded) {
            try {
                $secret = $this->get($testKey);
                $permissions['Read'] = ($secret->value() === 'test_value');
            } catch (Exception) {
                // Read failed
            }
        } elseif ($permissions['List'] && $existingSecrets && $existingSecrets->count() > 0) {
            try {
                $firstSecret = $existingSecrets->first();
                $this->get($firstSecret->key());
                $permissions['Read'] = true;
            } catch (Exception) {
                // Read failed
            }
        }
        
        // Test History permission
        if ($writeSucceeded) {
            try {
                $this->history($testKey, new FilterCollection(), 10);
                $permissions['History'] = true;
            } catch (Exception) {
                // History not supported or failed
            }
        } elseif ($permissions['List'] && $existingSecrets && $existingSecrets->count() > 0) {
            try {
                $firstSecret = $existingSecrets->first();
                $this->history($firstSecret->key(), new FilterCollection(), 10);
                $permissions['History'] = true;
            } catch (Exception) {
                // History not supported or failed
            }
        }
        
        // Test Delete permission (cleanup test key if write succeeded)
        if ($writeSucceeded) {
            try {
                $this->delete($testKey);
                $permissions['Delete'] = true;
            } catch (Exception $e) {
                error_log("Warning: Failed to clean up verify test key '{$testKey}' in vault '{$this->name()}': ".$e->getMessage());
                $permissions['Delete'] = false;
            }
        }
        
        return $permissions;
    }
}
