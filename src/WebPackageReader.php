<?php

declare(strict_types=1);

namespace davekok\webpackage;

use DateTime;
use davekok\lalr1\Parser;
use davekok\stream\Activity;
use davekok\stream\Reader;
use davekok\stream\ReaderBuffer;
use davekok\stream\ReaderException;

enum WebPackageReader_State
{
    case START;
    case SIGNATURE;
    case BUILD_DATE;
    case BUILD_DATE_1;
    case CONTENT_ENCODING;
    case FILE_NAME;
    case FILE_NAME_1;
    case FILE_NAME_2;
    case FILE_NAME_3;
    case CONTENT_TYPE;
    case CONTENT_TYPE_1;
    case CONTENT_TYPE_2;
    case CONTENT_TYPE_3;
    case CONTENT_TYPE_4;
    case CONTENT_TYPE_5;
    case CONTENT_TYPE_6;
    case CONTENT_TYPE_7;
    case CONTENT_TYPE_8;
    case CONTENT_LENGTH;
    case CONTENT_LENGTH_1;
    case CONTENT_LENGTH_2;
    case CONTENT;
}

class WebPackageReader implements Reader
{
    private int $contentLength = 0;

    public function __construct(
        private Parser $parser,
        private Activity $activity,
        private WebPackageReader_State $state = WebPackageReader_State::START,
    ) {}

    public function receive(WebPackageHandler $handler): void
    {
        $this->activity
            ->addRead($this)
            ->add($handler->handleWebPackage(...));
    }

    public function reset(ReaderBuffer $buffer): void
    {
        $buffer->reset();
        $this->parser->reset();
    }

