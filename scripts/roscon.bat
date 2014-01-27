@echo off
REM Prefer PHP binary in the following order:
REM 1. Whatever %PHPBIN% points to.
REM 2. "php" from %cd% with one of %pathext% extensions.
REM 3. "php" from a %path% path with one of %pathext% extensions.
REM 4. Whatever %PHP_PEAR_PHP_BIN% points to.
REM
REM Once a binary is found, a file is looked for that has the same name as
REM this batch file.
REM Prefered extensions are ".php" and then no extension.
goto SET_BIN
:PHP_ERR
echo PHP interpreter not found. Please set the %%PHPBIN%% or %%PHP_PEAR_PHP_BIN%% environment variable to one, or add one to your %%PATH%%.
goto :eof
:FILE_ERR
echo The file to be ran was not found. It should be at either "%~d0%~p0%~n0.php" or "%~d0%~p0%~n0".
endlocal
goto :eof
:SET_BIN
if "%PHPBIN%" == "" set PHPBIN=php
where /q %PHPBIN%
if %ERRORLEVEL% == 0 goto SET_FILE
if "%PHP_PEAR_PHP_BIN%" == "" goto PHP_ERR
where /q "%PHP_PEAR_PHP_BIN%"
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
endlocal
