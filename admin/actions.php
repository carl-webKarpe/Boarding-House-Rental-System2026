<?php

declare(strict_types=1);

/**
 * Handles admin actions on user accounts (POST only).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

requireRole(ADMIN_ROLES);
$returnTo = safeReturnPath($_POST['return'] ?? null, '../admin/users.php');
requireValidPost($returnTo);

$pdo = getDb();
$adminId = (int) currentUserId();
$targetId = (int) ($_POST['user_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id');
$stmt->execute([':id' => $targetId]);
$target = $stmt->fetch();

if (!$target) {
    flash('error', 'User not found.');
    redirect($returnTo);
}
if ($targetId === $adminId) {
    flash('error', 'You cannot change your own account here.');
    redirect($returnTo);
}
if ($target['role'] === ROLE_SUPER_ADMIN && currentRole() !== ROLE_SUPER_ADMIN) {
    flash('error', 'Only a super admin can change another super admin.');
    redirect($returnTo);
}

$updates = [
    'approve' => ["approval_status = 'approved'", 'Landlord approved. Their listings are now visible to tenants.'],
    'reject' => ["approval_status = 'rejected'", 'Landlord registration rejected.'],
    'deactivate' => ['is_active = 0', 'Account deactivated.'],
    'activate' => ['is_active = 1, failed_login_attempts = 0, locked_until = NULL', 'Account activated.'],
];

if (!isset($updates[$action])) {
    flash('error', 'Unknown action.');
    redirect($returnTo);
}

[$set, $message] = $updates[$action];
$pdo->prepare("UPDATE users SET $set WHERE id = :id")->execute([':id' => $targetId]);
auditLog('admin_' . $action, 'Admin ' . $action . ' user @' . $target['username'], $adminId);
flash('success', $message);
redirect($returnTo);
