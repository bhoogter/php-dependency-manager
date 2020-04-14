<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class dependency_manager_test extends TestCase
{
    const SCOPE = "dependency_manager_test";

    public static function clearObjects()
    {
        foreach (glob(__DIR__ . "/resources/work/*") as $f) {
            if ($f == __DIR__ . "resources/work/.gitkeep") continue;
            unlink($f);
        }
    }
    public static function setUpBeforeClass(): void
    {
        dependency_manager(dependency_manager_test::SCOPE, __DIR__ . "/resources/dependencies.xml", __DIR__ . "/resources/work");
    }

    public function testSetupCorrect(): void
    {
        $this->assertEquals(1, 1);
    }

    public function testLoadDependencyManager(): void
    {
        $obj = dependency_manager(dependency_manager_test::SCOPE);
        $this->assertNotNull($obj);
        $this->assertEquals(1, sizeof($obj->dependencies));
        $this->assertEquals(8, sizeof($obj->resources));
    }

    public function testDefaultSource()
    {
        $result = dependency_manager(dependency_manager_test::SCOPE) -> default_source();
        $this->assertTrue(strpos($result, "dependencies.xml") !== false);
    }
}
