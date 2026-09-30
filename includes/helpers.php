<?php

declare(strict_types=1);

require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/csrf.php';
require_once __DIR__ . '/../security/roles.php';

const ROOM_TYPES = [
    'shared' => 'Shared Room',
    'solo' => 'Solo Room',
    'dormitory' => 'Dormitory',
    'family' => 'Family Room',
];

const ROOM_STATUSES = [
    'available' => 'Available',
    'full' => 'Full',
    'hidden' => 'Hidden',
];

const COMMON_AMENITIES = [
    'WiFi', 'Air Conditioning', 'Electric Fan', 'Bed', 'Cabinet', 'Study Table',
    'Private Bathroom', 'Shared Bathroom', 'Kitchen', 'Laundry Area', 'CCTV',
    'Parking', 'Water Included', 'Electricity Included', 'Balcony',
];

/** Escape a value for HTML output. */
function e(mixed $value): string {
    return sanitizeForOutput($value ?? '');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Only allow redirects back to pages inside this site. */
function safeReturnPath(?string $path, string $fallback): string {
    $path = (string) $path;
    if ($path === '' || !preg_match('#^\.\./[A-Za-z0-9_\-/]+\.php(\?[A-Za-z0-9_=&%+.\-]*)?$#', $path)) {
        return $fallback;
    }
    return $path;
}

function flash(string $type, string $message): void {
    startSecureSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function takeFlashes(): array {
    startSecureSession();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUserId(): ?int {
    return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

function currentRole(): string {
    return isLoggedIn() ? (string) ($_SESSION['role'] ?? ROLE_TENANT) : 'guest';
}

/** Stops the request unless it is a POST with a valid CSRF token. */
function requireValidPost(string $backTo): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($backTo);
    }
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'The upload is too large. Please use smaller photos (max 5MB each).');
        redirect($backTo);
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Your session expired. Please try again.');
        redirect($backTo);
    }
}

function money(mixed $amount): string {
    return '₱' . number_format((float) $amount, 0);
}

function roomTypeLabel(string $type): string {
    return ROOM_TYPES[$type] ?? ucfirst($type);
}

/** Photos are stored either as full https URLs or paths under uploads/. */
function photoUrl(?string $path): string {
    if ($path === null || $path === '') {
        return '../Image/images.jpg';
    }
    if (preg_match('#^https://#i', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

function amenityList(?string $amenities): array {
    return array_values(array_filter(array_map('trim', explode(',', (string) $amenities))));
}

function lines(?string $text): array {
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text))));
}

function fullName(array $row, string $prefix = ''): string {
    $name = trim(($row[$prefix . 'first_name'] ?? '') . ' ' . ($row[$prefix . 'last_name'] ?? ''));
    return $name !== '' ? $name : (string) ($row[$prefix . 'username'] ?? '');
}

function statusBadge(string $status): string {
    $classes = [
        'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'approved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'available' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'rejected' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'cancelled' => 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'inactive' => 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'hidden' => 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'full' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    ];
    $class = $classes[$status] ?? 'bg-slate-200 text-slate-600';
    return '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

/** Turns the $_FILES structure of a multi-file input into a simple list. */
function normalizeUploadedFiles(?array $files): array {
    if (!$files || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }

    $list = [];
    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = [
            'name' => $name,
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }
    return $list;
}

function postString(string $key, int $maxLength = 255): string {
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $maxLength);
}

function postMoney(string $key): float {
    $value = (float) ($_POST[$key] ?? 0);
    return max(0, round($value, 2));
}
