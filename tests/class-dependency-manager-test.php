<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class dependency_manager_test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        dependency_manager(__DIR__ . "/../src/dependencies.xml", __DIR__ . "/resources/phars");
    }

    public static function clearObjects()
    {
        foreach (glob(__DIR__ . "/resources/work/*") as $f) {
            if ($f == __DIR__ . "resources/work/.gitkeep") continue;
            unlink($f);
        }
    }

    public function testSetupCorrect(): void
    {
        $this->assertTrue(true);
    }

    public function testLoadDependencyManager(): void
    {
        $obj = dependency_manager();
        $this->assertNotNull($obj);
        $this->assertEquals(1, sizeof($obj->dependencies));
        $this->assertEquals(8, sizeof($obj->resources));
    }

    public function testDefaultSource()
    {
        $result = dependency_manager()->default_source();
        $this->assertTrue(strpos($result, "dependencies.xml") !== false);
    }
}
