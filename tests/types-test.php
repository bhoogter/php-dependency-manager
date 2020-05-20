<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class types_test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // dependency_manager::$log_dump = true;
        dependency_manager(__DIR__ . "/resources/dependencies-types.xml", __DIR__ . "/resources/phars");
    }

    public function testSetupCorrect(): void
    {
        $this->assertNotNull(dependency_manager());
    }
}
