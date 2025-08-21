<?php

use STS\Keep\Data\Placeholder;
use STS\Keep\Data\Collections\PlaceholderCollection;

describe('PlaceholderCollection', function () {
    
    beforeEach(function () {
        $this->placeholder1 = new Placeholder(1, 'API_KEY', null, 'API_KEY', 'API_KEY={API_KEY}', '{API_KEY}');
        $this->placeholder2 = new Placeholder(2, 'DB_HOST', 'ssm', 'DB_HOST', 'DB_HOST={ssm:DB_HOST}', '{ssm:DB_HOST}');
        $this->placeholder3 = new Placeholder(3, 'REDIS_URL', 'ssm', 'REDIS_URL', 'REDIS_URL={ssm:REDIS_URL}', '{ssm:REDIS_URL}');
        
        $this->collection = new PlaceholderCollection([
            $this->placeholder1,
            $this->placeholder2,
            $this->placeholder3
        ]);
    });

    it('filters placeholders for specific vault', function () {
        $ssmPlaceholders = $this->collection->forVault('ssm', 'default');
        $defaultPlaceholders = $this->collection->forVault('default', 'default');
        
        expect($ssmPlaceholders)->toHaveCount(2);
        expect($ssmPlaceholders->first()->key)->toBe('DB_HOST');
        
        expect($defaultPlaceholders)->toHaveCount(1);
        expect($defaultPlaceholders->first()->key)->toBe('API_KEY');
    });

    it('gets referenced vaults', function () {
        $vaults = $this->collection->getReferencedVaults('default');
        
        expect($vaults)->toContain('default'); // for API_KEY placeholder without vault
        expect($vaults)->toContain('ssm');
        expect($vaults)->toHaveCount(2);
    });

    it('gets all referenced keys', function () {
        $keys = $this->collection->getReferencedKeys();
        
        expect($keys)->toContain('API_KEY');
        expect($keys)->toContain('DB_HOST');
        expect($keys)->toContain('REDIS_URL');
        expect($keys)->toHaveCount(3);
    });

    it('gets referenced keys for specific vault', function () {
        $ssmKeys = $this->collection->getReferencedKeysForVault('ssm', 'default');
        $defaultKeys = $this->collection->getReferencedKeysForVault('default', 'default');
        
        expect($ssmKeys)->toContain('DB_HOST');
        expect($ssmKeys)->toContain('REDIS_URL');
        expect($ssmKeys)->toHaveCount(2);
        
        expect($defaultKeys)->toContain('API_KEY');
        expect($defaultKeys)->toHaveCount(1);
    });

    it('converts to legacy array format', function () {
        $array = $this->collection->toLegacyArray();
        
        expect($array)->toHaveCount(3);
        expect($array[0])->toHaveKey('line');
        expect($array[0])->toHaveKey('vault');
        expect($array[0])->toHaveKey('key');
        expect($array[0]['key'])->toBe('API_KEY');
    });

    it('works with empty collection', function () {
        $empty = new PlaceholderCollection();
        
        expect($empty->getReferencedKeys())->toBeEmpty();
        expect($empty->getReferencedVaults('default'))->toBeEmpty();
        expect($empty->toLegacyArray())->toBeEmpty();
    });
});