<?php

declare(strict_types=1);

if (!defined('BH_SYSTEM_ROOT')) {
    define('BH_SYSTEM_ROOT', dirname(__DIR__));
}

if (!defined('BH_SYSTEM_SECURITY_DIR')) {
    define('BH_SYSTEM_SECURITY_DIR', __DIR__);
}

/**
 * Database configuration.
 * These values are intentionally simple for local development and can be moved
 * to a file outside the web root in production.
 */
define('BH_DB_HOST', '127.0.0.1');
define('BH_DB_NAME', 'bhsystem');
define('BH_DB_USER', 'root');
define('BH_DB_PASS', '');
define('BH_DB_CHARSET', 'utf8mb4');

/**
 * Application settings.
 */
define('BH_APP_NAME', 'Boarding House Rental System');
define('BH_SESSION_NAME', 'BHSESSID');
define('BH_LOG_FILE', BH_SYSTEM_ROOT . '/storage/app.log');
define('BH_RATE_LIMIT_FILE_DIR', BH_SYSTEM_ROOT . '/storage');
define('BH_UPLOAD_DIR', BH_SYSTEM_ROOT . '/uploads');
define('BH_UPLOAD_URL', '/uploads');

define('BH_PASSWORD_MIN_LENGTH', 8);

define('BH_MAX_LOGIN_ATTEMPTS', 5);
define('BH_LOCKOUT_MINUTES', 15);

define('BH_RESET_TOKEN_TTL_MINUTES', 60);

function isHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

function getClientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $value = (string) $_SERVER[$key];
            if (str_contains($value, ',')) {
                $value = trim(explode(',', $value)[0]);
            }
            return $value;
        }
    }

    return '127.0.0.1';
}

function writeLog(string $message, string $level = 'INFO'): void {
    if (!is_dir(BH_RATE_LIMIT_FILE_DIR)) {
        @mkdir(BH_RATE_LIMIT_FILE_DIR, 0777, true);
    }

    $line = sprintf("[%s] [%s] %s\n", gmdate('Y-m-d H:i:s'), strtoupper($level), $message);
    @file_put_contents(BH_LOG_FILE, $line, FILE_APPEND);
}
