<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . "/../src/class-dependency-manager.php");

final class dependency_manager_test extends TestCase
{

    public static function clearObjects()
    {
        foreach (glob(__DIR__ . "/resources/work/*") as $f) {
            if ($f == __DIR__ . "resources/work/.gitkeep") continue;
            unlink($f);
        }
    }
    public static function setUpBeforeClass(): void
    {
        dependency_manager("dependency_manager_test", __DIR__ . "/resources/dependencies.xml", __DIR__ . "/resources/work");
    }

    public function testSetupCorrect(): void
    {
        $this->assertEquals(1, 1);
    }
    public function testLoadDependencyManager(): void
    {
        $obj = dependency_manager("dependency_manager_test");
        $this->assertNotNull($obj);
        $this->assertEquals(1, sizeof($obj->dependencies));
        $this->assertEquals(4, sizeof($obj->resources));
    }
}
