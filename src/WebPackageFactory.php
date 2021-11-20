<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\lalr1\Parser;
use davekok\lalr1\Rules;
use davekok\lalr1\RulesFactory;
use davekok\stream\Activity;
use ReflectionClass;

class WebPackageFactory
{
    public readonly Rules $rules;

    public function __construct(RulesFactory $rulesFactory = new RulesFactory())
    {
        $this->rules = $rulesFactory->createRules(new ReflectionClass(WebPackageRules::class));
    }

    public function createReader(Activity $activity): WebPackageReader
    {
        $parser = new Parser($this->rules);
        $parser->setRulesObject(new WebPackageRules($parser, $activity));
        return new WebPackageReader($parser, $activity);
    }

    public function createWriter(Activity $activity): WebPackageWriter
    {
        return new WebPackageWriter($activity);
    }
}
