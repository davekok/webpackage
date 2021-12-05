<?php

declare(strict_types=1);

namespace davekok\webpackage;

use DateTime;

class WebPackage
{
    public const CONTENT_TYPE = "application/prs.davekok.webpackage";

    public function __construct(
        public readonly string      $hash            = "",
        public readonly string      $domainName      = "",
        public readonly DateTime    $buildDate       = new DateTime(),
        public readonly string|null $contentEncoding = null,
        public readonly string|null $contentEncoding = null,
        public readonly array       $files           = [],
        public readonly int         $length          = 30,
    ) {}
}
