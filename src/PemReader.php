<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\kernel\ReadBuffer;
use davekok\kernel\ReaderException;
use OpenSSLAsymmetricKey;

class PemReader implements Reader
{
    private string $pem = "";

    public function __construct(private readonly bool $privateKey = false) {}

    public function read(ReadBuffer $buffer): OpenSSLAsymmetricKey|null
    {
        try {
            $this->pem .= $buffer->mark()->end()->getString();

            if ($buffer->isLastChunk() === false) {
                return null;
            }

            if ($this->privateKey === true) {
                return openssl_pkey_get_private($this->pem) ?: throw new ReaderException("Unable to parse PEM.");
            }

            return openssl_pkey_get_public($this->pem) ?: throw new ReaderException("Unable to parse PEM.");

        } catch (Throwable $e) {
            $buffer->reset();
            throw $e;
        }
    }
}
