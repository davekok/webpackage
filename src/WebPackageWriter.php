<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\stream\Activity;
use davekok\stream\ReadyState;
use davekok\stream\Writer;
use davekok\stream\WriterBuffer;
use davekok\stream\WriterException;
use ArrayIterator;

enum WebPackageWriter_State
{
    case SIGNATURE;
    case BUILD_DATE;
    case CONTENT_ENCODING;
    case FILES;
    case FILE;
    case FILE_NAME;
    case CONTENT_TYPE;
    case CONTENT_LENGTH;
    case CONTENT;
    case CONTENT_1;
    case NEXT_FILE;
    case END_OF_FILES;
}

class WebPackageWriter implements Writer
{
    private WebPackage $webpackage;
    private ArrayIterator $files;
    private File $file;
    private int $offset;

    public function __construct(
        private Activity $activity,
        private WebPackageWriter_State $state  = WebPackageWriter_State::SIGNATURE,
        private WebPackageFormatter $formatter = new WebPackageFormatter,
    ) {}

    public function send(WebPackage $webpackage): void
    {
        $this->webpackage = $webpackage;
        $this->activity->addWrite($this);
    }

    public function write(WriterBuffer $buffer): void
    {
        for (;;) {
            switch ($this->state) {
                case WebPackageWriter_State::SIGNATURE:
                    $signature = $this->formatter->formatSignature();
                    if ($buffer->valid(strlen($signature)) === false) {
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($signature);
                    $this->state = WebPackageWriter_State::BUILD_DATE;
                case WebPackageWriter_State::BUILD_DATE:
                    $date = $this->formatter->formatBuildDate($this->webpackage->buildDate);
                    if ($buffer->valid(strlen($date)) === false) {
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($date);
                    $this->state = WebPackageWriter_State::CONTENT_ENCODING;
                case WebPackageWriter_State::CONTENT_ENCODING:
                    $encoding = $this->formatter->formatContentEncoding($this->webpackage->contentEncoding);
                    if ($buffer->valid(strlen($encoding)) === false) {
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($encoding);
                    $this->state = WebPackageWriter_State::FILES;
                case WebPackageWriter_State::FILES:
                    $this->files = new ArrayIterator($this->webpackage->files);
                    if ($this->files->valid() === false) {
                        $this->state = WebPackageWriter_State::END_OF_FILES;
                        continue 2;
                    }
                    $this->state = WebPackageWriter_State::FILE;
                case WebPackageWriter_State::FILE:
                    $this->file = $this->files->current();
                    $this->state = WebPackageWriter_State::FILE_NAME;
                case WebPackageWriter_State::FILE_NAME:
                    $fileName = $this->formatter->formatFileName($this->file->fileName);
                    if ($buffer->valid(strlen($fileName)) === false) {
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($fileName);
                    $this->state = WebPackageWriter_State::CONTENT_TYPE;
                case WebPackageWriter_State::CONTENT_TYPE:
                    $contentType = $this->formatter->formatContentType($this->file->contentType);
                    if ($buffer->valid(strlen($contentType)) === false) {
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($contentType);
                    $this->state = WebPackageWriter_State::CONTENT_LENGTH;
                case WebPackageWriter_State::CONTENT_LENGTH:
                    $contentLength = $this->formatter->formatContentLength($this->file->contentLength);
                    if ($buffer->valid(strlen($contentLength)) === false) {
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($contentLength);
                    $this->state = WebPackageWriter_State::CONTENT;
                case WebPackageWriter_State::CONTENT:
                    $startContent = $this->formatter->formatStartContent();
                    if ($buffer->valid(strlen($startContent)) === false) {
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($startContent);
                    $this->offset = 0;
                    $this->state = WebPackageWriter_State::CONTENT_1;
                case WebPackageWriter_State::CONTENT_1:
                    if ($buffer->addChunk($this->offset, $this->file->content)) {
                        $this->activity->repeat();
                        return;
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
                        $this->activity->repeat();
                        return;
                    }
                    $buffer->add($endOfFiles);
                    return;
            }
        }
    }
}
