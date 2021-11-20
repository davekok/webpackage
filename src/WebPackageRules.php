<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\lalr1\attributes\{Rule,Solution,Symbol,Symbols};
use davekok\lalr1\{Parser,ParserException,SymbolType,Token};
use davekok\stream\{Activity,Url};
use Throwable;

#[Symbols(
    new Symbol(SymbolType::ROOT, "webpackage"),
    new Symbol(SymbolType::BRANCH, "files"),
    new Symbol(SymbolType::BRANCH, "file"),
    new Symbol(SymbolType::LEAF, "signature"),
    new Symbol(SymbolType::LEAF, "build-date"),
    new Symbol(SymbolType::LEAF, "content-encoding"),
    new Symbol(SymbolType::LEAF, "file-name"),
    new Symbol(SymbolType::LEAF, "content-type"),
    new Symbol(SymbolType::LEAF, "content-length"),
    new Symbol(SymbolType::LEAF, "content"),
    new Symbol(SymbolType::LEAF, "end-of-files"),
)]
class WebPackageRules
{
    public function __construct(
        private Parser $parser,
        private Activity $activity,
    ) {}

    #[Solution]
    public function solution(WebPackage|ParserException $value): void
    {
        $this->activity->push($value);
    }

    #[Rule("signature build-date files end-of-files")]
    public function createWebPackageWithoutContentEncoding(array $tokens): Token
    {
        return $this->parser->createToken("webpackage", new WebPackage(
            buildDate: $tokens[1]->value,
            files:     $tokens[2]->value,
        ));
    }

    #[Rule("signature build-date content-encoding files end-of-files")]
    public function createWebPackageWithContentEncoding(array $tokens): Token
    {
        return $this->parser->createToken("webpackage", new WebPackage(
            buildDate:       $tokens[1]->value,
            contentEncoding: $tokens[2]->value,
            files:           $tokens[3]->value,
        ));
    }

    #[Rule("signature build-date content-encoding end-of-files")]
    public function createWebPackageWithContentEncodingNoFiles(array $tokens): Token
    {
        return $this->parser->createToken("webpackage", new WebPackage(
            buildDate:       $tokens[1]->value,
            contentEncoding: $tokens[2]->value,
        ));
    }

    #[Rule("signature build-date end-of-files")]
    public function createWebPackageEmpty(array $tokens): Token
    {
        return $this->parser->createToken("webpackage", new WebPackage(
            buildDate: $tokens[1]->value,
        ));
    }

    #[Rule("file")]
    public function startFiles(array $tokens): Token
    {
        return $this->parser->createToken("files", [$tokens[0]->value->fileName => $tokens[0]->value]);
    }

    #[Rule("files file")]
    public function addFile(array $tokens): Token
    {
        $tokens[0]->value[$tokens[1]->value->fileName] = $tokens[1]->value;
        return $tokens[0];
    }

    #[Rule("file-name content-type content-length content")]
    public function createFile(array $tokens): Token
    {
        return $this->parser->createToken("file", new File(
            fileName:      $tokens[0]->value,
            contentType:   $tokens[1]->value,
            contentLength: $tokens[2]->value,
            content:       $tokens[3]->value,
        ));
    }
}
