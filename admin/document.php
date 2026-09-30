<?php

declare(strict_types=1);

/**
 * Streams a private identity document to administrators only.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

requireRole(ADMIN_ROLES);

$stmt = getDb()->prepare('SELECT file_name, mime_type, original_name FROM user_documents WHERE id = :id');
$stmt->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$doc = $stmt->fetch();

$path = $doc ? BH_DOCUMENT_DIR . '/' . basename((string) $doc['file_name']) : '';
if (!$doc || !is_file($path)) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

header('Content-Type: ' . $doc['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $doc['original_name']) . '"');
header('Cache-Control: private, no-store');
readfile($path);
