@echo off
REM Prefer PHP binary in the following order:
REM 1. Whatever %PHPBIN% points to.
REM 2. "php" from %cd% with one of %pathext% extensions.
REM 3. "php" from a %path% path with one of %pathext% extensions.
REM 4. Whatever %PHP_PEAR_PHP_BIN% points to.
REM
REM Once a binary is found, a file is looked for at the %PHPFILE% environment
REM variable (not intendend to be overriden).
REM Fallbacks to a file with the same name as this one.
REM Prefered extensions are ".php" and then no extension.
REM
goto SET_BIN
:PHP_ERR
echo PHP interpreter not found. Please set the %%PHPBIN%% or %%PHP_PEAR_PHP_BIN%% environment variable to one, or add one to your %%PATH%%.
goto DONE
:FILE_ERR
echo The file to be ran was not found. It should be at either "%~d0%~p0%~n0.php" or "%~d0%~p0%~n0".
goto DONE
:SET_BIN
if "%PHPBIN%" == "" set PHPBIN=php
where /q %PHPBIN%
if %ERRORLEVEL% == 0 goto SET_FILE
if "%PHP_PEAR_PHP_BIN%" == "" goto PHP_ERR
where /q "%PHP_PEAR_PHP_BIN%"
if %ERRORLEVEL% neq 0 goto PHP_ERR
set PHPBIN=%PHP_PEAR_PHP_BIN%
:SET_FILE
if "%PHPFILE%" == "" set PHPFILE=%~d0%~p0%~n0.php
if exist "%PHPFILE%" goto RUN
set PHPFILE=%~d0%~p0%~n0
if not exist "%PHPFILE%" goto FILE_ERR
:RUN
"%PHPBIN%" "%PHPFILE%" %*
:DONE
