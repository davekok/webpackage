<?php

declare(strict_types=1);

namespace davekok\webpackage\tests;

use DateTime;
use davekok\parser\ParserException;
use davekok\kernel\Activity;
use davekok\kernel\ReaderException;
use davekok\kernel\ReadeBuffer;
use davekok\kernel\WriteBuffer;
use davekok\webpackage\File;
use davekok\webpackage\WebPackage;
use davekok\webpackage\WebPackageFactory;
use davekok\webpackage\WebPackageFormatter;
use davekok\webpackage\WebPackageHandler;
use davekok\webpackage\WebPackageReader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \davekok\webpackage\WebPackageFormatter::format
 * @covers \davekok\webpackage\WebPackageFormatter::formatBuildDate
 * @covers \davekok\webpackage\WebPackageFormatter::formatContentEncoding
 * @covers \davekok\webpackage\WebPackageFormatter::formatContentLength
 * @covers \davekok\webpackage\WebPackageFormatter::formatContentType
 * @covers \davekok\webpackage\WebPackageFormatter::formatEndOfFiles
 * @covers \davekok\webpackage\WebPackageFormatter::formatFileName
 * @covers \davekok\webpackage\WebPackageFormatter::formatSignature
 * @covers \davekok\webpackage\WebPackageFormatter::formatStartContent
 * @covers \davekok\webpackage\WebPackageFormatter::length
 * @covers \davekok\webpackage\WebPackageFormatter::lengthBuildDate
 * @covers \davekok\webpackage\WebPackageFormatter::lengthContentEncoding
 * @covers \davekok\webpackage\WebPackageFormatter::lengthContentLength
 * @covers \davekok\webpackage\WebPackageFormatter::lengthContentType
 * @covers \davekok\webpackage\WebPackageFormatter::lengthEndOfFiles
 * @covers \davekok\webpackage\WebPackageFormatter::lengthFileName
 * @covers \davekok\webpackage\WebPackageFormatter::lengthSignature
 * @covers \davekok\webpackage\WebPackageFormatter::lengthStartContent
 * @covers \davekok\webpackage\WebPackageReader::__construct
 * @covers \davekok\webpackage\WebPackageWriter::__construct
 * @uses \davekok\webpackage\File
 * @uses \davekok\webpackage\WebPackage
 * @uses \davekok\webpackage\WebPackageFactory
 * @uses \davekok\webpackage\WebPackageRules
 */
class WebPackageIOTest extends TestCase
{
    /**
     * @covers \davekok\webpackage\WebPackageReader::receive
     */
    public function testReceive(): void
    {
        $activity = $this->createMock(Activity::class);
        $reader = (new WebPackageFactory)->createReader($activity);
        $activity->expects(static::once())->method('read')->with($reader, $this->handleWebPackage(...))->willReturn($activity);
        $reader->receive($this);
    }

    public function handleWebPackage(WebPackage|Throwable $value): void
    {
    }

    public function webPackageProvider(): array
    {
        return [
            [
                new WebPackage(
                    buildDate: new DateTime("2021-11-19T16:41:23Z"),
                    contentEncoding: "br",
                    files: [
                        "/" => new File(
                            fileName:      "/",
                            contentType:   "text/html; charset=us-ascii",
                            contentLength: 28,
                            content:       "<!doctype html><html></html>"
                        ),
                        "/reset.css" => new File(
                            fileName:      "/reset.css",
                            contentType:   "text/css; charset=us-ascii; prop=value",
                            contentLength: 35,
                            content:       "*{margin:0;paddding:0;border:none;}"
                        )
                    ],
                    length: 1 + 7    // signature
                        + 1 + 20     // datetime
                        + 1 + 2      // content encoding
                            + 1 + 1  // file name
                            + 1 + 27 // content type
                            + 1 + 3  // content length
                            + 1 + 28 // content
                            + 1 + 10 // file name
                            + 1 + 38 // content type
                            + 1 + 3  // content length
                            + 1 + 35 // content
                        + 1          // end of files
                )
            ],
            [
                new WebPackage(
                    buildDate: new DateTime("2021-11-19T16:41:23+02:00"),
                    contentEncoding: "br",
                    files: [
                        "/" => new File(
                            fileName:      "/",
                            contentType:   "text/html; charset=\"us-ascii\"",
                            contentLength: 28,
                            content:       "<!doctype html><html></html>"
                        ),
                        "/data/" => new File(
                            fileName:      "/data/",
                            contentType:   "application/json",
                            contentLength: 16,
                            content:       '{"prop":"value"}'
                        ),
                        "/favicon" => new File(
                            fileName:      "/favicon",
                            contentType:   "image/png",
                            contentLength: 2,
                            content:       "\x00\x00"
                        ),
                    ],
                    length: 1 + 7    // signature
                        + 1 + 25     // datetime
                        + 1 + 2      // content encoding
                            + 1 + 1  // file name
                            + 1 + 29 // content type
                            + 1 + 3  // content length
                            + 1 + 28 // content
                            + 1 + 6  // file name
                            + 1 + 16 // content type
                            + 1 + 3  // content length
                            + 1 + 16 // content
                            + 1 + 8  // file name
                            + 1 + 9  // content type
                            + 1 + 3  // content length
                            + 1 + 2  // content
                        + 1          // end of files
                )
            ],
            [
                new WebPackage(
                    buildDate: new DateTime("2021-11-19T16:41:23+02:00"),
                    contentEncoding: "br",
                    length: 1 + 7 // signature
                        + 1 + 25  // datetime
                        + 1 + 2   // content encoding
                        + 1       // end of files
                )
            ],
            [
                new WebPackage(
                    buildDate: new DateTime("2021-11-19T16:41:23+02:00"),
                    contentEncoding: "compress",
                    length: 1 + 7 // signature
                        + 1 + 25  // datetime
                        + 1 + 8   // content encoding
                        + 1       // end of files
                )
            ],
            [
                new WebPackage(
                    buildDate: new DateTime("2021-11-19T16:41:23+02:00"),
                    contentEncoding: "deflate",
                    length: 1 + 7 // signature
                        + 1 + 25  // datetime
                        + 1 + 7   // content encoding
                        + 1       // end of files
                )
            ],
            [
                new WebPackage(
                    buildDate: new DateTime("2021-11-19T16:41:23+02:00"),
                    contentEncoding: "gzip",
                    length: 1 + 7 // signature
                        + 1 + 25  // datetime
                        + 1 + 4   // content encoding
                        + 1       // end of files
                )
            ],
            [
                new WebPackage(
                    buildDate: new DateTime("2021-11-19T16:41:23+02:00"),
                    length: 1 + 7 // signature
                        + 1 + 25  // datetime
                        + 1       // end of files
                )
            ],
        ];
    }

