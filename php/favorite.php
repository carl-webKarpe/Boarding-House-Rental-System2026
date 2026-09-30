<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$returnTo = safeReturnPath($_POST['return'] ?? null, '../php/browse-rooms.php');
requireValidPost($returnTo);

if (!isTenantRole(currentRole()) || !isLoggedIn()) {
    flash('error', 'Log in with a tenant account to save rooms.');
    redirect($returnTo);
}

$roomId = (int) ($_POST['room_id'] ?? 0);
$room = $roomId > 0 ? findRoom($roomId) : null;
if (!$room || !canViewRoom($room)) {
    redirect($returnTo);
}

$pdo = getDb();
$params = [':user' => currentUserId(), ':room' => $roomId];
$delete = $pdo->prepare('DELETE FROM favorites WHERE user_id = :user AND room_id = :room');
$delete->execute($params);
if ($delete->rowCount() === 0) {
    $pdo->prepare('INSERT INTO favorites (user_id, room_id) VALUES (:user, :room)')->execute($params);
    flash('success', 'Saved to your favorites.');
} else {
    flash('success', 'Removed from your favorites.');
}

redirect($returnTo);
