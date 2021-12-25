<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\kernel\WriteBuffer;
use davekok\kernel\WriterException;
use OpenSSLAsymmetricKey;

class PemWriter implements Writer
{
    private string $pem = "";
    private int $offset = 0;

    public function __construct(OpenSSLAsymmetricKey $key)
    {
        openssl_pkey_export($key, $this->pem) ?: throw new WriterException("Unable to format PEM.");
    }

    public function write(WriteBuffer $buffer): bool
    {
        return $buffer->addChunk($this->offset, $this->pem);
    }
}
