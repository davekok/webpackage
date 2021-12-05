<?php

declare(strict_types=1);

namespace davekok\webpackage;

use DateTime;
use Exception;
use HashContext;

class WebPackageHash
{
    private readonly HashContext $context;

    public function __construct(
        public readonly WebPackageFormatter $formatter = new WebPackageFormatter()
    ) {
        $this->context = hash_init("sha3-256");
    }

    public function addHead(string $domain, DateTime $buildDate, string|null $encoding): void
    {
        hash_update($hashctx, $this->formatter->formatDomain($this->domain)) ?: new Exception("Update hash failed.");
        hash_update($hashctx, $formatter->formatBuildDate($buildDate)) ?: new Exception("Update hash failed.");
        if ($encoding !== null) {
            hash_update($hashctx, $formatter->formatContentEncoding($encoding)) ?: new Exception("Update hash failed.");
        }
    }

    public function addFile(string $fileName, string $contentType, string $contentHash, int $contentLength, mixed $content): void
    {
        hash_update($hashctx, $formatter->formatFileName($fileName)) ?: new Exception("Update hash failed.");
        hash_update($hashctx, $formatter->formatContentType($contentType)) ?: new Exception("Update hash failed.");
        hash_update($hashctx, $formatter->formatContentHash($contentHash)) ?: new Exception("Update hash failed.");
        hash_update($hashctx, $formatter->formatContentLength($contentLength)) ?: new Exception("Update hash failed.");
        hash_update($hashctx, $formatter->formatStartContent()) ?: new Exception("Update hash failed.");
        if (is_resource($content) === true) {
            fseek($content, 0);
            hash_update_stream($hashctx, $content) ?: new Exception("Update hash failed.");
            return;
        }
        hash_update($hashctx, $content) ?: new Exception("Update hash failed.");
        return;
    }

    public function addEndOfFile(): self
    {
        hash_update($hashctx, $formatter->formatEndOfFiles()) ?: new Exception("Update hash failed.");
        return $this;
    }

    public function __toString(): string
    {
        return hash_final($hashctx, binary: true);
    }
}
