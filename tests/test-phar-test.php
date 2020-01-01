<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

require(__DIR__ . "/../src/class-dependency-manager.php");

final class test_phar_test extends TestCase
{

    public function clearObjects()
    {
        foreach (glob(__DIR__ . "/resources/work/*") as $f) {
            if ($f == __DIR__ . "resources/work/.gitkeep") continue;
            unlink($f);
        }
    }

    public static function setUpBeforeClass(): void 
    {
        dependency_manager("test_phar_test", __DIR__ . "/resources/dependencies-test-phars.xml", __DIR__ . "/resources/work");
    }

    public function setUp(): void
    {
        $this->clearObjects();
    }

    public function testLoadDependencyManager(): void
    {
        $obj_a = new test_phar_a();
        $obj_aa = new test_phar_aa();
        $this->assertNotNull($obj_a);
        $this->assertNotNull($obj_aa);

        $obj_b = new test_phar_b();
        $obj_bb = new test_phar_bb();
        $this->assertNotNull($obj_b);
        $this->assertNotNull($obj_bb);

        $obj_c = new test_phar_c();
        $obj_cc = new test_phar_cc();
        $this->assertNotNull($obj_c);
        $this->assertNotNull($obj_cc);
    }
}
