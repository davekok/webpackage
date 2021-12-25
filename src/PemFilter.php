<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\kernel\Actionable;
use davekok\kernel\Readable;
use davekok\kernel\Writable;
use InvalidArgumentException;

class PemFilter
{
    public function read(Actionable $actionable, callable $setter, bool $privateKey = false): void
    {
        $actionable instanceof Readable ?: throw new InvalidArgumentException("Expected an readable actionable.");
        $actionable->read(new PemReader($privateKey), $setter);
    }

    public function write(Actionable $actionable, OpenSSLAsymmetricKey $key): void
    {
        $actionable instanceof Writable ?: throw new InvalidArgumentException("Expected an writable actionable.");
        $actionable->write(new PemWriter($key));
    }
}
