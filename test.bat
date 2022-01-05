REM ECHO OFF

IF [%1] == [] GOTO NOARG
:ARG
php ..\phpunit-9.5.phar --bootstrap ".\src\stub.php" %1
GOTO DONE

:NOARG
for %%f in (tests/*.php) do (
    echo %%~nf
    php ..\phpunit-9.5.phar --bootstrap ".\src\stub.php" "tests/%%~nf.php"
)
GOTO DONE

:DONE

ECHO "Complete."