<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$roomId = (int) ($_GET['id'] ?? $_POST['room_id'] ?? 0);
$room = $roomId > 0 ? findRoom($roomId) : null;
if (!$room || !canViewRoom($room)) {
    http_response_code(404);
    pageStart('Room not found', 'browse');
    echo '<div class="' . CARD . ' text-center"><h1 class="font-display text-xl font-bold">Room not found</h1><p class="mt-2 text-sm text-slate-500">This room may have been removed.</p><a href="browse-rooms.php" class="' . BTN_PRIMARY . ' mt-4">Browse rooms</a></div>';
    pageEnd();
    exit;
}

$self = 'room.php?id=' . $roomId;
$userId = currentUserId();
$isTenant = isTenantRole(currentRole());
$pdo = getDb();

/** The tenant's latest booking for this room, if any. */
function tenantBooking(PDO $pdo, int $roomId, ?int $userId): ?array {
    if (!$userId) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE room_id = :room AND tenant_id = :tenant ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([':room' => $roomId, ':tenant' => $userId]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidPost($self);
    if (!$isTenant) {
        flash('error', 'Please log in with a tenant account to do that.');
        redirect($self);
    }

    $action = (string) ($_POST['action'] ?? '');
    $existing = tenantBooking($pdo, $roomId, $userId);

    if ($action === 'book') {
        $moveIn = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_POST['move_in_date'] ?? ''));
        $today = new DateTimeImmutable('today');
        $message = postString('message', 1000);

        if ($existing && in_array($existing['status'], ['pending', 'approved'], true)) {
            flash('error', 'You already have a ' . $existing['status'] . ' booking for this room.');
        } elseif ($room['status'] !== 'available' || (int) $room['available_slots'] < 1) {
            flash('error', 'Sorry, this room has no open slots right now.');
        } elseif (!$moveIn || $moveIn < $today || $moveIn > $today->modify('+1 year')) {
            flash('error', 'Please choose a move-in date between today and one year from now.');
        } else {
            $pdo->prepare('INSERT INTO bookings (room_id, tenant_id, move_in_date, message, status) VALUES (:room, :tenant, :move_in, :message, :status)')
                ->execute([':room' => $roomId, ':tenant' => $userId, ':move_in' => $moveIn->format('Y-m-d'), ':message' => $message, ':status' => 'pending']);
            auditLog('booking_request', 'Booking requested for room #' . $roomId, $userId);
            flash('success', 'Booking request sent! The landlord will review it. You can track it in My Bookings.');
        }
        redirect($self);
    }

    if ($action === 'review') {
        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = postString('comment', 1000);
        if (!$existing || $existing['status'] !== 'approved') {
            flash('error', 'You can review a room after your booking is approved.');
        } elseif ($rating < 1 || $rating > 5) {
            flash('error', 'Please choose a rating from 1 to 5 stars.');
        } else {
            $pdo->prepare('INSERT INTO reviews (room_id, tenant_id, rating, comment) VALUES (:room, :tenant, :rating, :comment)
                           ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)')
                ->execute([':room' => $roomId, ':tenant' => $userId, ':rating' => $rating, ':comment' => $comment]);
            flash('success', 'Thank you for your review!');
        }
        redirect($self . '#reviews');
    }

    redirect($self);
}

$photos = roomPhotos($roomId);
$reviews = roomReviews($roomId);
$booking = tenantBooking($pdo, $roomId, $userId);
$isFavorite = in_array($roomId, favoriteRoomIds($userId), true);
$myReview = null;
foreach ($reviews as $review) {
    if ((int) $review['tenant_id'] === $userId) {
        $myReview = $review;
    }
}
$hasOpenBooking = $booking && in_array($booking['status'], ['pending', 'approved'], true);
$isOpen = $room['status'] === 'available' && (int) $room['available_slots'] > 0;
$landlordName = trim($room['landlord_first_name'] . ' ' . $room['landlord_last_name']) ?: $room['landlord_username'];

pageStart($room['name'], 'browse');
?>
<a href="browse-rooms.php" class="text-sm font-semibold text-emerald-600 hover:underline">← Back to rooms</a>

