<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require(__DIR__ . "/../src/class-dependency-manager.php");

final class dependency_manager_test extends TestCase
{

    public function testLoadDependencyManager(): void
    {
        $obj = new dependency_manager(__DIR__ . "/resources/dependencies.xml", __DIR__ . "/resources/work");
        $this->assertNotNull($obj);
print_r($obj->dependencies);
        $this->assertEquals(1, sizeof($obj->dependencies));
print_r($obj->resources);
        $this->assertEquals(1, sizeof($obj->resources));
    }

}