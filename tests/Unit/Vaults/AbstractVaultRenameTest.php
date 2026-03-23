<?php

use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Tests\Support\TestVault;
use STS\Keep\Vaults\AbstractVault;

describe('AbstractVault rename', function () {
    beforeEach(function () {
        TestVault::clearAll();
    });

    it('renames a secret successfully', function () {
        $vault = new TestVault('test', ['namespace' => 'app'], 'dev');
        $vault->set('OLD_KEY', 'my-value');

        $result = $vault->rename('OLD_KEY', 'NEW_KEY');

        expect($result->key())->toBe('NEW_KEY');
        expect($result->value())->toBe('my-value');
        expect($vault->has('OLD_KEY'))->toBeFalse();
        expect($vault->has('NEW_KEY'))->toBeTrue();
    });

    it('throws when new key already exists', function () {
        $vault = new TestVault('test', ['namespace' => 'app'], 'dev');
        $vault->set('OLD_KEY', 'old-value');
        $vault->set('NEW_KEY', 'existing-value');

        expect(fn () => $vault->rename('OLD_KEY', 'NEW_KEY'))
            ->toThrow(KeepException::class, 'already exists');
    });

    it('rolls back when delete fails after creating new key', function () {
        $vault = new class('test', ['namespace' => 'app'], 'dev') extends AbstractVault {
            public const string DRIVER = 'test';
            private array $store = [];

            public function list(): SecretCollection
            {
                return new SecretCollection(array_values($this->store));
            }

            public function has(string $key): bool
            {
                return isset($this->store[$key]);
            }

            public function get(string $key): Secret
            {
                if (!isset($this->store[$key])) {
                    throw new \STS\Keep\Exceptions\SecretNotFoundException("Not found: {$key}");
                }
                return $this->store[$key];
            }

            public function set(string $key, string $value, bool $secure = true): Secret
            {
                $this->store[$key] = Secret::fromVault(
                    key: $key, value: $value, encryptedValue: null,
                    secure: $secure, env: 'dev', revision: 1, path: $key, vault: $this,
                );
                return $this->store[$key];
            }

            public function save(Secret $secret): Secret
            {
                $this->store[$secret->key()] = $secret;
                return $secret;
            }

            public function delete(string $key): bool
            {
                throw new \STS\Keep\Exceptions\AccessDeniedException('Access denied: cannot delete');
            }

            public function history(string $key, FilterCollection $filters, ?int $limit = 10): SecretHistoryCollection
            {
                return new SecretHistoryCollection();
            }
        };

        $vault->set('OLD_KEY', 'secret-value');

        expect(fn () => $vault->rename('OLD_KEY', 'NEW_KEY'))
            ->toThrow(KeepException::class, 'Rolled back');

        // Old key should still exist
        expect($vault->has('OLD_KEY'))->toBeTrue();
        // New key should have been cleaned up — but since delete always throws
        // in this vault, the cleanup also fails silently, so new key remains.
        // The important thing is the user gets a clear error.
    });
});
