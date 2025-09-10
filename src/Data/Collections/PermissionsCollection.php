<?php

namespace STS\Keep\Data\Collections;

use Illuminate\Support\Collection;
use STS\Keep\Data\VaultEnvPermissions;

class PermissionsCollection extends Collection
{
    public function addPermission(VaultEnvPermissions $permission): self
    {
        $key = $permission->vault() . ':' . $permission->env();
        $this->put($key, $permission);
        
        return $this;
    }
    
    public function forVault(string $vault): self
    {
        return $this->filter(fn(VaultEnvPermissions $p) => $p->vault() === $vault);
    }
    
    public function forEnv(string $env): self
    {
        return $this->filter(fn(VaultEnvPermissions $p) => $p->env() === $env);
    }
    
    public function forVaultEnv(string $vault, string $env): ?VaultEnvPermissions
    {
        $key = $vault . ':' . $env;
        return $this->get($key);
    }
    
    public function groupByVault(): Collection
    {
        $grouped = new Collection();
        
        foreach ($this->items as $permission) {
            $vault = $permission->vault();
            if (!$grouped->has($vault)) {
                $grouped->put($vault, new Collection());
            }
            $grouped->get($vault)->put($permission->env(), $permission);
        }
        
        return $grouped;
    }
    
    public function groupByEnv(): Collection
    {
        $grouped = new Collection();
        
        foreach ($this->items as $permission) {
            $env = $permission->env();
            if (!$grouped->has($env)) {
                $grouped->put($env, new Collection());
            }
            $grouped->get($env)->put($permission->vault(), $permission);
        }
        
        return $grouped;
    }
    
    public function toVaultPermissionsArray(): array
    {
        $result = [];
        
        foreach ($this->groupByVault() as $vault => $envPermissions) {
            $result[$vault] = [];
            foreach ($envPermissions as $env => $permission) {
                $result[$vault][$env] = $permission->permissions();
            }
        }
        
        return $result;
    }
    
    public function toApiResponse(): array
    {
        $result = [];
        
        foreach ($this->groupByVault() as $vault => $envPermissions) {
            $result[$vault] = [];
            foreach ($envPermissions as $env => $permission) {
                $result[$vault][$env] = [
                    'success' => $permission->success(),
                    'permissions' => [
                        'List' => $permission->list(),
                        'Read' => $permission->read(),
                        'Write' => $permission->write(),
                        'Delete' => $permission->delete(),
                        'History' => $permission->history(),
                    ],
                    'error' => $permission->error(),
                ];
            }
        }
        
        return $result;
    }
    
    public function toDisplayArray(): array
    {
        return $this->map(fn(VaultEnvPermissions $p) => $p->toDisplayArray())->values()->toArray();
    }
    
    public function failures(): self
    {
        return $this->filter(fn(VaultEnvPermissions $p) => !$p->success());
    }
    
    public function successes(): self
    {
        return $this->filter(fn(VaultEnvPermissions $p) => $p->success());
    }
}