<?php

$f = (strpos(__FILE__, ".phar") === false ? __DIR__ : "phar://" . __FILE__ . "/src") . "/class-dependency-manager.php";
require_once($f);
$f = (strpos(__FILE__, ".phar") === false ? __DIR__ : "phar://" . __FILE__ . "/src") . "/utils/dependency-manager-versioning.php";
require_once($f);
__HALT_COMPILER();
