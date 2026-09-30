<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin();
$role = currentRole();
if (!isTenantRole($role)) {
    redirect(homePathForRole($role));
}

$userId = (int) currentUserId();
$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidPost('dashboard.php');
    if (($_POST['action'] ?? '') === 'cancel') {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id AND tenant_id = :tenant AND status = 'pending'");
        $stmt->execute([':id' => (int) ($_POST['booking_id'] ?? 0), ':tenant' => $userId]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Booking request cancelled.' : 'Only pending requests can be cancelled.');
    }
    redirect('dashboard.php');
}

$stmt = $pdo->prepare('
    SELECT b.*, r.name AS room_name, r.monthly_rent, bh.name AS house_name, bh.barangay, bh.city,
           (SELECT p.path FROM room_photos p WHERE p.room_id = r.id ORDER BY p.sort_order, p.id LIMIT 1) AS cover_photo
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    WHERE b.tenant_id = :id
    ORDER BY b.created_at DESC');
$stmt->execute([':id' => $userId]);
$bookings = $stmt->fetchAll();
$favorites = favoriteRooms($userId);

$counts = ['pending' => 0, 'approved' => 0];
foreach ($bookings as $booking) {
    if (isset($counts[$booking['status']])) {
        $counts[$booking['status']]++;
    }
}

pageStart('My Bookings', 'bookings');
?>
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">Welcome, <?php echo e($_SESSION['username'] ?? ''); ?>!</h1>
    <p class="text-sm text-slate-500">Track your booking requests and saved rooms.</p>
  </div>
  <a href="browse-rooms.php" class="<?php echo BTN_PRIMARY; ?>">Find a room</a>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-3">
  <?php foreach (['Pending requests' => $counts['pending'], 'Approved bookings' => $counts['approved'], 'Saved rooms' => count($favorites)] as $label => $value): ?>
    <div class="<?php echo CARD; ?>">
      <p class="text-sm text-slate-500"><?php echo e($label); ?></p>
      <p class="mt-1 font-display text-3xl font-bold text-slate-900 dark:text-white"><?php echo (int) $value; ?></p>
    </div>
  <?php endforeach; ?>
</div>

<section class="<?php echo CARD; ?> mt-6">
  <h2 class="font-display text-lg font-semibold">My booking requests</h2>
  <?php if (!$bookings): ?>
    <p class="mt-3 text-sm text-slate-500">You have not requested any rooms yet. <a href="browse-rooms.php" class="font-semibold text-emerald-600 hover:underline">Browse rooms</a> to get started.</p>
  <?php else: ?>
    <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
      <?php foreach ($bookings as $booking): ?>
        <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center">
          <img src="<?php echo e(photoUrl($booking['cover_photo'])); ?>" alt="" class="h-20 w-full rounded-2xl object-cover sm:w-28" />
          <div class="min-w-0 flex-1">
            <a href="room.php?id=<?php echo (int) $booking['room_id']; ?>" class="font-semibold hover:text-emerald-600"><?php echo e($booking['room_name']); ?></a>
            <p class="text-sm text-slate-500"><?php echo e($booking['house_name']); ?> · <?php echo e($booking['barangay']); ?>, <?php echo e($booking['city']); ?></p>
            <p class="text-sm text-slate-500">Move-in <?php echo e(date('M j, Y', strtotime((string) $booking['move_in_date']))); ?> · <?php echo e(money($booking['monthly_rent'])); ?>/month</p>
            <?php if ($booking['landlord_note']): ?>
              <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Landlord note: “<?php echo e($booking['landlord_note']); ?>”</p>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-2">
            <?php echo statusBadge($booking['status']); ?>
            <?php if ($booking['status'] === 'pending'): ?>
              <form method="post" onsubmit="return confirm('Cancel this booking request?');">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="cancel" />
                <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>" />
                <button type="submit" class="<?php echo BTN_DANGER; ?> !px-3 !py-1 text-xs">Cancel</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section id="favorites" class="mt-6">
  <h2 class="font-display text-lg font-semibold">Saved rooms</h2>
  <?php if (!$favorites): ?>
    <p class="mt-2 text-sm text-slate-500">Tap the heart on a room to save it here.</p>
  <?php else: ?>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($favorites as $room): ?>
        <?php roomCard($room, true); ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php
pageEnd();
