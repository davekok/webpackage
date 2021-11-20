<?php

declare(strict_types=1);

namespace davekok\webpackage;

use DateTime;
use Exception;

class WebPackageFormatter
{
    public function format(WebPackage $wpk): string
    {
        return $this->formatSignature()
            . $this->formatBuildDate($wpk->buildDate)
            . $this->formatContentEncoding($wpk->contentEncoding)
            . array_reduce(
                $wpk->files,
                fn(string $prev, File $file) => $prev
                    . $this->formatFileName($file->fileName)
                    . $this->formatContentType($file->contentType)
                    . $this->formatContentLength($file->contentLength)
                    . $this->formatStartContent()
                    . $file->content,
                ""
              )
            . $this->formatEndOfFiles();
    }

    public function formatSignature(): string
    {
        return chr(WebPackageToken::SIGNATURE->value) . "WPK\x0D\x0A\x1A\x0A";
    }

    public function formatBuildDate(DateTime $buildDate = new DateTime()): string
    {
        return chr(WebPackageToken::BUILD_DATE->value) . str_replace("+00:00", "Z", $buildDate->format("c"));
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

    public function formatFileName(string $fileName): string
    {
        if (preg_match('~/([A-Za-z0-9_-]+/)*[A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+|/)?~', $fileName) !== 1) {
            throw new Exception("Invalid file name: $fileName");
        }
        return chr(WebPackageToken::FILE_NAME->value) . $fileName;
    }

    public function formatContentType(string $contentType): string
    {
        if (preg_match('~(application|audio|example|font|image|message|model|multipart|text|video)/[A-Za-z0-9_.-]+'
            . '(; *[a-z]+=([\x21\x23-\x3A\x3C-\x7E]*|"[\x20\x21\x23-\x7E]*"))*~', $contentType) !== 1) {
            throw new Exception("Invalid content type: $contentType");
        }
        return chr(WebPackageToken::CONTENT_TYPE->value) . $contentType;
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

    public function formatStartContent(): string
    {
        return chr(WebPackageToken::START_CONTENT->value);
    }

    public function formatEndOfFiles(): string
    {
        return chr(WebPackageToken::END_OF_FILES->value);
    }
}
