@echo off
REM Plugin Tests Framework - Full Test Runner for Windows
REM Runs both PHP and BATS tests in Docker for consistency
REM
REM Usage: run-tests.cmd [php|bats|all] [additional-args]
REM

setlocal enabledelayedexpansion

set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Find workspace root
if exist "%SCRIPT_DIR%\..\bats\setup.bash" (
    for %%I in ("%SCRIPT_DIR%\..\.." ) do set "WORKSPACE_ROOT=%%~fI"
) else (
    set "WORKSPACE_ROOT=%SCRIPT_DIR%"
)

REM Convert to Docker path
set "DOCKER_WORKSPACE=%WORKSPACE_ROOT:\=/%"
set "DRIVE_LETTER=%DOCKER_WORKSPACE:~0,1%"
set "DOCKER_PATH=%DOCKER_WORKSPACE:~2%"
set "DOCKER_MOUNT=/%DRIVE_LETTER%%DOCKER_PATH%"

set "TEST_TYPE=%~1"
if "%TEST_TYPE%"=="" set "TEST_TYPE=all"

shift
set "EXTRA_ARGS=%*"

if "%TEST_TYPE%"=="php" goto run_php
if "%TEST_TYPE%"=="bats" goto run_bats
if "%TEST_TYPE%"=="all" goto run_all

echo Usage: run-tests.cmd [php^|bats^|all] [additional-args]
exit /b 1

:run_php
echo Running PHP tests...
docker run --rm -v "%DOCKER_MOUNT%:/code" -w /code php:8.2-cli sh -c "composer install --quiet 2>/dev/null; vendor/bin/phpunit %EXTRA_ARGS%"
goto :eof

:run_bats
echo Running BATS tests...
docker run --rm -v "%DOCKER_MOUNT%:/code" -w /code bats/bats:latest tests/unit/*.bats %EXTRA_ARGS%
goto :eof

:run_all
echo Running all tests...
echo.
echo === PHP Tests ===
call :run_php
echo.
echo === BATS Tests ===
call :run_bats
goto :eof
