<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

function dependency_manager_workspace() { return __DIR__ . "/resources/work"; }
function dependency_manager_source() { return __DIR__ . "/resources/dependencies-test-phars.xml";}
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
        dependency_manager(true);

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
