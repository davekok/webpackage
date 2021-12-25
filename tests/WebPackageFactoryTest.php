<?php

declare(strict_types=1);

namespace davekok\webpackage\tests;

use davekok\parser\Parser;
use davekok\kernel\Activity;
use davekok\webpackage\WebPackageFactory;
use davekok\webpackage\WebPackageRules;
use davekok\webpackage\WebPackageReader;
use davekok\webpackage\WebPackageWriter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @coversDefaultClass \davekok\webpackage\WebPackageFactory
 * @covers ::__construct
 * @uses \davekok\webpackage\WebPackageRules
 * @uses \davekok\webpackage\WebPackageReader
 * @uses \davekok\webpackage\WebPackageWriter
 */
class WebPackageFactoryTest extends TestCase
{
    public function testConstruct(): void
    {
        $this->assertEquals((new WebPackageRulesTest)->rules, (new WebPackageFactory)->rules);
    }

    /**
     * @covers ::createReader
     * @covers ::__construct
     */
    public function testCreateReader(): void
    {
        $activity = $this->createMock(Activity::class);
        $parser = new Parser((new WebPackageRulesTest)->rules);
        $parser->setRulesObject(new WebPackageRules($parser, $activity));
        $this->assertEquals(
            new WebPackageReader($parser, $activity),
            (new WebPackageFactory())->createReader($activity)
        );
    }

    /**
     * @covers ::createWriter
     * @covers ::__construct
     */
    public function testCreateWriter(): void
    {
        $activity = $this->createMock(Activity::class);
        $this->assertEquals(
            new WebPackageWriter($activity),
            (new WebPackageFactory())->createWriter($activity)
        );
    }
}
