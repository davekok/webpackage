<?php

declare(strict_types=1);

namespace davekok\webpackage\tests;

use DateTime;
use davekok\lalr1\Parser;
use davekok\lalr1\Rule;
use davekok\lalr1\Rules;
use davekok\lalr1\RulesFactory;
use davekok\lalr1\Symbol;
use davekok\lalr1\SymbolType;
use davekok\lalr1\Token;
use davekok\stream\Activity;
use davekok\webpackage\File;
use davekok\webpackage\WebPackage;
use davekok\webpackage\WebPackageRules;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @coversDefaultClass \davekok\webpackage\WebPackageRules
 * @covers ::__construct
 * @covers \davekok\webpackage\File::__construct
 * @covers \davekok\webpackage\WebPackage::__construct
 */
class WebPackageRulesTest extends TestCase
{
    public function __construct(
        public readonly Rules $rules = new Rules(
            symbols: [
                "webpackage"       => new Symbol(type: SymbolType::ROOT,   key: "\x00", name: "webpackage",       precedence: 0),
                "files"            => new Symbol(type: SymbolType::BRANCH, key: "\x01", name: "files",            precedence: 0),
                "file"             => new Symbol(type: SymbolType::BRANCH, key: "\x02", name: "file",             precedence: 0),
                "signature"        => new Symbol(type: SymbolType::LEAF,   key: "\x03", name: "signature",        precedence: 0),
                "build-date"       => new Symbol(type: SymbolType::LEAF,   key: "\x04", name: "build-date",       precedence: 0),
                "content-encoding" => new Symbol(type: SymbolType::LEAF,   key: "\x05", name: "content-encoding", precedence: 0),
                "file-name"        => new Symbol(type: SymbolType::LEAF,   key: "\x06", name: "file-name",        precedence: 0),
                "content-type"     => new Symbol(type: SymbolType::LEAF,   key: "\x07", name: "content-type",     precedence: 0),
                "content-length"   => new Symbol(type: SymbolType::LEAF,   key: "\x08", name: "content-length",   precedence: 0),
                "content"          => new Symbol(type: SymbolType::LEAF,   key: "\x09", name: "content",          precedence: 0),
                "end-of-files"     => new Symbol(type: SymbolType::LEAF,   key: "\x0A", name: "end-of-files",     precedence: 0),
            ],
            rules: [
                "\x03\x04\x01\x0a" => new Rule(
                    key: "\x03\x04\x01\x0a",
                    text: 'signature build-date files end-of-files',
                    precedence: 0,
                    reduceMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'createWebPackageWithoutContentEncoding'),
                ),
                "\x03\x04\x05\x01\x0a" => new Rule(
                    key: "\x03\x04\x05\x01\x0a",
                    text: 'signature build-date content-encoding files end-of-files',
                    precedence: 0,
                    reduceMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'createWebPackageWithContentEncoding'),
                ),
                "\x03\x04\x05\x0a" => new Rule(
                    key: "\x03\x04\x05\x0a",
                    text: 'signature build-date content-encoding end-of-files',
                    precedence: 0,
                    reduceMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'createWebPackageWithContentEncodingNoFiles'),
                ),
                "\x03\x04\x0a" => new Rule(
                    key: "\x03\x04\x0a",
                    text: 'signature build-date end-of-files',
                    precedence: 0,
                    reduceMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'createWebPackageEmpty'),
                ),
                "\x02" => new Rule(
                    key: "\x02",
                    text: 'file',
                    precedence: 0,
                    reduceMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'startFiles'),
                ),
                "\x01\x02" => new Rule(
                    key: "\x01\x02",
                    text: 'files file',
                    precedence: 0,
                    reduceMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'addFile'),
                ),
                "\x06\x07\x08\x09" => new Rule(
                    key: "\x06\x07\x08\x09",
                    text: 'file-name content-type content-length content',
                    precedence: 0,
                    reduceMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'createFile'),
                ),
            ],
            solutionMethod: new ReflectionMethod('davekok\webpackage\WebPackageRules', 'solution'),
        )
    ) {
        parent::__construct();
    }

    public function testRules(): void
    {
        $this->assertEquals(
            $this->rules,
            (new RulesFactory)->createRules(new ReflectionClass(WebPackageRules::class)),
        );
    }

    /**
     * @covers ::solution
     */
    public function testSolution(): void
    {
        $value    = $this->createMock(WebPackage::class);
        $parser   = $this->createMock(Parser::class);
        $activity = $this->createMock(Activity::class);
        $activity->expects(static::once())->method('push')->with($value);
        (new WebPackageRules($parser, $activity))->solution($value);
    }

    /**
     * @covers ::createWebPackageWithoutContentEncoding
     */
    public function testCreateWebPackageWithoutContentEncoding(): void
    {
        $buildDate  = new DateTime();
        $files      = [];
        $webpackage = new WebPackage(buildDate: $buildDate, files: $files);
        $parser     = $this->createMock(Parser::class);
        $activity   = $this->createMock(Activity::class);
        $tokens     = [
            new Token(symbol: $this->rules->getSymbol("signature"),    value: null),
            new Token(symbol: $this->rules->getSymbol("build-date"),   value: $buildDate),
            new Token(symbol: $this->rules->getSymbol("files"),        value: $files),
            new Token(symbol: $this->rules->getSymbol("end-of-files"), value: null),
        ];
        $parser->expects(static::once())
            ->method('createToken')
            ->with("webpackage", $webpackage)
            ->willReturn(new Token(symbol: $this->rules->getSymbol("webpackage"), value: $webpackage));
        (new WebPackageRules($parser, $activity))->createWebPackageWithoutContentEncoding($tokens);
    }

    /**
     * @covers ::createWebPackageWithContentEncoding
     */
    public function testCreateWebPackageWithContentEncoding(): void
    {
        $buildDate       = new DateTime();
        $contentEncoding = "br";
        $files           = [];
        $webpackage      = new WebPackage(buildDate: $buildDate, contentEncoding: $contentEncoding, files: $files);
        $parser          = $this->createMock(Parser::class);
        $activity        = $this->createMock(Activity::class);
        $tokens          = [
            new Token(symbol: $this->rules->getSymbol("signature"),        value: null),
            new Token(symbol: $this->rules->getSymbol("build-date"),       value: $buildDate),
            new Token(symbol: $this->rules->getSymbol("content-encoding"), value: $contentEncoding),
            new Token(symbol: $this->rules->getSymbol("files"),            value: $files),
            new Token(symbol: $this->rules->getSymbol("end-of-files"),     value: null),
        ];
        $parser->expects(static::once())
            ->method('createToken')
            ->with("webpackage", $webpackage)
            ->willReturn(new Token(symbol: $this->rules->getSymbol("webpackage"), value: $webpackage));
        (new WebPackageRules($parser, $activity))->createWebPackageWithContentEncoding($tokens);
    }

    /**
     * @covers ::startFiles
     */
    public function testStartFiles(): void
    {
        $file            = new File(
            fileName:      "/",
            contentType:   "text/html; charset=us-ascii",
            contentLength: 30,
            content:       "<!doctype html>\n<html></html>\n",
        );
        $files           = ["/" => $file];
        $parser          = $this->createMock(Parser::class);
        $activity        = $this->createMock(Activity::class);
        $tokens          = [
            new Token(symbol: $this->rules->getSymbol("file"), value: $file),
        ];
        $parser->expects(static::once())
            ->method('createToken')
            ->with("files", $files)
            ->willReturn(new Token(symbol: $this->rules->getSymbol("files"), value: $files));
        (new WebPackageRules($parser, $activity))->startFiles($tokens);
    }

    /**
     * @covers ::addFile
     */
    public function testAddFile(): void
    {
        $file1           = new File(
            fileName:      "/",
            contentType:   "text/html; charset=us-ascii",
            contentLength: 30,
            content:       "<!doctype html>\n<html></html>\n",
        );
        $file2           = new File(
            fileName:      "/reset.css",
            contentType:   "text/css; charset=us-ascii",
            contentLength: 34,
            content:       "*{margin:0;padding:0;border:none;}",
        );
        $files           = ["/" => $file1, "/reset.css" => $file2];
        $parser          = $this->createMock(Parser::class);
        $activity        = $this->createMock(Activity::class);
        $tokens          = [
            new Token(symbol: $this->rules->getSymbol("files"), value: ["/" => $file1]),
            new Token(symbol: $this->rules->getSymbol("file"), value: $file2),
        ];
        $this->assertEquals(
            new Token(symbol: $this->rules->getSymbol("files"), value: $files),
            (new WebPackageRules($parser, $activity))->addFile($tokens),
        );
    }

    /**
     * @covers ::createFile
     */
    public function testCreateFile(): void
    {
        $fileName        = "/";
        $contentType     = "text/html; charset=us-ascii";
        $contentLength   = 30;
        $content         = "<!doctype html>\n<html></html>\n";
        $file            = new File(
            fileName:      $fileName,
            contentType:   $contentType,
            contentLength: $contentLength,
            content:       $content,
        );
        $parser          = $this->createMock(Parser::class);
        $activity        = $this->createMock(Activity::class);
        $tokens          = [
            new Token(symbol: $this->rules->getSymbol("file-name"),      value: $fileName),
            new Token(symbol: $this->rules->getSymbol("content-type"),   value: $contentType),
            new Token(symbol: $this->rules->getSymbol("content-length"), value: $contentLength),
            new Token(symbol: $this->rules->getSymbol("content"),        value: $content),
        ];
        $parser->expects(static::once())
            ->method('createToken')
            ->with("file", $file)
            ->willReturn(new Token(symbol: $this->rules->getSymbol("file"), value: $file));
        (new WebPackageRules($parser, $activity))->createFile($tokens);
    }
}
