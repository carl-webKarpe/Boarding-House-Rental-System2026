@echo off
REM Starts the Boarding House Rental System without XAMPP.
REM Requirements: PHP 8+ on your PATH and MySQL Server running.
cd /d "%~dp0"
if not exist .env copy .env.example .env >nul
echo Starting server at http://localhost:8000  (press Ctrl+C to stop)
start "" http://localhost:8000
php -d upload_max_filesize=10M -d post_max_size=60M -S localhost:8000 router.php
