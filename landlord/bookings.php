<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireRole([ROLE_LANDLORD]);
$landlordId = (int) currentUserId();
$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidPost('bookings.php');
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $note = postString('landlord_note', 255) ?: null;

    try {
        $pdo->beginTransaction();
        // Lock the booking and its room so two approvals can't overfill a room.
        $stmt = $pdo->prepare('
            SELECT b.id, b.status, b.tenant_id, r.id AS room_id, r.available_slots, r.capacity, r.status AS room_status
            FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            JOIN boarding_houses bh ON bh.id = r.boarding_house_id
            WHERE b.id = :id AND bh.landlord_id = :landlord
            FOR UPDATE');
        $stmt->execute([':id' => $bookingId, ':landlord' => $landlordId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            throw new RuntimeException('Booking not found.');
        }

        if ($action === 'approve') {
            if ($booking['status'] !== 'pending') {
                throw new RuntimeException('Only pending requests can be approved.');
            }
            if ((int) $booking['available_slots'] < 1) {
                throw new RuntimeException('This room has no open slots left. Update the room first or decline the request.');
            }
            $slotsLeft = (int) $booking['available_slots'] - 1;
            $pdo->prepare("UPDATE bookings SET status = 'approved', landlord_note = :note WHERE id = :id")->execute([':note' => $note, ':id' => $bookingId]);
            $pdo->prepare('UPDATE rooms SET available_slots = :slots, status = :status WHERE id = :id')->execute([
                ':slots' => $slotsLeft,
                ':status' => $slotsLeft === 0 && $booking['room_status'] === 'available' ? 'full' : $booking['room_status'],
                ':id' => $booking['room_id'],
            ]);
            $message = 'Booking approved. The room now has ' . $slotsLeft . ' open slot' . ($slotsLeft === 1 ? '' : 's') . '.';
        } elseif ($action === 'reject') {
            if ($booking['status'] !== 'pending') {
                throw new RuntimeException('Only pending requests can be declined.');
            }
            $pdo->prepare("UPDATE bookings SET status = 'rejected', landlord_note = :note WHERE id = :id")->execute([':note' => $note, ':id' => $bookingId]);
            $message = 'Booking declined.';
        } elseif ($action === 'end') {
            // Tenant moved out or the approved booking was called off: free the slot again.
            if ($booking['status'] !== 'approved') {
                throw new RuntimeException('Only approved bookings can be ended.');
            }
            $slots = min((int) $booking['capacity'], (int) $booking['available_slots'] + 1);
            $pdo->prepare("UPDATE bookings SET status = 'cancelled', landlord_note = :note WHERE id = :id")->execute([':note' => $note ?? 'Ended by landlord', ':id' => $bookingId]);
            $pdo->prepare('UPDATE rooms SET available_slots = :slots, status = IF(status = \'full\', \'available\', status) WHERE id = :id')->execute([':slots' => $slots, ':id' => $booking['room_id']]);
            $message = 'Booking ended and the slot is open again.';
        } else {
            throw new RuntimeException('Unknown action.');
        }

        $pdo->commit();
        auditLog('booking_' . $action, 'Booking #' . $bookingId, $landlordId);
        flash('success', $message);
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
    }
    redirect('bookings.php' . (isset($_GET['status']) ? '?status=' . urlencode((string) $_GET['status']) : ''));
}

$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'cancelled'], true) ? $_GET['status'] : 'pending';
$stmt = $pdo->prepare('
    SELECT b.*, r.name AS room_name, r.available_slots, bh.name AS house_name,
           u.username, u.first_name, u.last_name, u.email, u.phone, u.gender
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    JOIN users u ON u.id = b.tenant_id
    WHERE bh.landlord_id = :landlord AND b.status = :status
    ORDER BY b.created_at DESC');
$stmt->execute([':landlord' => $landlordId, ':status' => $statusFilter]);
$bookings = $stmt->fetchAll();

pageStart('Booking Requests', 'requests');
?>
<h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">Booking Requests</h1>
<p class="text-sm text-slate-500">Approve or decline tenants who want to rent your rooms.</p>

<div class="mt-4 flex flex-wrap gap-2">
  <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Declined', 'cancelled' => 'Cancelled'] as $value => $label): ?>
    <a href="?status=<?php echo $value; ?>" class="rounded-full px-4 py-1.5 text-sm font-semibold <?php echo $statusFilter === $value ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 border border-slate-200 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300'; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<div class="mt-6 space-y-4">
  <?php if (!$bookings): ?>
    <div class="<?php echo CARD; ?> text-sm text-slate-500">No <?php echo e($statusFilter); ?> requests.</div>
  <?php endif; ?>
  <?php foreach ($bookings as $booking): ?>
    <article class="<?php echo CARD; ?>">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="font-semibold"><?php echo e(fullName($booking)); ?> <span class="text-sm font-normal text-slate-500">@<?php echo e($booking['username']); ?></span></p>
          <p class="text-sm text-slate-500">
            <?php echo e($booking['email']); ?><?php echo $booking['phone'] ? ' · ' . e($booking['phone']) : ''; ?><?php echo $booking['gender'] ? ' · ' . e(ucwords(str_replace('_', ' ', $booking['gender']))) : ''; ?>
          </p>
          <p class="mt-2 text-sm">Wants <strong><?php echo e($booking['room_name']); ?></strong> at <?php echo e($booking['house_name']); ?> · Move-in <strong><?php echo e(date('M j, Y', strtotime((string) $booking['move_in_date']))); ?></strong></p>
          <?php if ($booking['message']): ?>
            <p class="mt-2 rounded-2xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">“<?php echo e($booking['message']); ?>”</p>
          <?php endif; ?>
          <?php if ($booking['landlord_note']): ?><p class="mt-2 text-sm text-slate-500">Your note: <?php echo e($booking['landlord_note']); ?></p><?php endif; ?>
          <p class="mt-2 text-xs text-slate-400">Requested <?php echo e(date('M j, Y g:i A', strtotime((string) $booking['created_at']))); ?></p>
        </div>
        <?php echo statusBadge($booking['status']); ?>
      </div>

      <?php if (in_array($booking['status'], ['pending', 'approved'], true)): ?>
        <form method="post" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
          <?php echo csrfInput(); ?>
          <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>" />
          <input name="landlord_note" maxlength="255" placeholder="Optional note to the tenant" class="<?php echo INPUT; ?> !mt-0 flex-1" />
          <?php if ($booking['status'] === 'pending'): ?>
            <button name="action" value="approve" class="<?php echo BTN_PRIMARY; ?>" <?php echo (int) $booking['available_slots'] < 1 ? 'disabled title="No open slots"' : ''; ?>>Approve</button>
            <button name="action" value="reject" class="<?php echo BTN_DANGER; ?>">Decline</button>
          <?php else: ?>
            <button name="action" value="end" class="<?php echo BTN_SECONDARY; ?>" onclick="return confirm('End this booking and open the slot again?');">End booking / moved out</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php
pageEnd();
