<?php

declare(strict_types=1);

namespace davekok\webpackage;

use Exception;
use finfo;

class BuildCommand
{
    public function __construct(
        private WebPackageClientKey $webPackageClientKey,
        private string              $domain,
        private string|null         $certificate,
        private array               $files,
        private string|null         $encoding,
                string|null         $out,
                string|null         $strip,
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
        $formatter = new WebPackageFormatter();
        $buildDate = new DateTime();
        $files     = iterator_to_array($this->iterateFiles());

        $hash = new WebPackageHash($formatter);
        $hash->addHead($this->domain, $buildDate, $this->encoding)
        foreach ($files as [$fileName, $contentType, $contentHash, $contentLength, $content]) {
            $hash->addFile($fileName, $contentType, $contentHash, $contentLength, $content);
        }
        $hash->addEndOfFiles();

        $handle = fopen($this->out, "xb") ?: throw new Exception("Unable to create file '{$this->out}'.");
        fwrite($handle, $formatter->formatHead(
            hash:            $this->webPackageClientKey->encrypt($hash),
            domain:          $this->domain,
            buildDate:       $buildDate,
            contentEncoding: $this->encoding,
            certificate:     $this->certificate === null ? null : $this->webPackageClientKey->encrypt(
                file_get_contents($this->certificate) ?: throw new Exception("Unable to read {$this->certificate}")
            )
        ));
        foreach ($files as [$fileName, $contentType, $contentHash, $contentLength, $content]) {
            fwrite($handle, $formatter->formatFileHead($fileName, $contentType, $contentHash, $contentLength, $content));
            fseek($content, 0);
            stream_copy_to_stream($content, $handle);
        }
        fwrite($handle, $formatter->formatEndOfFiles());
    }

    private function iterateFiles(): iterable
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
        $contentHash = hash_file("sha3-256", $file, binary: true);
        $content     = fopen($file, "rb");
        if ($content === false) {
            throw new Exception("Invalid path or unreadable file: $file");
        }
        return [$fileName, $contentType, $contentHash, $contentLength, $content];
    }
}
