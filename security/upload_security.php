<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const IMAGE_MIME_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const DOCUMENT_MIME_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

/**
 * Validates and stores one uploaded file under $targetDir with a random name.
 * The file type is detected from the file contents, not from the browser.
 *
 * @param array<string,string> $allowed mime type => extension
 * @return array{success: bool, message?: string, file_name?: string, mime?: string, original_name?: string}
 */
function storeUploadedFile(array $file, string $targetDir, array $allowed, int $maxBytes = 5 * 1024 * 1024): array {
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return ['success' => false, 'message' => 'File is too large.'];
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'The file could not be uploaded.'];
    }
    if ((int) $file['size'] > $maxBytes) {
        return ['success' => false, 'message' => sprintf('File exceeds the %dMB limit.', intdiv($maxBytes, 1024 * 1024))];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        $types = strtoupper(implode(', ', array_unique(array_values($allowed))));
        return ['success' => false, 'message' => 'Only ' . $types . ' files are allowed.'];
    }

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return ['success' => false, 'message' => 'Upload folder is not writable.'];
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $fileName)) {
        return ['success' => false, 'message' => 'Could not store the uploaded file.'];
    }

    return [
        'success' => true,
        'file_name' => $fileName,
        'mime' => $mime,
        'original_name' => mb_substr(basename((string) ($file['name'] ?? 'file')), 0, 200),
    ];
}
