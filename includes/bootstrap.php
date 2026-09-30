<?php

declare(strict_types=1);

/**
 * Common setup for every page: security headers, session, database, helpers.
 */
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/database.php';
require_once __DIR__ . '/../security/audit_log.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/listings.php';

applySecurityHeaders();
startSecureSession();
