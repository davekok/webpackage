<?php

declare(strict_types=1);

namespace davekok\webpackage;

class File
{
    public function __construct(
        public readonly string $fileName,
        public readonly string $contentType,
        public readonly int    $contentLength,
        public readonly string $content,
    ) {}
}
