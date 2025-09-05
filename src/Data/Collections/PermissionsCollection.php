<?php

namespace STS\Keep\Data\Collections;

use Illuminate\Support\Collection;
use STS\Keep\Data\VaultStagePermissions;

class PermissionsCollection extends Collection
{
    public function add(VaultStagePermissions $permission): self
    {
        $key = $permission->vault() . ':' . $permission->stage();
        $this->put($key, $permission);
        
        return $this;
    }
    
    public function forVault(string $vault): self
    {
        return $this->filter(fn(VaultStagePermissions $p) => $p->vault() === $vault);
    }
    
    public function forStage(string $stage): self
    {
        return $this->filter(fn(VaultStagePermissions $p) => $p->stage() === $stage);
    }
    
    public function forVaultStage(string $vault, string $stage): ?VaultStagePermissions
    {
        $key = $vault . ':' . $stage;
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
            $grouped->get($vault)->put($permission->stage(), $permission);
        }
        
        return $grouped;
    }
    
    public function groupByStage(): Collection
    {
        $grouped = new Collection();
        
        foreach ($this->items as $permission) {
            $stage = $permission->stage();
            if (!$grouped->has($stage)) {
                $grouped->put($stage, new Collection());
            }
            $grouped->get($stage)->put($permission->vault(), $permission);
        }
        
        return $grouped;
    }
    
    public function toVaultPermissionsArray(): array
    {
        $result = [];
        
        foreach ($this->groupByVault() as $vault => $stagePermissions) {
            $result[$vault] = [];
            foreach ($stagePermissions as $stage => $permission) {
                $result[$vault][$stage] = $permission->permissions();
            }
        }
        
        return $result;
    }
    
    public function toApiResponse(): array
    {
        $result = [];
        
        foreach ($this->groupByVault() as $vault => $stagePermissions) {
            $result[$vault] = [];
            foreach ($stagePermissions as $stage => $permission) {
                $result[$vault][$stage] = [
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
        return $this->map(fn(VaultStagePermissions $p) => $p->toDisplayArray())->values()->toArray();
    }
    
    public function failures(): self
    {
        return $this->filter(fn(VaultStagePermissions $p) => !$p->success());
    }
    
    public function successes(): self
    {
        return $this->filter(fn(VaultStagePermissions $p) => $p->success());
    }
}