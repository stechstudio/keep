<?php

namespace STS\Keep\Services;

class SecretsEncryption
{
    public static function deriveKey(string $appKey): string
    {
        return hash('sha256', $appKey . 'keep-secrets-v1');
    }

    public static function encrypt(array $secrets, string $appKey): string
    {
        $key = self::deriveKey($appKey);
        $data = serialize($secrets);
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . base64_encode($iv));
    }

    public static function decrypt(string $encryptedData, string $appKey): array
    {
        $key = self::deriveKey($appKey);
        $data = base64_decode($encryptedData);
        
        [$encrypted, $iv] = explode('::', $data, 2);
        $iv = base64_decode($iv);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt secrets cache. Invalid APP_KEY or corrupted data.');
        }
        
        return unserialize($decrypted);
    }
}