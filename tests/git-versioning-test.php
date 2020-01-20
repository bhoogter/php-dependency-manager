<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class git_versioning_test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        dependency_manager_versioning::$testResponse =
            file_get_contents(__DIR__ . "/resources/xml-file-releases.json");
	dependency_manager_versioning::$gitAuth = 
	    @file_get_contents(__DIR__ . "resources/.gitauth");
    }

    public function testGitVersioning_versionList(): void
    {
        $dm = new dependency_manager();
        $result = dependency_manager_versioning::gitVersionList("bhoogter", "xml-file");
// print $result;
        $this->assertTrue(strlen($result) > 1000);
    }

    public function testGitVersioning_versions(): void
    {
        $dm = new dependency_manager();
        $result = dependency_manager_versioning::gitVersions("bhoogter", "xml-file");
        $this->assertEquals(10, sizeof($result));
    }

    public function testGitVersioning_resolveGitVersion(): void
    {
        $dm = new dependency_manager();
        $url = "";
        $rec = ">0.2.0";
        $result = dependency_manager_versioning::resolveGitVersion("bhoogter", "xml-file", $rec, $url);
        $expected = "0.2.61";
        $expectedUrl = "https://github.com/bhoogter/xml-file/releases/download/0.2.61/xml-file.phar";

// print "\nresult=$result";
        $this->assertEquals($expected, $result);
// print "\nurl=$url\n";
        $this->assertEquals($expectedUrl, $url);
    }

    public function testGitVersioning_resolveGitVersion2(): void
    {
        $dm = new dependency_manager();
        $url = "";
        $rec = "0.2.+";
        $result = dependency_manager_versioning::resolveGitVersion("bhoogter", "xml-file", $rec, $url);
        $expected = "0.2.61";
        $expectedUrl = "https://github.com/bhoogter/xml-file/releases/download/0.2.61/xml-file.phar";

// print "\nresult=$result";
        $this->assertEquals($expected, $result);
// print "\nurl=$url\n";
        $this->assertEquals($expectedUrl, $url);
    }
}
