@echo off
setlocal

where git >nul 2>nul
if errorlevel 1 (
	echo Error: git is not installed or not on PATH.
	exit /b 1
)

if "%~1"=="" (
	echo Usage: repo.bat "your commit message"
	exit /b 1
)

if not exist ".git" (
	echo Error: run this script from the root of a git repository.
	exit /b 1
)

set "commit_message=%*"

echo Staging all changes...
git add .
if errorlevel 1 exit /b 1

git diff --cached --quiet
if %errorlevel%==0 (
	echo No staged changes to commit.
	exit /b 0
)

echo Creating commit...
git commit -m "%commit_message%"
if errorlevel 1 exit /b 1

for /f "delims=" %%b in ('git rev-parse --abbrev-ref HEAD') do set "current_branch=%%b"

echo Pushing to origin/%current_branch%...
git push -u origin "%current_branch%"
if errorlevel 1 exit /b 1

echo Done. Changes pushed successfully.
exit /b 0