    /**
     * @dataProvider webPackageProvider
     * @covers \davekok\webpackage\WebPackageReader::read
     */
    public function testRead(WebPackage $webpackage): void
    {
        $activity = $this->createMock(Activity::class);
        $reader   = (new WebPackageFactory)->createReader();
        $content  = (new WebPackageFormatter)->format($webpackage);
        $third    = intdiv(strlen($content), 3);
        $buffer   = new StreamKernelReaderBuffer();

        $activity->expects(static::exactly(2))->method('repeat')->willReturn($activity);
        $activity->expects(static::once())->method('push')->with($webpackage)->willReturn($activity);
        $reader->read($buffer->add(substr($content, 0, $third)));
        $reader->read($buffer->add(substr($content, $third, $third)));
        $reader->read($buffer->add(substr($content, $third*2))->end());
    }

    /**
     * @dataProvider webPackageProvider
     * @covers \davekok\webpackage\WebPackageWriter::send
     * @covers \davekok\webpackage\WebPackageWriter::write
     */
    public function testWrite(WebPackage $webpackage): void
    {
        $formatter = new WebPackageFormatter;
        $activity  = $this->createMock(Activity::class);
        $writer    = (new WebPackageFactory)->createWriter($activity);
        $buffer    = $this->createMock(WriterBuffer::class);

        $activity->expects(static::once())
            ->method('addWrite')
            ->with($writer)
            ->willReturn($activity);

        $validCalls = [
            [strlen($formatter->formatSignature())],
            [strlen($formatter->formatBuildDate($webpackage->buildDate))],
            [strlen($formatter->formatContentEncoding($webpackage->contentEncoding))],
            ...array_reduce(
                $webpackage->files,
                fn(array $prev, File $file) => [
                    ...$prev,
                    [strlen($formatter->formatFileName($file->fileName))],
                    [strlen($formatter->formatContentType($file->contentType))],
                    [strlen($formatter->formatContentLength($file->contentLength))],
                    [strlen($formatter->formatStartContent())],
                ],
                []
            ),
            [strlen($formatter->formatEndOfFiles())],
        ];
        $buffer->expects(static::exactly(count($validCalls)))
            ->method('valid')
            ->withConsecutive(...$validCalls)
            ->willReturn(true);

        $addCalls = [
            [$formatter->formatSignature()],
            [$formatter->formatBuildDate($webpackage->buildDate)],
            [$formatter->formatContentEncoding($webpackage->contentEncoding)],
            ...array_reduce(
                $webpackage->files,
                fn(array $prev, File $file) => [
                    ...$prev,
                    [$formatter->formatFileName($file->fileName)],
                    [$formatter->formatContentType($file->contentType)],
                    [$formatter->formatContentLength($file->contentLength)],
                    [$formatter->formatStartContent()],
                ],
                []
            ),
            [$formatter->formatEndOfFiles()],
        ];
        $buffer->expects(static::exactly(count($addCalls)))
            ->method('add')
            ->withConsecutive(...$addCalls)
            ->willReturn($buffer);

        $writer->send($webpackage);
        $writer->write($buffer);
    }
}
