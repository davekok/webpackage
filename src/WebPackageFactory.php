<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\parser\Parser;
use davekok\parser\RulesBag;

class WebPackageFactory
{
    public function __construct(public readonly RulesBag $rulesBag) {}

    public function createReader(): WebPackageReader
    {
        return new WebPackageReader(new Parser($this->rulesBag, new WebPackageRules));
    }

    public function createWriter(WebPackage $webPackage): WebPackageWriter
    {
        return new WebPackageWriter($webPackage);
    }
}
