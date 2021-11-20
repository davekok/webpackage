<?php

declare(strict_types=1);

namespace davekok\webpackage;

use Exception;
use finfo;
use Traversable;

class BuildCommand
{
    public function __construct(
        private array       $files,
        private string|null $encoding,
                string|null $out,
                string|null $strip,
    ) {
        $this->out = $out ?? getcwd() ?: throw new Exception("Unable to detect current working directory.");
        if (is_dir($this->out) === true) {
            $this->out .= "/" . basename($this->out) . ".wpk";
        }
        if (str_ends_with($this->out, ".wpk") === false) {
            $this->out .= ".wpk";
        }
        $this->strip = realpath($strip ?? dirname($this->out)) ?: throw new Exception("Invalid strip argument");
    }

    public function build(): void
    {
        $handle    = fopen($this->out, "xb") ?: throw new Exception("Unable to create file '{$this->out}'.");
        $formatter = new WebPackageFormatter();
        fwrite($handle, $formatter->formatSignature());
        fwrite($handle, $formatter->formatBuildDate());
        fwrite($handle, $formatter->formatContentEncoding($this->encoding));
        foreach ($this->iterateFiles() as [$fileName, $contentType, $contentLength, $content]) {
            fwrite($handle, $formatter->formatFileName($fileName));
            fwrite($handle, $formatter->formatContentType($contentType));
            fwrite($handle, $formatter->formatContentLength($contentLength));
            fwrite($handle, $formatter->formatStartContent());
            stream_copy_to_stream($content, $handle);
        }
        fwrite($handle, $formatter->formatEndOfFiles());
        fclose($handle);
    }

    private function iterateFiles(): Traversable
    {
        foreach ($this->files as $file) {
            $file = realpath($file);
            if (is_dir($file)) {
                yield from $this->crawlDir($file);
                continue;
            }
            yield $this->openFile($file);
        }
    }

    private function crawlDir(string $path): iterable
    {
        $dir = opendir($path);
        if ($dir === false) return;
        while (($entry = readdir($dir)) !== false) {
            if ($entry[0] === ".") continue;
            $file = "$path/$entry";
            if (is_dir($file)) {
                yield from $this->crawlDir($file);
                continue;
            }
            yield $this->openFile($file);
        }
    }

    private function openFile(string $file): array
    {
        $contentLength = filesize($file);
        if ($contentLength >= 2 ** 24) {
            throw new Exception("File is too large");
        }
        if (str_starts_with($file, $this->strip) === true) {
            $fileName = substr($file, strlen($this->strip));
        } else {
            $fileName = $file;
        }
        if (str_ends_with($file, "index.html") === true) {
            $fileName = substr($fileName, 0, -strlen("index.html"));
        }
        $finfo = new finfo();
        $contentType = $finfo->file($file, FILEINFO_MIME_TYPE);
        if ($contentType === "text/plain" || $contentType === "application/x-empty") {
            $charset = $finfo->file($file, FILEINFO_MIME_ENCODING);
            $charset = $charset !== "binary" ? "; charset=$charset" : "";
            $contentType = match (true) {
                str_ends_with($file, ".html") => "text/html$charset",
                str_ends_with($file, ".css")  => "text/css$charset",
                str_ends_with($file, ".js")   => "application/javascript$charset",
                str_ends_with($file, ".mjs")  => "application/javascript$charset",
                str_ends_with($file, ".json") => "application/json",
                default => $contentType,
            };
        } else if (str_starts_with($contentType, "text/")) {
            $contentType .= "; charset=" . $finfo->file($file, FILEINFO_MIME_ENCODING);
        }
        $content = fopen($file, "rb");
        if ($content === false) {
            throw new Exception("Invalid path or unreadable file: $file");
        }
        return [$fileName, $contentType, $contentLength, $content];
    }
}
