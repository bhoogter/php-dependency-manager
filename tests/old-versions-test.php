<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class old_version_phars extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        dependency_manager(
            [
                __DIR__ . "/resources/dependencies-old-versions.xml",

            ],
            __DIR__ . "/resources/phars"
        );
    }

    public function setUp(): void
    {
        dependency_manager::$log_dump = true;
    }

    public function testLoadDependencyManager(): void
    {
        print_r(dependency_manager()->packages);
        $this->assertEquals(4, count(dependency_manager()->packages));
    }
}
