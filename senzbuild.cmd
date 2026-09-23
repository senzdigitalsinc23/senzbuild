@echo off
REM SenzBuild Launcher for Windows
REM Delegates to the PHP-based senzbuild script.
REM Place this file in a directory on your PATH (e.g., D:\composer\).

set "SENZ_SCRIPT=%~dp0senzbuild"
if not exist "%SENZ_SCRIPT%" (
    echo ERROR:senzbuild PHP script not found at %SENZ_SCRIPT%
    exit /b 1
)

php "%SENZ_SCRIPT%" %*
