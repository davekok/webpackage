<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\stream\Action;
use davekok\stream\Writer;
use davekok\stream\WriteBuffer;
use ArrayIterator;

enum WebPackageWriter_State
{
    case SIGNATURE;
    case CONTENT_ENCODING;
    case BUILD_DATE;
    case FILES;
    case FILE;
    case FILE_NAME;
    case CONTENT_TYPE;
    case CONTENT_HASH;
    case CONTENT_LENGTH;
    case CONTENT;
    case CONTENT_1;
    case NEXT_FILE;
    case END_OF_FILES;
}

class WebPackageWriter implements Writer
{
    private WebPackageWriter_State $state = WebPackageWriter_State::SIGNATURE;
    private ArrayIterator $files;
    private File $file;
    private int $offset;

    public function __construct(
        private WebPackage $webpackage,
        private WebPackageFormatter $formatter = new WebPackageFormatter,
    ) {}

    public function write(WriteBuffer $buffer): bool
    {
        for (;;) {
            switch ($this->state) {
                case WebPackageWriter_State::SIGNATURE:
                    $signature = $this->formatter->formatSignature();
                    if ($buffer->valid(strlen($signature)) === false) {
                        return false;
                    }
                    $buffer->add($signature);
                    $this->state = WebPackageWriter_State::CONTENT_ENCODING;
                case WebPackageWriter_State::CONTENT_ENCODING:
                    $encoding = $this->formatter->formatContentEncoding($this->webpackage->contentEncoding);
                    if ($buffer->valid(strlen($encoding)) === false) {
                        return false;
                    }
                    $buffer->add($encoding);
                    $this->state = WebPackageWriter_State::BUILD_DATE;
                case WebPackageWriter_State::BUILD_DATE:
                    $date = $this->formatter->formatBuildDate($this->webpackage->buildDate);
                    if ($buffer->valid(strlen($date)) === false) {
                        return false;
                    }
                    $buffer->add($date);
                    $this->state = WebPackageWriter_State::FILES;
                case WebPackageWriter_State::FILES:
                    $this->files = new ArrayIterator($this->webpackage->files);
                    if ($this->files->valid() === false) {
                        $this->state = WebPackageWriter_State::END_OF_FILES;
                        continue 2;
                    }
                    $this->state = WebPackageWriter_State::FILE;
                case WebPackageWriter_State::FILE:
                    $this->file  = $this->files->current();
                    $this->state = WebPackageWriter_State::FILE_NAME;
                case WebPackageWriter_State::FILE_NAME:
                    $fileName = $this->formatter->formatFileName($this->file->fileName);
                    if ($buffer->valid(strlen($fileName)) === false) {
                        return false;
                    }
                    $buffer->add($fileName);
                    $this->state = WebPackageWriter_State::CONTENT_TYPE;
                case WebPackageWriter_State::CONTENT_TYPE:
                    $contentType = $this->formatter->formatContentType($this->file->contentType);
                    if ($buffer->valid(strlen($contentType)) === false) {
                        return false;
                    }
                    $buffer->add($contentType);
                    $this->state = WebPackageWriter_State::CONTENT_HASH;
                case WebPackageWriter_State::CONTENT_HASH:
                    $contentHash = $this->formatter->formatContentHash($this->file->contentHash);
                    if ($buffer->valid(strlen($contentHash)) === false) {
                        return false;
                    }
                    $buffer->add($contentHash);
                    $this->state = WebPackageWriter_State::CONTENT_LENGTH;
                case WebPackageWriter_State::CONTENT_LENGTH:
                    $contentLength = $this->formatter->formatContentLength($this->file->contentLength);
                    if ($buffer->valid(strlen($contentLength)) === false) {
                        return false;
                    }
                    $buffer->add($contentLength);
                    $this->state = WebPackageWriter_State::CONTENT;
                case WebPackageWriter_State::CONTENT:
                    $startContent = $this->formatter->formatStartContent();
                    if ($buffer->valid(strlen($startContent)) === false) {
                        return false;
                    }
                    $buffer->add($startContent);
                    $this->offset = 0;
                    $this->state  = WebPackageWriter_State::CONTENT_1;
                case WebPackageWriter_State::CONTENT_1:
                    if ($buffer->addChunk($this->offset, $this->file->content)) {
                        return false;
                    }
                    $this->state = WebPackageWriter_State::NEXT_FILE;
                case WebPackageWriter_State::NEXT_FILE:
                    $this->files->next();
                    if ($this->files->valid() === true) {
                        $this->state = WebPackageWriter_State::FILE;
                        continue 2;
                    }
                    $this->state = WebPackageWriter_State::END_OF_FILES;
                case WebPackageWriter_State::END_OF_FILES:
                    $endOfFiles = $this->formatter->formatEndOfFiles();
                    if ($buffer->valid(strlen($endOfFiles)) === false) {
                        return false;
                    }
                    $buffer->add($endOfFiles);
                    return true;
            }
        }
    }
}
