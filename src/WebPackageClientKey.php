<?php

declare(strict_types=1);

namespace davekok\webpackage;

use Exception;
use OpenSSLAsymmetricKey;
use Stringable;

class WebPackageClientKey
{
    public function __construct(private readonly OpenSSLAsymmetricKey $key) {}

    public function encrypt(string|Stringable $data): string
    {
        openssl_public_encrypt((string)$data, $encryptedData, $this->key, OPENSSL_PKCS1_OAEP_PADDING)
            ?: throw new Exception("Encryption failed.");
        return $encryptedData;
    }

    public function decrypt(string|Stringable $encryptedData): string
    {
        openssl_public_decrypt((string)$encryptedData, $data, $this->key, OPENSSL_PKCS1_OAEP_PADDING)
            ?: throw new Exception("Decryption failed.");
        return $data;
    }
}