<div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
  <div class="space-y-6">
    <section class="<?php echo CARD; ?> !p-3">
      <img id="mainPhoto" src="<?php echo e(photoUrl($photos[0]['path'] ?? null)); ?>" alt="<?php echo e($room['name']); ?>" class="h-64 w-full rounded-2xl object-cover sm:h-96" />
      <?php if (count($photos) > 1): ?>
        <div class="mt-3 flex gap-2 overflow-x-auto">
          <?php foreach ($photos as $photo): ?>
            <button type="button" data-photo="<?php echo e(photoUrl($photo['path'])); ?>" class="shrink-0 overflow-hidden rounded-xl border-2 border-transparent hover:border-emerald-500">
              <img src="<?php echo e(photoUrl($photo['path'])); ?>" alt="" class="h-16 w-24 object-cover" />
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="<?php echo CARD; ?>">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600"><?php echo e(roomTypeLabel($room['room_type'])); ?></p>
          <h1 class="mt-1 font-display text-2xl font-bold text-slate-900 dark:text-white"><?php echo e($room['name']); ?></h1>
          <p class="mt-1 text-sm text-slate-500"><?php echo e($room['house_name']); ?> · <?php echo e($room['address']); ?>, <?php echo e($room['city']); ?>, <?php echo e($room['province']); ?></p>
          <?php if ($room['nearby_school']): ?>
            <p class="mt-1 text-sm text-slate-500">🎓 <?php echo e($room['distance_to_school'] ? $room['distance_to_school'] . ' from ' : 'Near '); ?><?php echo e($room['nearby_school']); ?></p>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
          <?php echo statusBadge($isOpen ? 'available' : ($room['status'] === 'hidden' ? 'hidden' : 'full')); ?>
          <?php if ($room['avg_rating'] !== null): ?>
            <span class="rounded-lg bg-amber-50 px-2 py-1 text-sm font-bold text-amber-600 dark:bg-amber-500/10">★ <?php echo e(number_format((float) $room['avg_rating'], 1)); ?> (<?php echo (int) $room['review_count']; ?>)</span>
          <?php endif; ?>
        </div>
      </div>

      <dl class="mt-5 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <?php foreach ([
            'Monthly rent' => money($room['monthly_rent']),
            'Deposit' => money($room['deposit']),
            'Advance' => money($room['advance_payment']),
            'Open slots' => (int) $room['available_slots'] . ' of ' . (int) $room['capacity'],
            'Room size' => $room['size_sqm'] ? rtrim(rtrim((string) $room['size_sqm'], '0'), '.') . ' sqm' : '—',
            'For' => ['any' => 'Anyone', 'male' => 'Male only', 'female' => 'Female only'][$room['gender_policy']] ?? 'Anyone',
        ] as $label => $value): ?>
          <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/60">
            <dt class="text-xs text-slate-500"><?php echo e($label); ?></dt>
            <dd class="mt-1 font-semibold text-slate-900 dark:text-white"><?php echo e($value); ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>

      <?php if ($room['description']): ?>
        <h2 class="mt-6 font-display text-lg font-semibold">About this room</h2>
        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300"><?php echo e($room['description']); ?></p>
      <?php endif; ?>
      <?php if ($room['house_description']): ?>
        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300"><?php echo e($room['house_description']); ?></p>
      <?php endif; ?>

      <?php if ($amenities = amenityList($room['amenities'])): ?>
        <h2 class="mt-6 font-display text-lg font-semibold">Amenities</h2>
        <ul class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
          <?php foreach ($amenities as $amenity): ?>
            <li class="flex items-center gap-2"><span class="text-emerald-600">✓</span><?php echo e($amenity); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($rules = lines($room['house_rules'])): ?>
        <h2 class="mt-6 font-display text-lg font-semibold">House rules</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">
          <?php foreach ($rules as $rule): ?><li><?php echo e($rule); ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section id="reviews" class="<?php echo CARD; ?>">
      <h2 class="font-display text-lg font-semibold">Reviews</h2>
      <?php if ($isTenant && $booking && $booking['status'] === 'approved'): ?>
        <form method="post" class="mt-4 space-y-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
          <?php echo csrfInput(); ?>
          <input type="hidden" name="action" value="review" />
          <input type="hidden" name="room_id" value="<?php echo $roomId; ?>" />
          <label class="<?php echo LABEL; ?>"><?php echo $myReview ? 'Update your review' : 'Write a review'; ?>
            <select name="rating" class="<?php echo INPUT; ?>" required>
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?php echo $i; ?>" <?php echo (int) ($myReview['rating'] ?? 5) === $i ? 'selected' : ''; ?>><?php echo str_repeat('★', $i) . str_repeat('☆', 5 - $i); ?></option>
              <?php endfor; ?>
            </select>
          </label>
          <textarea name="comment" rows="3" maxlength="1000" placeholder="How was your stay?" class="<?php echo INPUT; ?>"><?php echo e($myReview['comment'] ?? ''); ?></textarea>
          <button type="submit" class="<?php echo BTN_PRIMARY; ?>">Submit review</button>
        </form>
      <?php endif; ?>
      <?php if (!$reviews): ?>
        <p class="mt-3 text-sm text-slate-500">No reviews yet.</p>
      <?php endif; ?>
      <div class="mt-4 space-y-3">
        <?php foreach ($reviews as $review): ?>
          <div class="rounded-2xl border border-slate-100 p-4 dark:border-slate-800">
            <div class="flex items-center justify-between">
              <p class="text-sm font-semibold"><?php echo e(fullName($review)); ?></p>
              <p class="text-sm text-amber-500"><?php echo str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']); ?></p>
            </div>
            <?php if ($review['comment']): ?><p class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?php echo e($review['comment']); ?></p><?php endif; ?>
            <p class="mt-2 text-xs text-slate-400"><?php echo e(date('M j, Y', strtotime((string) $review['created_at']))); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <aside class="space-y-6">
    <section class="<?php echo CARD; ?> lg:sticky lg:top-24">
      <p><span class="font-display text-3xl font-bold text-slate-900 dark:text-white"><?php echo e(money($room['monthly_rent'])); ?></span> <span class="text-sm text-slate-500">/ month</span></p>

      <?php if (!isLoggedIn()): ?>
        <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">Log in as a tenant to request this room and contact the landlord.</p>
        <a href="../html/loginform.html" class="<?php echo BTN_PRIMARY; ?> mt-4 w-full">Log in to book</a>
        <a href="../html/account-type.html" class="<?php echo BTN_SECONDARY; ?> mt-2 w-full">Create an account</a>
      <?php elseif (!$isTenant): ?>
        <p class="mt-4 text-sm text-slate-500">Only tenant accounts can book rooms.</p>
      <?php elseif ($hasOpenBooking): ?>
        <div class="mt-4 rounded-2xl bg-emerald-50 p-4 text-sm dark:bg-emerald-500/10">
          <p class="font-semibold">Your booking: <?php echo statusBadge($booking['status']); ?></p>
          <p class="mt-1 text-slate-600 dark:text-slate-300">Move-in: <?php echo e(date('M j, Y', strtotime((string) $booking['move_in_date']))); ?></p>
          <?php if ($booking['landlord_note']): ?><p class="mt-1 text-slate-600 dark:text-slate-300">Landlord: “<?php echo e($booking['landlord_note']); ?>”</p><?php endif; ?>
          <a href="dashboard.php" class="mt-2 inline-block font-semibold text-emerald-700 hover:underline">View in My Bookings →</a>
        </div>
      <?php elseif (!$isOpen): ?>
        <p class="mt-4 rounded-2xl bg-slate-100 p-4 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">This room is currently full. Save it to your favorites and check back later.</p>
      <?php else: ?>
        <form method="post" class="mt-4 space-y-3">
          <?php echo csrfInput(); ?>
          <input type="hidden" name="action" value="book" />
          <input type="hidden" name="room_id" value="<?php echo $roomId; ?>" />
          <label class="<?php echo LABEL; ?>">Preferred move-in date
            <input type="date" name="move_in_date" required min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" class="<?php echo INPUT; ?>" />
          </label>
          <label class="<?php echo LABEL; ?>">Message to landlord <span class="font-normal text-slate-400">(optional)</span>
            <textarea name="message" rows="3" maxlength="1000" placeholder="Introduce yourself, your school, and any questions." class="<?php echo INPUT; ?>"></textarea>
          </label>
          <button type="submit" class="<?php echo BTN_PRIMARY; ?> w-full">Request to book</button>
          <p class="text-xs text-slate-500">No payment is taken here. The landlord will confirm your request.</p>
        </form>
      <?php endif; ?>

      <?php if ($isTenant): ?>
        <form method="post" action="favorite.php" class="mt-3">
          <?php echo csrfInput(); ?>
          <input type="hidden" name="room_id" value="<?php echo $roomId; ?>" />
          <input type="hidden" name="return" value="../php/<?php echo e($self); ?>" />
          <button type="submit" class="<?php echo BTN_SECONDARY; ?> w-full"><?php echo $isFavorite ? '♥ Saved to favorites' : '♡ Save to favorites'; ?></button>
        </form>
      <?php endif; ?>

      <div class="mt-6 border-t border-slate-100 pt-4 dark:border-slate-800">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Landlord</p>
        <p class="mt-1 font-semibold"><?php echo e($landlordName); ?> <span class="text-xs font-semibold text-emerald-600">✓ Verified</span></p>
        <?php if (isLoggedIn()): ?>
          <?php $phone = $room['contact_phone'] ?: $room['landlord_phone']; ?>
          <?php if ($phone): ?><p class="mt-1 text-sm">📞 <a href="tel:<?php echo e($phone); ?>" class="hover:underline"><?php echo e($phone); ?></a></p><?php endif; ?>
          <p class="mt-1 text-sm">✉️ <a href="mailto:<?php echo e($room['landlord_email']); ?>" class="hover:underline"><?php echo e($room['landlord_email']); ?></a></p>
        <?php else: ?>
          <p class="mt-1 text-sm text-slate-500">Log in to see contact details.</p>
        <?php endif; ?>
      </div>
    </section>
  </aside>
</div>
<?php
pageEnd();
