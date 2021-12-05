<?php

declare(strict_types=1);

namespace davekok\webpackage;

use DateTime;
use Exception;

class WebPackageFormatter
{
    public function formatHead(
        string      $hash,
        string      $domain,
        DateTime    $buildDate,
        string|null $certificate,
        string|null $contentEncoding
    ): string {
        return $this->formatSignature($hash)
            . $this->formatDomain($domain)
            . $this->formatBuildDate($buildDate)
            . $this->formatCertificate($certificate)
            . $this->formatContentEncoding($contentEncoding);
    }

    public function lengthHead(
        string      $hash,
        string      $domain,
        DateTime    $buildDate,
        string|null $certificate,
        string|null $contentEncoding
    ): int {
        return $this->lengthSignature($hash)
            + $this->lengthDomain($domain)
            + $this->lengthBuildDate($buildDate)
            + $this->lengthCertificate($certificate)
            + $this->lengthContentEncoding($contentEncoding);
    }

    public function formatFileHead(
        string $fileName,
        string $contentType,
        string $contentHash,
        string $contentLength,
    ): string {
        return $this->formatFileName($fileName)
            + $this->formatContentType($contentType)
            + $this->formatContentHash($contentHash)
            + $this->formatContentLength($contentLength)
            + $this->formatStartContent();
    }

    public function lengthFile(
        string $fileName,
        string $contentType,
        string $contentHash,
        string $contentLength,
    ): int {
        return $this->lengthFileName($fileName)
            + $this->lengthContentType($contentType)
            + $this->lengthContentHash($contentHash)
            + $this->lengthContentLength($contentLength)
            + $this->lengthStartContent()
            + $contentLength;
    }

    public function formatSignature(string $hash): string
    {
        return chr(WebPackageToken::SIGNATURE->value) . "WPK\x0D\x0A\x1A\x0A" . chr(strlen($hash)) . $hash;
    }

    public function lengthSignature(string $hash): int
    {
        return 9 + strlen($hash);
    }

    public function formatDomain(string $domain): string
    {
        return chr(WebPackageToken::DOMAIN->value) . $domain;
    }

    public function lengthDomain(string $domain): int
    {
        return 1 + strlen($domain);
    }

    public function formatCertificate(string $certificate): string
    {
        $length = strlen($certificate);
        return chr(WebPackageToken::CERTIFICATE->value) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF) . $certificate;
    }

    public function lengthCertificate(string $certificate): int
    {
        return 3 + strlen($certificate);
    }

    public function formatBuildDate(DateTime $buildDate = new DateTime()): string
    {
        return chr(WebPackageToken::BUILD_DATE->value)
            . str_replace("+00:00", "Z", (clone $buildDate)->setTimezone(new DateTimeZone("UTC"))->format("c"));
    }

    public function lengthBuildDate(DateTime $buildDate = new DateTime()): int
    {
        return 1 + 20;
    }

    public function formatContentEncoding(string|null $contentEncoding): string
    {
        if ($contentEncoding === null) {
            return "";
        }
        if (in_array($contentEncoding, ["br", "compress", "deflate", "gzip"]) === false) {
            throw new Exception("Invalid content encoding: $contentEncoding");
        }
        return chr(WebPackageToken::CONTENT_ENCODING->value) . $contentEncoding;
    }

    public function lengthContentEncoding(string|null $contentEncoding): int
    {
        return $contentEncoding === null ? 0 : (1 + strlen($contentEncoding));
    }

    public function formatFileName(string $fileName): string
    {
        if (preg_match('~/([A-Za-z0-9_-]+/)*[A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+|/)?~', $fileName) !== 1) {
            throw new Exception("Invalid file name: $fileName");
        }
        return chr(WebPackageToken::FILE_NAME->value) . $fileName;
    }

    public function lengthFileName(string $fileName): int
    {
        return 1 + strlen($fileName);
    }

    public function formatContentType(string $contentType): string
    {
        if (preg_match('~(application|audio|example|font|image|message|model|multipart|text|video)/[A-Za-z0-9_.-]+'
            . '(; *[a-z]+=([\x21\x23-\x3A\x3C-\x7E]*|"[\x20\x21\x23-\x7E]*"))*~', $contentType) !== 1) {
            throw new Exception("Invalid content type: $contentType");
        }
        return chr(WebPackageToken::CONTENT_TYPE->value) . $contentType;
    }

    public function lengthContentType(string $contentType): int
    {
        return 1 + strlen($contentType);
    }

    public function formatContentHash(string $hash): string
    {
        return chr(WebPackageToken::CONTENT_HASH->value) . $hash;
    }

    public function lengthContentHash(string $hash): int
    {
        return 1 + strlen($hash);
    }

    public function formatContentLength(int $contentLength): string
    {
        if ($contentLength < 0 || $contentLength >= 2**24) {
            throw new Exception("Invalid content length: $contentLength");
        }
        return chr(WebPackageToken::CONTENT_LENGTH->value)
            . chr(($contentLength << 16) & 0xFF)
            . chr(($contentLength << 8) & 0xFF)
            . chr($contentLength & 0xFF);
    }

    public function lengthContentLength(int $contentLength): int
    {
        return 4;
    }

    public function formatStartContent(): string
    {
        return chr(WebPackageToken::START_CONTENT->value);
    }

    public function lengthStartContent(): int
    {
        return 1;
    }

    public function formatEndOfFiles(): string
    {
        return chr(WebPackageToken::END_OF_FILES->value);
    }

    public function lengthEndOfFiles(): int
    {
        return 1;
    }
}