    /**
     * webpackage                    = signature build-date files end-of-files ;
     * webpackage                    = signature build-date content-encoding files end-of-files ;
     * files                         = files file ;
     * files                         = file ;
     * file                          = file-name content-type content-length content ;
     *
     * signature                     = "\x89" "WPK\x0D\x0A\x1A\x0A" ; // similar to PNG signature
     * end-of-files                  = "\x00" ;
     * build-date                    = "\x01" [0-9]{4} "-" ("0" [1-9] | "1" [0-2]) "-" ( "0" [1-9] | [1-2] [0-9] | "3" [0-1] )
     *                                     "T" ( [0-1] [0-9] | "2" [0-3] ) ":" ( [0-5] [0-9] ) ":" ( [0-5] [0-9] )
     *                                     ( "Z" | ("+" | "-") [0-9]{2} ":" [0-9]{2} ) ;
     * content-encoding              = "\x02" ( "gzip" | "compress" | "deflate" | "br" ) ;
     *
     * reserving \x02 - \x0F for future use
     *
     * file-name                     = "\x10" "/" ([A-Za-z0-9_-]+ "/")* [A-Za-z0-9_-]* ("." [A-Za-z0-9_-]+ | "/")? ;
     * content-type                  = "\x11" (
     *                                     "application"
     *                                     | "audio"
     *                                     | "example"
     *                                     | "font"
     *                                     | "image"
     *                                     | "message"
     *                                     | "model"
     *                                     | "multipart"
     *                                     | "text"
     *                                     | "video"
     *                                 ) "/" [A-Za-z0-9_.-]+ (";" " "* [a-z]+ "=" "\"" [\x20\x21\x23-\x7E]* "\"")* ;
     * content-length                = "\x12" [\x00-\xFF]{3} ; // most significant byte first
     *
     * reserving \x14 - \xFE for future use
     *
     * content                       = "\xFF" [\x00-\xFF]{content-length}
     *
     * File names must be sorted in ascending order.
     * "/" is a valid file name.
     */
    public function read(ReaderBuffer $buffer): void
    {
        try {
            while ($buffer->valid()) {
                switch ($this->state) {
                    case WebPackageReader_State::START:
                        switch ($buffer->current()) {
                            case WebPackageToken::SIGNATURE->value:
                                $this->state = WebPackageReader_State::SIGNATURE;
                                $buffer->next()->mark();
                                continue 3;
                            case WebPackageToken::END_OF_FILES->value:
                                $buffer->next()->mark();
                                $this->parser->pushToken("end-of-files");
                                $this->parser->endOfTokens();
                                return;
                            case WebPackageToken::BUILD_DATE->value:
                                $this->state = WebPackageReader_State::BUILD_DATE;
                                $buffer->next()->mark();
                                continue 3;
                            case WebPackageToken::CONTENT_ENCODING->value:
                                $this->state = WebPackageReader_State::CONTENT_ENCODING;
                                $buffer->next()->mark();
                                continue 3;
                            case WebPackageToken::FILE_NAME->value:
                                $this->state = WebPackageReader_State::FILE_NAME;
                                $buffer->next()->mark();
                                continue 3;
                            case WebPackageToken::CONTENT_TYPE->value:
                                $this->state = WebPackageReader_State::CONTENT_TYPE;
                                $buffer->next()->mark();
                                continue 3;
                            case WebPackageToken::CONTENT_LENGTH->value:
                                $this->state = WebPackageReader_State::CONTENT_LENGTH;
                                $buffer->next()->mark();
                                continue 3;
                            case WebPackageToken::START_CONTENT->value:
                                $this->state = WebPackageReader_State::CONTENT;
                                $buffer->next()->mark();
                                continue 3;
                            default:
                                throw new ReaderException("Unknown token");
                        }
                    case WebPackageReader_State::SIGNATURE:
                        if ($buffer->valid(7) === false) {
                            break 2;
                        }
                        $buffer->next(7);
                        if ($buffer->equals("WPK\x0D\x0A\x1A\x0A") === true) {
                            $this->state = WebPackageReader_State::START;
                            $this->parser->pushToken("signature");
                            continue 2;
                        }
                        throw new ReaderException("Invalid signature");
                    case WebPackageReader_State::BUILD_DATE:
                        if ($buffer->valid(20) === false) {
                            break 2;
                        }
                        $buffer->next(19);
                        if ($buffer->current() === 0x5A) {
                            $buffer->next();
                            $this->state = WebPackageReader_State::START;
                            $dateString = $buffer->getString();
                            $date = date_create($dateString);
                            if ($date === false) {
                                $dateString = urlencode($dateString);
                                throw new ReaderException("Invalid date: $dateString");
                            }
                            $this->parser->pushToken("build-date", $dateString);
                            continue 2;
                        }
                        $buffer->next();
                        $this->state = WebPackageReader_State::BUILD_DATE_1;
                        continue 2;
                    case WebPackageReader_State::BUILD_DATE_1:
                        if ($buffer->valid(5) === false) {
                            break 2;
                        }
                        $buffer->next(5);
                        $dateString = $buffer->getString();
                        $date = date_create($dateString);
                        if ($date === false) {
                            $dateString = urlencode($dateString);
                            throw new ReaderException("Invalid date: $dateString");
                        }
                        $this->state = WebPackageReader_State::START;
                        $this->parser->pushToken("build-date", $dateString);
                        continue 2;
                    case WebPackageReader_State::CONTENT_ENCODING:
                        switch ($buffer->current()) {
                            case 0x42: // B
                            case 0x62: // b
                                if ($buffer->valid(2) === false) {
                                    break 3;
                                }
                                $buffer->set(2);
                                if ($buffer->equals("br", true) === true) {
                                    $this->state = WebPackageReader_State::START;
                                    $this->parser->pushToken("content-encoding", $buffer->getString());
                                    continue 3;
                                }
                                throw new ReaderException("Invalid content encoding");
                            case 0x43: // C
                            case 0x63: // c
                                if ($buffer->valid(8) === false) {
                                    break 3;
                                }
                                $buffer->set(8);
                                if ($buffer->equals("compress", true) === true) {
                                    $this->state = WebPackageReader_State::START;
                                    $this->parser->pushToken("content-encoding", $buffer->getString());
                                    continue 3;
                                }
                                throw new ReaderException("Invalid content encoding");
                            case 0x44: // D
                            case 0x64: // d
                                if ($buffer->valid(7) === false) {
                                    break 3;
                                }
                                $buffer->set(7);
                                if ($buffer->equals("deflate", true) === true) {
                                    $this->state = WebPackageReader_State::START;
                                    $this->parser->pushToken("content-encoding", $buffer->getString());
                                    continue 3;
                                }
                                throw new ReaderException("Invalid content encoding");
                            case 0x47: // G
                            case 0x67: // g
                                if ($buffer->valid(4) === false) {
                                    break 3;
                                }
                                $buffer->set(4);
                                if ($buffer->equals("gzip", true) === true) {
                                    $this->state = WebPackageReader_State::START;
                                    $this->parser->pushToken("content-encoding", $buffer->getString());
                                    continue 3;
                                }
                            default:
                                throw new ReaderException("Invalid content encoding");
                        }
                    case WebPackageReader_State::FILE_NAME:
                        if ($buffer->current() === 0x2F) { // /
                            $buffer->next();
                            $this->state = WebPackageReader_State::FILE_NAME_1;
                            continue 2;
                        }
                        throw new ReaderException("Invalid file name: file names must start with /");
                    case WebPackageReader_State::FILE_NAME_1:
                        $c = $buffer->current();
                        if ($c === 0x2D || $c >= 0x30 && $c <= 0x39 || $c >= 0x41 && $c <= 0x5A || $c === 0x5F || $c >= 0x61 && $c <= 0x7A) {
                            $buffer->next();
                            $this->state = WebPackageReader_State::FILE_NAME_2;
                            continue 2;
                        }
                        $this->parser->pushToken("file-name", $buffer->getString());
                        $this->state = WebPackageReader_State::START;
                        continue 2;
                    case WebPackageReader_State::FILE_NAME_2:
                        $c = $buffer->current();
                        if ($c === 0x2D || $c >= 0x30 && $c <= 0x39 || $c >= 0x41 && $c <= 0x5A || $c === 0x5F || $c >= 0x61 && $c <= 0x7A) {
                            $buffer->next();
                            $this->state = WebPackageReader_State::FILE_NAME_2;
                            continue 2;
                        }
                        if ($c === 0x2F) { // /
                            $buffer->next();
                            $this->state = WebPackageReader_State::FILE_NAME_1;
                            continue 2;
                        }
                        if ($c === 0x2E) { // .
                            $buffer->next();
                            $this->state = WebPackageReader_State::FILE_NAME_3;
                            continue 2;
                        }
                        $this->parser->pushToken("file-name", $buffer->getString());
                        $this->state = WebPackageReader_State::START;
                        continue 2;
                    case WebPackageReader_State::FILE_NAME_3:
                        $c = $buffer->current();
                        if ($c === 0x2D || $c >= 0x30 && $c <= 0x39 || $c >= 0x41 && $c <= 0x5A || $c === 0x5F || $c >= 0x61 && $c <= 0x7A) {
                            $buffer->next();
                            continue 2;
                        }
                        $this->parser->pushToken("file-name", $buffer->getString());
                        $this->state = WebPackageReader_State::START;
                        continue 2;
                    case WebPackageReader_State::CONTENT_TYPE:
                        if ($buffer->valid(12) === false) {
                            break 2;
                        }
                        $buffer->set(12);
                        if ($buffer->equals("application/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(5);
                        if ($buffer->equals("text/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(6);
                        if ($buffer->equals("image/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(5);
                        if ($buffer->equals("font/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(6);
                        if ($buffer->equals("video/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(6);
                        if ($buffer->equals("audio/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(10);
                        if ($buffer->equals("multipart/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(8);
                        if ($buffer->equals("message/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(6);
                        if ($buffer->equals("model/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        $buffer->set(8);
                        if ($buffer->equals("example/", true) === true) {
                            $this->state = WebPackageReader_State::CONTENT_TYPE_1;
                            continue 2;
                        }
                        throw new ReaderException("Invalid content type");
                    case WebPackageReader_State::CONTENT_TYPE_1:
                        $c = $buffer->current();
                        if ($c === 0x2D || $c === 0x2E || $c >= 0x30 && $c <= 0x39 || $c >= 0x41 && $c <= 0x5A || $c === 0x5F || $c >= 0x61 && $c <= 0x7A) {
                            $buffer->next();
                            $this->state = WebPackageReader_State::CONTENT_TYPE_2;
                            continue 2;
                        }
                        throw new ReaderException("Invalid content type");
                    case WebPackageReader_State::CONTENT_TYPE_2:
                        $c = $buffer->current();
                        if ($c === 0x2D || $c === 0x2E || $c >= 0x30 && $c <= 0x39 || $c >= 0x41 && $c <= 0x5A || $c === 0x5F || $c >= 0x61 && $c <= 0x7A) {
                            $buffer->next();
                            $this->state = WebPackageReader_State::CONTENT_TYPE_2;
                            continue 2;
                        }
                        // continue with next case
                    case WebPackageReader_State::CONTENT_TYPE_3:
                        if ($buffer->current() === 0x3B) { // ;
                            $buffer->next();
                            $this->state = WebPackageReader_State::CONTENT_TYPE_4;
                            continue 2;
                        }
                        $this->state = WebPackageReader_State::START;
                        $this->parser->pushToken("content-type", $buffer->getString());
                        continue 2;
                    case WebPackageReader_State::CONTENT_TYPE_4:
                        if ($buffer->current() === 0x20) {
                            $buffer->next();
                            continue 2;
                        }
                        // continue with next case
                    case WebPackageReader_State::CONTENT_TYPE_5:
                        switch ($buffer->current()) {
                            case 0x21: // !
                            case 0x23: // #
                            case 0x24: // $
                            case 0x26: // &
                            case 0x2B: // +
                            case 0x2D: // -
                            case 0x2E: // .
                            case 0x30:case 0x31:case 0x32:case 0x33:case 0x34:case 0x35:case 0x36:case 0x37:case 0x38:case 0x39: // 0-9
                            case 0x41:case 0x42:case 0x43:case 0x44:case 0x45:case 0x46:case 0x47:case 0x48:case 0x49:case 0x4A: // A-J
                            case 0x4B:case 0x4C:case 0x4D:case 0x4E:case 0x4F:case 0x50:case 0x51:case 0x52:case 0x53:case 0x54: // K-T
                            case 0x55:case 0x56:case 0x57:case 0x58:case 0x59:case 0x5A: // U-Z
                            case 0x5E: // ^
                            case 0x5F: // _
                            case 0x60: // `
                            case 0x61:case 0x62:case 0x63:case 0x64:case 0x65:case 0x66:case 0x67:case 0x68:case 0x69:case 0x6A: // a-j
                            case 0x6B:case 0x6C:case 0x6D:case 0x6E:case 0x6F:case 0x70:case 0x71:case 0x72:case 0x73:case 0x74: // k-t
                            case 0x75:case 0x76:case 0x77:case 0x78:case 0x79:case 0x7A: // u-z
                            case 0x7B: // {
                            case 0x7C: // |
                            case 0x7D: // }
                            case 0x7E: // ~
                                $buffer->next();
                                continue 3;
                            case 0x3D: // =
                                $buffer->next();
                                $this->state = WebPackageReader_State::CONTENT_TYPE_6;
                                continue 3;
                            default:
                                throw new ReaderException("Invalid content type");
                        }
                    case WebPackageReader_State::CONTENT_TYPE_6:
                        if ($buffer->current() === 0x22) { // "
                            $buffer->next();
                            $this->state = WebPackageReader_State::CONTENT_TYPE_8;
                            continue 2;
                        }
                        $this->state = WebPackageReader_State::CONTENT_TYPE_7;
                        // continue with next case
                    case WebPackageReader_State::CONTENT_TYPE_7:
                        switch ($buffer->current()) {
                            // not allowed: ()<>@,;:\"/[]?=
                            case 0x21: // !
                            case 0x23: // #
                            case 0x24: // $
                            case 0x25: // %
                            case 0x26: // &
                            case 0x27: // '
                            case 0x2A: // *
                            case 0x2B: // +
                            case 0x2D: // -
                            case 0x2E: // .
                            case 0x30:case 0x31:case 0x32:case 0x33:case 0x34:case 0x35:case 0x36:case 0x37:case 0x38:case 0x39: // 0-9
                            case 0x41:case 0x42:case 0x43:case 0x44:case 0x45:case 0x46:case 0x47:case 0x48:case 0x49:case 0x4A: // A-J
                            case 0x4B:case 0x4C:case 0x4D:case 0x4E:case 0x4F:case 0x50:case 0x51:case 0x52:case 0x53:case 0x54: // K-T
                            case 0x55:case 0x56:case 0x57:case 0x58:case 0x59:case 0x5A: // U-Z
                            case 0x5E: // ^
                            case 0x5F: // _
                            case 0x60: // `
                            case 0x61:case 0x62:case 0x63:case 0x64:case 0x65:case 0x66:case 0x67:case 0x68:case 0x69:case 0x6A: // a-j
                            case 0x6B:case 0x6C:case 0x6D:case 0x6E:case 0x6F:case 0x70:case 0x71:case 0x72:case 0x73:case 0x74: // k-t
                            case 0x75:case 0x76:case 0x77:case 0x78:case 0x79:case 0x7A: // u-z
                            case 0x7B: // {
                            case 0x7C: // |
                            case 0x7D: // }
                            case 0x7E: // ~
                                $buffer->next();
                                continue 3;
                            case 0x3B: // ;
                                $buffer->next();
                                $this->state = WebPackageReader_State::CONTENT_TYPE_4;
                                continue 3;
                            default:
                                $this->state = WebPackageReader_State::START;
                                $this->parser->pushToken("content-type", $buffer->getString());
                                continue 3;
                        }
                    case WebPackageReader_State::CONTENT_TYPE_8:
                        $c = $buffer->current();
                        if ($c >= 0x23 && $c <= 0x7E || $c === 0x20 || $c === 0x21) {
                            $buffer->next();
                            continue 2;
                        }
                        if ($c === 0x22) {
                            $buffer->next();
                            $this->state = WebPackageReader_State::CONTENT_TYPE_3;
                            continue 2;
                        }
                    case WebPackageReader_State::CONTENT_LENGTH:
                        $this->contentLength = $buffer->current() << 16;
                        $buffer->next();
                        $this->state = WebPackageReader_State::CONTENT_LENGTH_1;
                        continue 2;
                    case WebPackageReader_State::CONTENT_LENGTH_1:
                        $this->contentLength |= $buffer->current() << 8;
                        $buffer->next();
                        $this->state = WebPackageReader_State::CONTENT_LENGTH_2;
                        continue 2;
                    case WebPackageReader_State::CONTENT_LENGTH_2:
                        $this->contentLength |= $buffer->current();
                        $buffer->next();
                        $this->state = WebPackageReader_State::START;
                        $this->parser->pushToken("content-length", $this->contentLength);
                        continue 2;
                    case WebPackageReader_State::CONTENT:
                        if ($buffer->valid($this->contentLength) === false) {
                            break 2;
                        }
                        $buffer->set($this->contentLength);
                        $this->state = WebPackageReader_State::START;
                        $this->parser->pushToken("content", $buffer->getString());
                        continue 2;
                }
            }
            if ($buffer->isLastChunk() === true) {
                $this->parser->endOfTokens();
            } else {
                $this->activity->repeat();
            }
        } catch (ReaderException $e) {
            $this->reset($buffer);
            $this->activity->push($e);
        } catch (Throwable $e) {
            $this->activity
                ->clear()
                ->addError($e)
                ->addClose();
        }
    }
}
