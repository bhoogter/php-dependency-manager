<?php

unlink('php-dependency-manager.phar');
$phar = new Phar('php-dependency-manager.phar');
// $phar->buildFromDirectory('src');
// $phar->buildFromIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src')));
$pattern = '#' . dirname(__FILE__) . '.src.*$#';
$pattern = str_replace("\\", "\\\\", $pattern);
$phar->buildFromDirectory(dirname(__FILE__), $pattern);
$phar->addFile("LICENSE");
$phar->addFile("VERSION");
$phar->addFile("README.md");
$phar->setDefaultStub('src/stub.php');
