@echo off
REM Plugin Tests Framework - BATS Runner for Windows
REM Runs BATS tests in Docker for cross-platform consistency
REM
REM Usage: run-bats.cmd [options] <test-file-or-directory>

setlocal enabledelayedexpansion

REM Use current directory as workspace root
set "WORKSPACE_ROOT=%CD%"

REM Convert workspace to Docker mount format (/c/path/to/workspace)
set "DOCKER_MOUNT=%WORKSPACE_ROOT%"
set "DOCKER_MOUNT=%DOCKER_MOUNT:\=/%"
set "DOCKER_MOUNT=/%DOCKER_MOUNT:~0,1%%DOCKER_MOUNT:~2%"

REM Process arguments - collect them and convert any file paths
set "DOCKER_ARGS="
set "WORKSPACE_ESCAPED=%WORKSPACE_ROOT:\=\\%"

:loop
if "%~1"=="" goto done

set "ARG=%~1"

REM Check if this is an absolute Windows path (starts with drive letter)
echo %ARG% | findstr /b /r "[A-Za-z]:" >nul
if !errorlevel! equ 0 (
    REM It's an absolute path - convert to relative
    REM PowerShell does this more reliably
    for /f "delims=" %%R in ('powershell -NoProfile -Command "$p='%ARG%'; $w='%WORKSPACE_ROOT%'; if($p.StartsWith($w)){$p.Substring($w.Length+1).Replace('\','/')}else{$p.Replace('\','/')}"') do set "ARG=%%R"
)

set "DOCKER_ARGS=!DOCKER_ARGS! !ARG!"
shift
goto loop

:done
docker run --rm -v "%DOCKER_MOUNT%:/code" -w /code bats/bats:latest!DOCKER_ARGS!
