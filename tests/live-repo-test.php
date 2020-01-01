<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

function dependency_manager_workspace() { return __DIR__ . "/resources/work"; }
function dependency_manager_source() { return __DIR__ . "/resources/dependencies-live.xml";}
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

        $obj = new xml_file();
        $obj2 = new aggregator();
    }
}
