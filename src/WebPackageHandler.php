<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\lalr1\ParserException;
use davekok\stream\ReaderException;

interface WebPackageHandler
{
    public function handleWebPackage(WebPackage|ParserException|ReaderException $value): void;
}
