<?php

use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretsCollection;

beforeEach(function () {
    $this->secrets = new SecretsCollection([
        new Secret('DB_HOST', 'localhost'),
        new Secret('DB_PORT', '3306'),
        new Secret('DB_NAME', 'myapp'),
        new Secret('DB_USER', 'admin'),
        new Secret('DB_PASSWORD', 'secret123'),
        new Secret('MAIL_HOST', 'smtp.mailtrap.io'),
        new Secret('MAIL_PORT', '2525'),
        new Secret('MAIL_USERNAME', 'user@example.com'),
        new Secret('MAIL_PASSWORD', 'mailpass'),
        new Secret('REDIS_HOST', '127.0.0.1'),
        new Secret('REDIS_PORT', '6379'),
        new Secret('APP_KEY', 'base64:key'),
        new Secret('APP_DEBUG', 'true'),
        new Secret('DROPBOX_TOKEN', 'token123'),
    ]);
});

describe('SecretsCollection', function () {
    
    it('extends Laravel Collection', function () {
        expect($this->secrets)->toBeInstanceOf(Illuminate\Support\Collection::class);
    });
    
    describe('toKeyValuePair()', function () {
        it('transforms secrets to key-value pairs', function () {
            $pairs = $this->secrets->toKeyValuePair();
            
            expect($pairs)->toBeInstanceOf(SecretsCollection::class);
            expect($pairs->toArray())->toBe([
                'DB_HOST' => 'localhost',
                'DB_PORT' => '3306',
                'DB_NAME' => 'myapp',
                'DB_USER' => 'admin',
                'DB_PASSWORD' => 'secret123',
                'MAIL_HOST' => 'smtp.mailtrap.io',
                'MAIL_PORT' => '2525',
                'MAIL_USERNAME' => 'user@example.com',
                'MAIL_PASSWORD' => 'mailpass',
                'REDIS_HOST' => '127.0.0.1',
                'REDIS_PORT' => '6379',
                'APP_KEY' => 'base64:key',
                'APP_DEBUG' => 'true',
                'DROPBOX_TOKEN' => 'token123',
            ]);
        });
        
        it('handles null values', function () {
            $secrets = new SecretsCollection([
                new Secret('KEY1', 'value1'),
                new Secret('KEY2', null),
                new Secret('KEY3', 'value3'),
            ]);
            
            $pairs = $secrets->toKeyValuePair();
            
            expect($pairs->toArray())->toBe([
                'KEY1' => 'value1',
                'KEY2' => null,
                'KEY3' => 'value3',
            ]);
        });
    });
    
    describe('toEnvString()', function () {
        it('converts collection to .env format string', function () {
            $secrets = new SecretsCollection([
                new Secret('DB_HOST', 'localhost'),
                new Secret('DB_PORT', '3306'),
                new Secret('DB_NAME', 'myapp'),
            ]);
            
            $envString = $secrets->toEnvString();
            
            expect($envString)->toBe(
                'DB_HOST="localhost"' . PHP_EOL .
                'DB_PORT="3306"' . PHP_EOL .
                'DB_NAME="myapp"'
            );
        });
        
        it('handles null values in env string', function () {
            $secrets = new SecretsCollection([
                new Secret('KEY1', 'value1'),
                new Secret('KEY2', null),
                new Secret('KEY3', 'value3'),
            ]);
            
            $envString = $secrets->toEnvString();
            
            expect($envString)->toBe(
                'KEY1="value1"' . PHP_EOL .
                'KEY2=' . PHP_EOL .
                'KEY3="value3"'
            );
        });
        
        it('escapes quotes in values', function () {
            $secrets = new SecretsCollection([
                new Secret('QUOTED', 'value with "quotes"'),
                new Secret('NORMAL', 'normal value'),
            ]);
            
            $envString = $secrets->toEnvString();
            
            expect($envString)->toBe(
                'QUOTED="value with \\"quotes\\""' . PHP_EOL .
                'NORMAL="normal value"'
            );
        });
        
        it('handles empty collection', function () {
            $secrets = new SecretsCollection([]);
            
            expect($secrets->toEnvString())->toBe('');
        });
    });
    
    describe('filterByPatterns()', function () {
        it('filters by single only pattern', function () {
            $filtered = $this->secrets->filterByPatterns('DB_*');
            
            expect($filtered)->toBeInstanceOf(SecretsCollection::class);
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'DB_PORT',
                'DB_NAME',
                'DB_USER',
                'DB_PASSWORD',
            ]);
        });
        
        it('filters by multiple only patterns', function () {
            $filtered = $this->secrets->filterByPatterns('DB_*, MAIL_*');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'DB_PORT',
                'DB_NAME',
                'DB_USER',
                'DB_PASSWORD',
                'MAIL_HOST',
                'MAIL_PORT',
                'MAIL_USERNAME',
                'MAIL_PASSWORD',
            ]);
        });
        
        it('filters with except pattern', function () {
            $filtered = $this->secrets->filterByPatterns(null, 'DB_*');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'MAIL_HOST',
                'MAIL_PORT',
                'MAIL_USERNAME',
                'MAIL_PASSWORD',
                'REDIS_HOST',
                'REDIS_PORT',
                'APP_KEY',
                'APP_DEBUG',
                'DROPBOX_TOKEN',
            ]);
        });
        
        it('combines only and except patterns', function () {
            $filtered = $this->secrets->filterByPatterns('*_HOST, *_PORT', 'REDIS_*');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'DB_PORT',
                'MAIL_HOST',
                'MAIL_PORT',
            ]);
        });
        
        it('handles spaces in comma-separated patterns', function () {
            $filtered = $this->secrets->filterByPatterns('  DB_HOST ,  DB_PORT  ,  APP_*  ');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'DB_PORT',
                'APP_KEY',
                'APP_DEBUG',
            ]);
        });
        
        it('returns all when only is null or empty', function () {
            $filtered1 = $this->secrets->filterByPatterns(null);
            $filtered2 = $this->secrets->filterByPatterns('');
            
            expect($filtered1->count())->toBe(14);
            expect($filtered2->count())->toBe(14);
        });
        
        it('handles empty strings in pattern lists', function () {
            $filtered = $this->secrets->filterByPatterns('DB_*, , ,MAIL_*');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'DB_PORT',
                'DB_NAME',
                'DB_USER',
                'DB_PASSWORD',
                'MAIL_HOST',
                'MAIL_PORT',
                'MAIL_USERNAME',
                'MAIL_PASSWORD',
            ]);
        });
        
        it('handles exact key matches', function () {
            $filtered = $this->secrets->filterByPatterns('DB_HOST, APP_KEY');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'APP_KEY',
            ]);
        });
        
        it('handles wildcards in middle of pattern', function () {
            $filtered = $this->secrets->filterByPatterns('*_HOS*');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'MAIL_HOST',
                'REDIS_HOST',
            ]);
        });
        
        it('except overrides only when matching', function () {
            $filtered = $this->secrets->filterByPatterns('DB_*', 'DB_PASSWORD');
            
            expect($filtered->allKeys()->toArray())->toBe([
                'DB_HOST',
                'DB_PORT',
                'DB_NAME',
                'DB_USER',
            ]);
        });
    });
    
    describe('allKeys()', function () {
        it('returns collection of all keys', function () {
            $keys = $this->secrets->allKeys();
            
            expect($keys)->toBeInstanceOf(SecretsCollection::class);
            expect($keys->toArray())->toBe([
                'DB_HOST',
                'DB_PORT',
                'DB_NAME',
                'DB_USER',
                'DB_PASSWORD',
                'MAIL_HOST',
                'MAIL_PORT',
                'MAIL_USERNAME',
                'MAIL_PASSWORD',
                'REDIS_HOST',
                'REDIS_PORT',
                'APP_KEY',
                'APP_DEBUG',
                'DROPBOX_TOKEN',
            ]);
        });
        
        it('handles empty collection', function () {
            $secrets = new SecretsCollection([]);
            $keys = $secrets->allKeys();
            
            expect($keys->toArray())->toBe([]);
        });
    });
    
    describe('hasKey()', function () {
        it('returns true for existing keys', function () {
            expect($this->secrets->hasKey('DB_HOST'))->toBeTrue();
            expect($this->secrets->hasKey('MAIL_PASSWORD'))->toBeTrue();
            expect($this->secrets->hasKey('APP_KEY'))->toBeTrue();
        });
        
        it('returns false for non-existing keys', function () {
            expect($this->secrets->hasKey('NON_EXISTENT'))->toBeFalse();
            expect($this->secrets->hasKey('DB_HOSTS'))->toBeFalse(); // Similar but different
            expect($this->secrets->hasKey(''))->toBeFalse();
        });
        
        it('is case sensitive', function () {
            expect($this->secrets->hasKey('db_host'))->toBeFalse();
            expect($this->secrets->hasKey('DB_HOST'))->toBeTrue();
        });
    });
    
    describe('getByKey()', function () {
        it('returns Secret for existing key', function () {
            $secret = $this->secrets->getByKey('DB_HOST');
            
            expect($secret)->toBeInstanceOf(Secret::class);
            expect($secret->key())->toBe('DB_HOST');
            expect($secret->value())->toBe('localhost');
        });
        
        it('returns null for non-existing key', function () {
            expect($this->secrets->getByKey('NON_EXISTENT'))->toBeNull();
            expect($this->secrets->getByKey(''))->toBeNull();
        });
        
        it('returns first match when duplicates exist', function () {
            $secrets = new SecretsCollection([
                new Secret('DUPLICATE', 'first'),
                new Secret('OTHER', 'value'),
                new Secret('DUPLICATE', 'second'),
            ]);
            
            $secret = $secrets->getByKey('DUPLICATE');
            
            expect($secret->value())->toBe('first');
        });
    });
    
    describe('mapToOnly()', function () {
        it('returns collection mapped to only specified attributes', function () {
            // This method maps each Secret to only show specified attributes
            // The number of secrets remains the same, but each only shows selected attributes
            $filtered = $this->secrets->mapToOnly(['key', 'value']);
            
            expect($filtered)->toBeInstanceOf(Illuminate\Support\Collection::class);
            expect($filtered->count())->toBe(14); // All secrets, but only with key/value attributes
            
            // Check that first item only has key and value
            $first = $filtered->first();
            expect($first)->toHaveKeys(['key', 'value']);
            expect($first)->not->toHaveKey('secure');
            expect($first)->not->toHaveKey('environment');
        });
        
        it('returns empty attributes when given empty array', function () {
            $filtered = $this->secrets->mapToOnly([]);
            
            expect($filtered->count())->toBe(14); // Still has all secrets
            expect($filtered->first())->toBe([]); // But each is empty
        });
        
        it('handles non-existent attributes gracefully', function () {
            $filtered = $this->secrets->mapToOnly(['key', 'nonexistent']);
            
            expect($filtered->count())->toBe(14);
            $first = $filtered->first();
            expect($first)->toHaveKey('key');
            expect($first)->not->toHaveKey('nonexistent');
        });
    });
    
    describe('edge cases', function () {
        it('handles secrets with special characters in keys', function () {
            $secrets = new SecretsCollection([
                new Secret('SPECIAL_!@#$', 'value1'),
                new Secret('WITH.DOTS', 'value2'),
                new Secret('WITH-DASHES', 'value3'),
            ]);
            
            expect($secrets->hasKey('SPECIAL_!@#$'))->toBeTrue();
            expect($secrets->getByKey('WITH.DOTS')->value())->toBe('value2');
            expect($secrets->allKeys()->toArray())->toContain('WITH-DASHES');
        });
        
        it('handles large collections efficiently', function () {
            $largeSecrets = new SecretsCollection();
            for ($i = 0; $i < 1000; $i++) {
                $largeSecrets->push(new Secret("KEY_$i", "value_$i"));
            }
            
            expect($largeSecrets->count())->toBe(1000);
            expect($largeSecrets->hasKey('KEY_500'))->toBeTrue();
            expect($largeSecrets->getByKey('KEY_999')->value())->toBe('value_999');
            
            $filtered = $largeSecrets->filterByPatterns('KEY_1*');
            expect($filtered->count())->toBe(111); // KEY_1, KEY_10-19, KEY_100-199
        });
        
        it('maintains collection immutability', function () {
            $original = $this->secrets->count();
            
            $filtered = $this->secrets->filterByPatterns('DB_*');
            $pairs = $this->secrets->toKeyValuePair();
            $keys = $this->secrets->allKeys();
            
            expect($this->secrets->count())->toBe($original);
            expect($filtered)->not->toBe($this->secrets);
            expect($pairs)->not->toBe($this->secrets);
            expect($keys)->not->toBe($this->secrets);
        });
    });
});