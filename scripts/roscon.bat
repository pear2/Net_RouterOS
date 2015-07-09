@echo off
:: Prefer PHP binary in the following order:
:: 1. Whatever %PHPBIN% points to.
:: 2. "php" from %cd% with one of %pathext% extensions.
:: 3. "php" from a %path% path with one of %pathext% extensions.
:: 4. Whatever %PHP_PEAR_PHP_BIN% points to.
::
:: Once a binary is found, a file is looked for that has the same name
:: (including folder) as this batch file.
:: Preferred extensions are ".php" and then no extension.
::
:: On failure to find PHP binary or a PHP file, this batch file returns 255.
goto SET_BIN
:PHP_ERR
echo PHP interpreter not found. Please set the %%PHPBIN%% or %%PHP_PEAR_PHP_BIN%% environment variable to one, or add one to your %%PATH%%.
setlocal
goto :END
:FILE_ERR
echo The file to be ran was not found. It should be at either "%~d0%~p0%~n0.php" or "%~d0%~p0%~n0".
goto :END
:SET_BIN
if "%PHPBIN%" == "" set PHPBIN=php
where /q %PHPBIN%
if %ERRORLEVEL% == 0 goto SET_FILE
if "%PHP_PEAR_PHP_BIN%" == "" goto PHP_ERR
where /q "%PHP_PEAR_PHP_BIN%" 2>nul
if %ERRORLEVEL% neq 0 goto PHP_ERR
set PHPBIN=%PHP_PEAR_PHP_BIN%
:SET_FILE
setlocal
set PHPFILE=%~d0%~p0%~n0.php
if exist "%PHPFILE%" goto RUN
set PHPFILE=%~d0%~p0%~n0
if exist "%PHPFILE%" goto RUN
goto FILE_ERR
:RUN
"%PHPBIN%" "%PHPFILE%" %*
set PHPBIN_ERRORLEVEL="%ERRORLEVEL%"
:END
if "%PHPBIN_ERRORLEVEL%" == "" set PHPBIN_ERRORLEVEL=255
exit /B %PHPBIN_ERRORLEVEL%
