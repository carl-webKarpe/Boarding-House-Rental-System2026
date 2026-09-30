#!/bin/sh
# Starts the Boarding House Rental System (macOS/Linux).
cd "$(dirname "$0")"
[ -f .env ] || cp .env.example .env
echo "Starting server at http://localhost:8000"
php -d upload_max_filesize=10M -d post_max_size=60M -S localhost:8000 router.php
