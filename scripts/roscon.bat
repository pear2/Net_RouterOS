@echo off
:: Prefer PHP binary in the following order:
:: 1. Whatever %PHPBIN% points to.
:: 2. "php" from %cd% with one of %pathext% extensions.
:: 3. "php" from a %path% path with one of %pathext% extensions.
:: 4. Whatever %PHP_PEAR_PHP_BIN% points to.
::
:: Once a binary is found, a file with the same name as this one
:: will be ran. Prefered extensions are no extension and ".php"
:: in this order.
::
if "%PHPBIN%" == "" set PHPBIN=php
if exist "%PHPBIN%" goto SET_FILE
if "%PHP_PEAR_PHP_BIN%" neq "" goto USE_PEAR_BIN
goto PHP_ERR
:USE_PEAR_BIN
set PHPBIN=%PHP_PEAR_PHP_BIN%
if exist "%PHP_PEAR_PHP_BIN%" goto SET_FILE
goto PHP_ERR
:SET_FILE
if "%PHPFILE%" == "" set PHPFILE=%~d0%~p0%~n0
if exist "%PHPFILE%" goto RUN
set PHPFILE=%~d0%~p0%~n0.php
if exist "%PHPFILE%" goto RUN
goto FILE_ERR
:RUN
"%PHPBIN%" "%PHPFILE%" %*
goto DONE
:PHP_ERR
echo PHP interpreter not found. Please set the %%PHPBIN%% or %%PHP_PEAR_PHP_BIN%% environment variable to one, or add a PHP interpreter to your %%PATH%%.
goto DONE
:FILE_ERR
echo The file to be ran was not found. It should be in the same folder, and either have a ".php" extension, or no extension at all.
:DONE
