<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class local_filename_test extends TestCase
{
    private static $dm;

    public static function setUpBeforeClass(): void
    {
        self::$dm = dependency_manager(
            __DIR__ . "/../src/dependencies.xml", 
            [''=>__DIR__ . "/resources/phars", 'other' => __DIR__ . "/resources/other" ]
        );
        // self::$dm::$log_dump = true;
    }

    private function fix($s) {
        return str_replace("\\", DIRECTORY_SEPARATOR, $s);
    }

    public function testLocalFilename(): void
    {
        $this->assertEquals(
            $this->fix('\tests\resources\phars\testgroup-testname-1-1-1.phar'),
            substr(self::$dm->local_file_name("testgroup", "testname", "1.1.1", "phar", null), -52)
        );
        $this->assertEquals(
            $this->fix('\tests\resources\phars\testgroup-testname-1-1-1.phar'),
            substr(self::$dm->local_file_name("testgroup", "testname", "1.1.1", "phar", null, 'dne'), -52)
        );
        $this->assertEquals(
            $this->fix('\tests\resources\other\testgroup-testname-1-1-1.phar'),
            substr(self::$dm->local_file_name("testgroup", "testname", "1.1.1", "phar", null, 'other'), -52)
        );
    }
}
