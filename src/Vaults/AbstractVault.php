<?php

namespace STS\Keep\Vaults;

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
}
