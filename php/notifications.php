<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin();
$role = currentRole();
$userId = (int) currentUserId();
$pdo = getDb();

// Notifications are built from recent booking activity.
if ($role === ROLE_LANDLORD) {
    $stmt = $pdo->prepare("
        SELECT b.status, b.created_at, b.updated_at, r.name AS room_name, u.username, u.first_name, u.last_name
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN boarding_houses bh ON bh.id = r.boarding_house_id
        JOIN users u ON u.id = b.tenant_id
        WHERE bh.landlord_id = :id
        ORDER BY COALESCE(b.updated_at, b.created_at) DESC LIMIT 30");
} else {
    $stmt = $pdo->prepare("
        SELECT b.status, b.created_at, b.updated_at, b.landlord_note, r.id AS room_id, r.name AS room_name
        FROM bookings b JOIN rooms r ON r.id = b.room_id
        WHERE b.tenant_id = :id AND b.status IN ('approved', 'rejected', 'pending')
        ORDER BY COALESCE(b.updated_at, b.created_at) DESC LIMIT 30");
}
$stmt->execute([':id' => $userId]);
$items = $stmt->fetchAll();

function notificationText(array $item, string $role): string {
    if ($role === ROLE_LANDLORD) {
        $who = fullName($item);
        return match ($item['status']) {
            'pending' => $who . ' requested to book ' . $item['room_name'] . '.',
            'cancelled' => $who . ' cancelled their request for ' . $item['room_name'] . '.',
            default => 'You ' . $item['status'] . ' ' . $who . '\'s request for ' . $item['room_name'] . '.',
        };
    }
    return match ($item['status']) {
        'approved' => 'Your booking for ' . $item['room_name'] . ' was approved! 🎉',
        'rejected' => 'Your booking for ' . $item['room_name'] . ' was declined.',
        default => 'Your request for ' . $item['room_name'] . ' is waiting for the landlord.',
    };
}

pageStart('Notifications', 'notifications');
?>
<h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">Notifications</h1>
<section class="<?php echo CARD; ?> mt-6">
  <?php if (!$items): ?>
    <p class="text-sm text-slate-500">No notifications yet.</p>
  <?php endif; ?>
  <ul class="divide-y divide-slate-100 dark:divide-slate-800">
    <?php foreach ($items as $item): ?>
      <li class="flex items-start justify-between gap-3 py-3">
        <div>
          <p class="text-sm font-medium"><?php echo e(notificationText($item, $role)); ?></p>
          <?php if (!empty($item['landlord_note'])): ?><p class="text-sm text-slate-500">“<?php echo e($item['landlord_note']); ?>”</p><?php endif; ?>
          <p class="text-xs text-slate-400"><?php echo e(date('M j, Y g:i A', strtotime((string) ($item['updated_at'] ?? $item['created_at'])))); ?></p>
        </div>
        <?php echo statusBadge($item['status']); ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php
pageEnd();
