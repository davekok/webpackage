<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\parser\attributes\{Rule,Symbol,Symbols};
use davekok\parser\{Parser,ParserException,Rules,SymbolType,Token};
use Throwable;

#[Symbols(
    new Symbol(SymbolType::ROOT,   "webpackage"),
    new Symbol(SymbolType::BRANCH, "files"),
    new Symbol(SymbolType::BRANCH, "files"),
    new Symbol(SymbolType::BRANCH, "file"),
    new Symbol(SymbolType::LEAF,   "signature"),
    new Symbol(SymbolType::LEAF,   "domain"),
    new Symbol(SymbolType::LEAF,   "build-date"),
    new Symbol(SymbolType::LEAF,   "certificate"),
    new Symbol(SymbolType::LEAF,   "content-encoding"),
    new Symbol(SymbolType::LEAF,   "file-name"),
    new Symbol(SymbolType::LEAF,   "content-type"),
    new Symbol(SymbolType::LEAF,   "content-length"),
    new Symbol(SymbolType::LEAF,   "content"),
    new Symbol(SymbolType::LEAF,   "end-of-files"),
)]
class WebPackageRules implements Rules
{
    private readonly Parser $parser;

    public function setParser(Parser $parser): void
    {
        $this->parser = $parser;
    }

    #[Rule("signature")]
    public function start(array $tokens): Token
    {
        return $this->parser->createToken("webpackage", [
            "signature"  => $tokens[0]->value,
            "domain"     => $tokens[1]->value,
            "build-date" => $tokens[1]->value,
        ]);
    }

    #[Rule("webpackage certificate")]
    public function addCertificate(array $tokens): Token
    {
        $tokens[0]->value["certificate"] = $tokens[1]->value;
        return $tokens[0];
    }

    #[Rule("webpackage content-encoding")]
    public function addContentEncoding(array $tokens): Token
    {
        $tokens[0]->value["content-encoding"] = $tokens[1]->value;
        return $tokens[0];
    }

    #[Rule("webpackage files end-of-files")]
    public function addFiles(array $tokens): Token
    {
        $tokens[0]->value["files"] = $tokens[1]->value;
        return $tokens[0];
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

    #[Rule("file-name content-type content-hash content-length content")]
    public function createFile(array $tokens): Token
    {
        return $this->parser->createToken("file", new File(
            fileName:      $tokens[0]->value,
            contentType:   $tokens[1]->value,
            contentHash:   $tokens[2]->value,
            contentLength: $tokens[3]->value,
            content:       $tokens[4]->value,
        ));
    }
}
