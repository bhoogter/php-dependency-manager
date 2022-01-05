<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class old_versions_test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        dependency_manager(
            [
                __DIR__ . "/resources/dependencies-old-versions.xml",

            ],
            __DIR__ . "/resources/phars"
        );
        // dependency_manager::$log_dump = true;
    }

    public function testLoadDependencyManager(): void
    {
        // print "\n================\nPackages:";
        // print_r(dependency_manager()->packages);
        $this->assertEquals(4, count(dependency_manager()->packages));

    }
}
