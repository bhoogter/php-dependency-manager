<?php

spl_autoload_register(function ($name) {
    if ($name == "dependency_manager") require_once(__DIR__ . "/class_dependency_manager.php");
});
