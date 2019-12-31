#!/bin/bash

IF [%1] == [] GOTO NOARG
SET ARGS=%1
GOTO DONEARGS
:NOARG
SET ARGS=*.*
:DONEARGS

php ..\phpunit-6.5.14.phar tests\%ARGS

