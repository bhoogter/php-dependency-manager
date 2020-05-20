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
        php_logger::$suppress_output = true;
        $this->assertTrue(php_logger::error("TEST TEXT"));
        php_logger::clear_log_levels('none');
        $this->assertFalse(php_logger::error("TEST TEXT"));
    }
}
