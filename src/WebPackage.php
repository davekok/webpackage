<?php

declare(strict_types=1);

namespace davekok\webpackage;

use DateTime;

class WebPackage
{
    public function __construct(
        public readonly DateTime    $buildDate       = new DateTime(),
        public readonly string|null $contentEncoding = null,
        public readonly array       $files           = [],
    ) {}
}
