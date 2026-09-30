<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireRole([ROLE_LANDLORD]);
$landlordId = (int) currentUserId();
$pdo = getDb();

$approved = landlordIsApproved($landlordId);
$houses = landlordHouses($landlordId);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE bh.landlord_id = :id AND b.status = 'pending'");
$stmt->execute([':id' => $landlordId]);
$pendingRequests = (int) $stmt->fetchColumn();

$roomTotal = array_sum(array_map(static fn ($h) => (int) $h['room_count'], $houses));
$openSlots = array_sum(array_map(static fn ($h) => (int) $h['open_slots'], $houses));

pageStart('My Listings', 'landlord');
?>
<?php if (!$approved): ?>
  <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
    <p class="font-semibold">Your account is waiting for administrator approval.</p>
    <p class="mt-1">You can already add your boarding houses and rooms. Tenants will see them as soon as an administrator verifies your documents.</p>
  </div>
<?php endif; ?>

<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">My Listings</h1>
    <p class="text-sm text-slate-500">Manage your boarding houses, rooms, and availability.</p>
  </div>
  <a href="house-form.php" class="<?php echo BTN_PRIMARY; ?>">+ Add boarding house</a>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php foreach (['Boarding houses' => count($houses), 'Rooms' => $roomTotal, 'Open slots' => $openSlots, 'Pending requests' => $pendingRequests] as $label => $value): ?>
    <div class="<?php echo CARD; ?>">
      <p class="text-sm text-slate-500"><?php echo e($label); ?></p>
      <p class="mt-1 font-display text-3xl font-bold text-slate-900 dark:text-white"><?php echo (int) $value; ?></p>
      <?php if ($label === 'Pending requests' && $value > 0): ?><a href="bookings.php" class="text-sm font-semibold text-emerald-600 hover:underline">Review now →</a><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!$houses): ?>
  <div class="<?php echo CARD; ?> mt-6 text-center">
    <p class="font-semibold">You have not added a boarding house yet.</p>
    <p class="mt-1 text-sm text-slate-500">Start by adding your boarding house, then add its rooms with prices and photos.</p>
    <a href="house-form.php" class="<?php echo BTN_PRIMARY; ?> mt-4">Add your first boarding house</a>
  </div>
<?php endif; ?>

<?php foreach ($houses as $house): ?>
  <?php $rooms = houseRooms((int) $house['id']); ?>
  <section class="<?php echo CARD; ?> mt-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="font-display text-lg font-semibold"><?php echo e($house['name']); ?> <?php echo statusBadge($house['status']); ?></h2>
        <p class="text-sm text-slate-500"><?php echo e($house['address']); ?>, <?php echo e($house['city']); ?></p>
      </div>
      <div class="flex gap-2">
        <a href="house-form.php?id=<?php echo (int) $house['id']; ?>" class="<?php echo BTN_SECONDARY; ?>">Edit house</a>
        <a href="room-form.php?house_id=<?php echo (int) $house['id']; ?>" class="<?php echo BTN_PRIMARY; ?>">+ Add room</a>
      </div>
    </div>

    <?php if (!$rooms): ?>
      <p class="mt-4 text-sm text-slate-500">No rooms yet. Add a room so tenants can find this boarding house.</p>
    <?php else: ?>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="text-xs uppercase text-slate-500">
            <tr><th class="py-2">Room</th><th>Type</th><th>Rent</th><th>Slots</th><th>Status</th><th>Requests</th><th></th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($rooms as $room): ?>
              <tr>
                <td class="py-3">
                  <div class="flex items-center gap-3">
                    <img src="<?php echo e(photoUrl($room['cover_photo'])); ?>" alt="" class="h-10 w-14 rounded-lg object-cover" />
                    <span class="font-semibold"><?php echo e($room['name']); ?></span>
                  </div>
                </td>
                <td><?php echo e(roomTypeLabel($room['room_type'])); ?></td>
                <td><?php echo e(money($room['monthly_rent'])); ?></td>
                <td><?php echo (int) $room['available_slots']; ?> / <?php echo (int) $room['capacity']; ?></td>
                <td><?php echo statusBadge($room['status']); ?></td>
                <td><?php echo (int) $room['pending_bookings'] > 0 ? '<a href="bookings.php" class="font-semibold text-amber-600">' . (int) $room['pending_bookings'] . ' pending</a>' : '—'; ?></td>
                <td class="whitespace-nowrap text-right">
                  <a href="../php/room.php?id=<?php echo (int) $room['id']; ?>" class="text-sm font-semibold text-slate-500 hover:underline">View</a>
                  <a href="room-form.php?id=<?php echo (int) $room['id']; ?>" class="ml-3 text-sm font-semibold text-emerald-600 hover:underline">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php
pageEnd();
