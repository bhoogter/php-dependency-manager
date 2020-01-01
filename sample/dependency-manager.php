<?php

// Place the dependency manager phar in the same directory ()
define('DEPENDENCY_MANAGER_PHAR', __DIR__ . "/php-dependency-manager.phar");
require_once("phar://" . DEPENDENCY_MANAGER_PHAR . "/src/class-dependency-manager.phar");
