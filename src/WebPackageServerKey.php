<?php

declare(strict_types=1);

namespace davekok\webpackage;

use Exception;
use OpenSSLAsymmetricKey;
use Psr\Log\LoggerInterface;
use Stringable;

class WebPackageServerKey
{
    public function __construct(private readonly OpenSSLAsymmetricKey $key) {}

    public function encrypt(string|Stringable $data): string
    {
        openssl_private_encrypt((string)$data, $encryptedData, $this->key, OPENSSL_PKCS1_OAEP_PADDING)
            ?: throw new Exception("Encrypt failed.");
        return $encryptedData;
    }

    public function decrypt(string|Stringable $encryptedData): string
    {
        openssl_private_decrypt((string)$encryptedData, $data, $this->key, OPENSSL_PKCS1_OAEP_PADDING)
            ?: throw new Exception("Decrypt failed.");
        return $data;
    }
}
