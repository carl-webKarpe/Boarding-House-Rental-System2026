<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getDb(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', BH_DB_HOST, BH_DB_PORT, BH_DB_NAME, BH_DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, BH_DB_USER, BH_DB_PASS, $options);
    } catch (PDOException $e) {
        writeLog('Database connection failed: ' . $e->getMessage(), 'ERROR');
        http_response_code(503);
        $message = 'Cannot connect to the database. Make sure MySQL is running and the DB_* settings in .env are correct.';
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html')) {
            echo '<p style="font-family:sans-serif;padding:2rem">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            echo json_encode(['success' => false, 'message' => $message]);
        }
        exit;
    }

    return $pdo;
}
