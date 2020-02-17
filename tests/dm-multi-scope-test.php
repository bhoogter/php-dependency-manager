<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class dm_multi_scope extends TestCase
{

    public static function setUpBeforeClass(): void 
    {
        dependency_manager(null, __DIR__ . "/resources/dependencies.xml", __DIR__ . "/resources/work");
        dependency_manager("alt-scope", __DIR__ . "/resources/dependencies-test-phars.xml", __DIR__ . "/resources/work");
    }

    public function testLoadDependencyManager(): void
    {
        $dm1 = dependency_manager();
        $dm2 = dependency_manager("alt-scope");

        $this->assertEquals(1, sizeof($dm1->dependencies));
// print_r($dm2->dependencies);
        $this->assertEquals(3, sizeof($dm2->dependencies));
    }
}
