<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require(__DIR__ . "/../src/class-dependency-manager.php");

final class dependency_manager_test extends TestCase
{

    public function clearObjects()
    {
        foreach (glob(__DIR__ . "/resourceswork/*") as $f) {
            if ($f == __DIR__ . "resources/work/.gitkeep") continue;
            unlink($f);
        }
    }

    public function setUp(): void
    {
        $this->clearObjects();
    }

    public function testLoadDependencyManager(): void
    {
        $obj = new dependency_manager(__DIR__ . "/resources/dependencies.xml", __DIR__ . "/resources/work");
        $this->assertNotNull($obj);
        $this->assertEquals(1, sizeof($obj->dependencies));
        $this->assertEquals(4, sizeof($obj->resources));
    }
}
