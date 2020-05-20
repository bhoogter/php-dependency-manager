<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class logger_test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        dependency_manager(__DIR__ . "/../src/dependencies.xml", __DIR__ . "/resources/phars");
    }

    public function testSetupCorrect(): void
    {
        dependency_manager::$log_dump = false;
        $this->assertFalse(dependency_manager()->trace("TEST TEXT"));
        dependency_manager::$log_dump = true;
        $this->assertTrue(dependency_manager()->info("TEST TEXT"));
        dependency_manager::$log_dump = false;
    }
}
