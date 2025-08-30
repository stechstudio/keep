<?php

namespace STS\Keep\Services;

class Crypt
{
    private string $key;

    public function __construct(string $keyPart)
    {
        $this->key = $this->deriveEncryptionKey($keyPart);
    }

    private function deriveEncryptionKey(string $keyPart): string
    {
        $sources = [
            // System context (cached by PHP)
            gethostname(),
            getcwd(),

            // Deployment fingerprint
            $this->getDeploymentHash(),

            // Process context
            get_current_user(),

            $keyPart,
        ];

        $masterKey = hash('sha256', implode('|', array_filter($sources)), true);

        // Derive a 32-byte key suitable for encryption
        return sodium_crypto_kdf_derive_from_key(
            32,                    // 32 bytes for ChaCha20-Poly1305
            1,                     // subkey ID
            'KeepSecr',            // context (8 bytes exactly)
            $masterKey
        );
    }

    private function getDeploymentHash(): string
    {
        // OPcache caches this after the first load
        $contents = @include getcwd().'/vendor/composer/installed.php';

        return is_array($contents)
            ? hash('sha256', serialize($contents))
            : '';
    }

    public function encrypt(array $secrets): string
    {
        $data = serialize($secrets);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($data, $nonce, $this->key);

        return base64_encode($nonce.$encrypted);
    }

    public function decrypt(string $encryptedData): array
    {
        $data = base64_decode($encryptedData);

        if (strlen($data) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted data: too short');
        }

        $nonce = substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key);

        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt secrets cache. Invalid key or corrupted data.');
        }

        return unserialize($decrypted);
    }
}
