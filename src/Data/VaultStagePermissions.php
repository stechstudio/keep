<?php

namespace STS\Keep\Data;

class VaultStagePermissions
{
    protected array $permissions = [];

    public function __construct(
        protected string $vault,
        protected string $stage,
        protected bool $list = false,
        protected bool $read = false,
        protected bool $write = false,
        protected bool $delete = false,
        protected bool $history = false,
        protected bool $success = false,
        protected ?string $error = null
    ) {
        $this->buildPermissionsArray();
    }

    protected function buildPermissionsArray(): void
    {
        if ($this->list) $this->permissions[] = 'list';
        if ($this->read) $this->permissions[] = 'read';
        if ($this->write) $this->permissions[] = 'write';
        if ($this->delete) $this->permissions[] = 'delete';
        if ($this->history) $this->permissions[] = 'history';
    }

    public static function fromTestResults(string $vault, string $stage, array $results): static
    {
        return new static(
            vault: $vault,
            stage: $stage,
            list: $results['List'] ?? false,
            read: $results['Read'] ?? false,
            write: $results['Write'] ?? false,
            delete: $results['Delete'] ?? false,
            history: $results['History'] ?? false,
            success: ($results['List'] ?? false) || ($results['Read'] ?? false),
            error: null
        );
    }

    public static function fromError(string $vault, string $stage, string $error): static
    {
        return new static(
            vault: $vault,
            stage: $stage,
            list: false,
            read: false,
            write: false,
            delete: false,
            history: false,
            success: false,
            error: $error
        );
    }

    public function vault(): string
    {
        return $this->vault;
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function list(): bool
    {
        return $this->list;
    }

    public function read(): bool
    {
        return $this->read;
    }

    public function write(): bool
    {
        return $this->write;
    }

    public function delete(): bool
    {
        return $this->delete;
    }

    public function history(): bool
    {
        return $this->history;
    }

    public function toArray(): array
    {
        return [
            'vault' => $this->vault,
            'stage' => $this->stage,
            'list' => $this->list,
            'read' => $this->read,
            'write' => $this->write,
            'delete' => $this->delete,
            'history' => $this->history,
            'success' => $this->success,
            'error' => $this->error,
            'permissions' => $this->permissions,
        ];
    }
    
    public function toDisplayArray(): array
    {
        return [
            'vault' => $this->vault,
            'stage' => $this->stage,
            'list' => $this->list,
            'write' => $this->write,
            'read' => $this->read,
            'history' => $this->history,
            'cleanup' => $this->delete,
        ];
    }
}