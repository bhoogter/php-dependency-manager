<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class test_phar_test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // dependency_manager(__DIR__ . "/resources/dependencies-old-versions.xml",__DIR__ . "/resources/phars");
    }

    public function setUp(): void
    {
        // dependency_manager::$log_dump = true;
    }

    public function testResourceStringTools(): void
    {
        $v = dependency_manager::build_resource_string("github", "group", "name", "1.2.3");
        $this->assertEquals("github://group:name:1.2.3", $v);

        $opts = dependency_manager::parse_resource_string($v);
        $this->assertEquals('github', $opts['repo']);
        $this->assertEquals('group', $opts['group']);
        $this->assertEquals('name', $opts['name']);
        $this->assertEquals('1.2.3', $opts['version']);
        $this->assertEquals('phar', $opts['type']);
        $this->assertEquals('', $opts['url']);
    }

    public function testResourceStringTools_withUrl(): void
    {
        $v = dependency_manager::build_resource_string("github", "group", "name", "1.2.3", "zip", "http://localhost/no-where");
        $this->assertEquals("github://group:name/zip:1.2.3:http://localhost/no-where", $v);

        $opts = dependency_manager::parse_resource_string($v);
        $this->assertEquals('github', $opts['repo']);
        $this->assertEquals('group', $opts['group']);
        $this->assertEquals('name', $opts['name']);
        $this->assertEquals('1.2.3', $opts['version']);
        $this->assertEquals('zip', $opts['type']);
        $this->assertEquals('', $opts['ext']);
        $this->assertEquals('http://localhost/no-where', $opts['url']);
    }
}
