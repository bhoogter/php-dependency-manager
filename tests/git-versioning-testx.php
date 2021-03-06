<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class git_versioning_test extends TestCase
{
    private const XMLFILEVER = "0.2.64";
    private const XMLFILEURL = "https://github.com/bhoogter/xml-file/releases/download/0.2.64/xml-file.phar";

    public static function setUpBeforeClass(): void
    {
        $test_data = file_get_contents(__DIR__ . "/resources/xml-file-releases.json");
        $test_auth = @file_get_contents(__DIR__ . "resources/.gitauth");

        $dm = new dependency_manager();
        $dm->gitTestResponse = $test_data;
        $dm->gitAuth = $test_auth;
    }

    public function testGitVersioning_versionList(): void
    {
        $result = dependency_manager()->gitVersionList("bhoogter", "xml-file");
// print $result;
        $this->assertTrue(strlen($result) > 1000);
    }

    public function testGitVersioning_versions(): void
    {
        $result = dependency_manager()->gitVersions("bhoogter", "xml-file");
        $this->assertEquals(13, sizeof($result));
    }

    public function testGitVersioning_resolveGitVersion(): void
    {
        $url = "";
        $result = dependency_manager()->resolveGitVersion("bhoogter", "xml-file", ">0.2.0", $url);
        $this->assertEquals(self::XMLFILEVER, $result);
        $this->assertEquals(self::XMLFILEURL, $url);
    }

    public function testGitVersioning_resolveGitVersion2(): void
    {
        $url = "";
        $result = dependency_manager()->resolveGitVersion("bhoogter", "xml-file", "0.2.+", $url);
        $this->assertEquals(self::XMLFILEVER, $result);
        $this->assertEquals(self::XMLFILEURL, $url);
    }
}
