<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../security/upload_security.php';

requireRole([ROLE_LANDLORD]);
$landlordId = (int) currentUserId();
$pdo = getDb();

const MAX_ROOM_PHOTOS = 8;
const ROOM_PHOTO_DIR = BH_UPLOAD_DIR . '/rooms';

$roomId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$room = $roomId > 0 ? landlordRoom($roomId, $landlordId) : null;
if ($roomId > 0 && !$room) {
    flash('error', 'Room not found.');
    redirect('dashboard.php');
}

$houseId = $room ? (int) $room['boarding_house_id'] : (int) ($_GET['house_id'] ?? $_POST['house_id'] ?? 0);
$house = landlordHouse($houseId, $landlordId);
if (!$house) {
    flash('error', 'Please choose one of your boarding houses first.');
    redirect('dashboard.php');
}

$self = $room ? 'room-form.php?id=' . $roomId : 'room-form.php?house_id=' . $houseId;

function deletePhotoFile(string $path): void {
    if (str_starts_with($path, 'uploads/rooms/')) {
        @unlink(BH_SYSTEM_ROOT . '/' . $path);
    }
}

/** Saves uploaded photos for a room; returns error messages. */
function saveRoomPhotos(PDO $pdo, int $roomId, array $files): array {
    $errors = [];
    $count = (int) $pdo->query('SELECT COUNT(*) FROM room_photos WHERE room_id = ' . $roomId)->fetchColumn();
    $insert = $pdo->prepare('INSERT INTO room_photos (room_id, path, sort_order) VALUES (:room, :path, :sort)');
    foreach ($files as $file) {
        if ($count >= MAX_ROOM_PHOTOS) {
            $errors[] = 'Only ' . MAX_ROOM_PHOTOS . ' photos are allowed per room.';
            break;
        }
        $upload = storeUploadedFile($file, ROOM_PHOTO_DIR, IMAGE_MIME_TYPES);
        if (!$upload['success']) {
            $errors[] = ($file['name'] ?? 'Photo') . ': ' . $upload['message'];
            continue;
        }
        $insert->execute([':room' => $roomId, ':path' => 'uploads/rooms/' . $upload['file_name'], ':sort' => $count]);
        $count++;
    }
    return $errors;
}

$defaults = [
    'name' => '', 'room_type' => 'shared', 'monthly_rent' => '', 'deposit' => '', 'advance_payment' => '',
    'capacity' => 1, 'available_slots' => 1, 'size_sqm' => '', 'amenities' => '', 'description' => '', 'status' => 'available',
];
$values = $room ? array_intersect_key($room, $defaults) + $defaults : $defaults;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidPost($self);
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'delete_photo' && $room) {
        $stmt = $pdo->prepare('SELECT path FROM room_photos WHERE id = :id AND room_id = :room');
        $stmt->execute([':id' => (int) ($_POST['photo_id'] ?? 0), ':room' => $roomId]);
        if ($path = $stmt->fetchColumn()) {
            $pdo->prepare('DELETE FROM room_photos WHERE id = :id')->execute([':id' => (int) $_POST['photo_id']]);
            deletePhotoFile((string) $path);
            flash('success', 'Photo removed.');
        }
        redirect($self);
    }

    if ($action === 'make_cover' && $room) {
        $pdo->prepare('UPDATE room_photos SET sort_order = sort_order + 1 WHERE room_id = :room')->execute([':room' => $roomId]);
        $pdo->prepare('UPDATE room_photos SET sort_order = 0 WHERE id = :id AND room_id = :room')->execute([':id' => (int) ($_POST['photo_id'] ?? 0), ':room' => $roomId]);
        flash('success', 'Cover photo updated.');
        redirect($self);
    }

    if ($action === 'delete_room' && $room) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = :room AND status IN ('pending', 'approved')");
        $stmt->execute([':room' => $roomId]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash('error', 'This room has pending or approved bookings. Set its status to Hidden instead of deleting it.');
            redirect($self);
        }
        foreach (roomPhotos($roomId) as $photo) {
            deletePhotoFile($photo['path']);
        }
        $pdo->prepare('DELETE FROM rooms WHERE id = :id')->execute([':id' => $roomId]);
        auditLog('room_delete', 'Room #' . $roomId . ' deleted', $landlordId);
        flash('success', 'Room deleted.');
        redirect('dashboard.php');
    }

    $amenities = array_filter(array_map('trim', (array) ($_POST['amenities'] ?? [])), static fn ($a) => in_array($a, COMMON_AMENITIES, true));
    foreach (explode(',', postString('other_amenities', 300)) as $extra) {
        $extra = trim(str_replace(',', '', $extra));
        if ($extra !== '') {
            $amenities[] = mb_substr($extra, 0, 40);
        }
    }

    $values = [
        'name' => postString('name', 150),
        'room_type' => isset(ROOM_TYPES[$_POST['room_type'] ?? '']) ? $_POST['room_type'] : 'shared',
        'monthly_rent' => postMoney('monthly_rent'),
        'deposit' => postMoney('deposit'),
        'advance_payment' => postMoney('advance_payment'),
        'capacity' => max(1, min(50, (int) ($_POST['capacity'] ?? 1))),
        'available_slots' => max(0, (int) ($_POST['available_slots'] ?? 0)),
        'size_sqm' => ($_POST['size_sqm'] ?? '') === '' ? null : max(0, min(999, (float) $_POST['size_sqm'])),
        'amenities' => implode(',', array_unique($amenities)),
        'description' => postString('description', 3000),
        'status' => isset(ROOM_STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : 'available',
    ];

    if ($values['name'] === '') {
        $errors[] = 'Room name is required.';
    }
    if ($values['monthly_rent'] <= 0) {
        $errors[] = 'Monthly rent must be more than zero.';
    }
    if ($values['available_slots'] > $values['capacity']) {
        $errors[] = 'Open slots cannot be more than the room capacity.';
    }
    if ($values['status'] === 'available' && $values['available_slots'] === 0) {
        $values['status'] = 'full';
    }

    $photos = normalizeUploadedFiles($_FILES['photos'] ?? null);
    if (!$room && !$photos) {
        $errors[] = 'Please add at least one photo of the room.';
    }

    if (!$errors) {
        $params = [];
        foreach ($values as $key => $value) {
            $params[':' . $key] = $value;
        }
        if ($room) {
            $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($values)));
            $params[':id'] = $roomId;
            $pdo->prepare("UPDATE rooms SET $sets WHERE id = :id")->execute($params);
            $savedId = $roomId;
        } else {
            $columns = implode(', ', array_keys($values));
            $placeholders = implode(', ', array_map(static fn ($k) => ":$k", array_keys($values)));
            $params[':house'] = $houseId;
            $pdo->prepare("INSERT INTO rooms (boarding_house_id, $columns) VALUES (:house, $placeholders)")->execute($params);
            $savedId = (int) $pdo->lastInsertId();
            auditLog('room_create', 'Room #' . $savedId . ' created', $landlordId);
        }

        $photoErrors = saveRoomPhotos($pdo, $savedId, $photos);
        foreach ($photoErrors as $message) {
            flash('error', $message);
        }
        flash('success', $room ? 'Room updated.' : 'Room added.');
        redirect($photoErrors ? 'room-form.php?id=' . $savedId : 'dashboard.php');
    }
}

$selectedAmenities = amenityList(is_string($values['amenities']) ? $values['amenities'] : '');
$otherAmenities = array_diff($selectedAmenities, COMMON_AMENITIES);
$photos = $room ? roomPhotos($roomId) : [];

pageStart($room ? 'Edit Room' : 'Add Room', 'landlord');
?>
<a href="dashboard.php" class="text-sm font-semibold text-emerald-600 hover:underline">← Back to my listings</a>
<h1 class="mt-3 font-display text-2xl font-bold text-slate-900 dark:text-white"><?php echo $room ? 'Edit room' : 'Add a room'; ?></h1>
<p class="text-sm text-slate-500">at <?php echo e($house['name']); ?></p>

<?php if ($errors): ?>
  <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
    <?php foreach ($errors as $error): ?><p><?php echo e($error); ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="<?php echo CARD; ?> mt-6 space-y-5">
  <?php echo csrfInput(); ?>
  <input type="hidden" name="id" value="<?php echo $roomId; ?>" />
  <input type="hidden" name="house_id" value="<?php echo $houseId; ?>" />
  <input type="hidden" name="action" value="save" />

  <div class="grid gap-4 sm:grid-cols-2">
    <label class="<?php echo LABEL; ?>">Room name *
      <input name="name" required maxlength="150" value="<?php echo e($values['name']); ?>" class="<?php echo INPUT; ?>" placeholder="e.g. Room 3 – Shared, 2nd floor" />
    </label>
    <label class="<?php echo LABEL; ?>">Room type *
      <select name="room_type" class="<?php echo INPUT; ?>">
        <?php foreach (ROOM_TYPES as $value => $label): ?>
          <option value="<?php echo e($value); ?>" <?php echo $values['room_type'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div class="grid gap-4 sm:grid-cols-3">
    <label class="<?php echo LABEL; ?>">Monthly rent (₱) *
      <input type="number" name="monthly_rent" required min="1" step="1" value="<?php echo e($values['monthly_rent']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">Deposit (₱)
      <input type="number" name="deposit" min="0" step="1" value="<?php echo e($values['deposit']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">Advance payment (₱)
      <input type="number" name="advance_payment" min="0" step="1" value="<?php echo e($values['advance_payment']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">Capacity (persons) *
      <input type="number" name="capacity" required min="1" max="50" value="<?php echo e($values['capacity']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">Open slots *
      <input type="number" name="available_slots" required min="0" max="50" value="<?php echo e($values['available_slots']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">Size (sqm)
      <input type="number" name="size_sqm" min="0" step="0.5" value="<?php echo e($values['size_sqm']); ?>" class="<?php echo INPUT; ?>" />
    </label>
  </div>

  <fieldset>
    <legend class="<?php echo LABEL; ?>">Amenities</legend>
    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
      <?php foreach (COMMON_AMENITIES as $amenity): ?>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="amenities[]" value="<?php echo e($amenity); ?>" <?php echo in_array($amenity, $selectedAmenities, true) ? 'checked' : ''; ?> class="h-4 w-4 rounded text-emerald-600" />
          <?php echo e($amenity); ?>
        </label>
      <?php endforeach; ?>
    </div>
    <input name="other_amenities" maxlength="300" value="<?php echo e(implode(', ', $otherAmenities)); ?>" class="<?php echo INPUT; ?> mt-3" placeholder="Other amenities, separated by commas" />
  </fieldset>

  <label class="<?php echo LABEL; ?>">Description
    <textarea name="description" rows="4" maxlength="3000" class="<?php echo INPUT; ?>" placeholder="Describe the room, what's included, and who it's best for."><?php echo e($values['description']); ?></textarea>
  </label>

  <label class="<?php echo LABEL; ?>">Status
    <select name="status" class="<?php echo INPUT; ?>">
      <option value="available" <?php echo $values['status'] === 'available' ? 'selected' : ''; ?>>Available — accepting bookings</option>
      <option value="full" <?php echo $values['status'] === 'full' ? 'selected' : ''; ?>>Full — visible but not bookable</option>
      <option value="hidden" <?php echo $values['status'] === 'hidden' ? 'selected' : ''; ?>>Hidden — not visible to tenants</option>
    </select>
  </label>

  <label class="<?php echo LABEL; ?>">Add photos <?php echo $room ? '' : '*'; ?> <span class="font-normal text-slate-400">(JPG, PNG or WEBP, up to 5MB each, max <?php echo MAX_ROOM_PHOTOS; ?>)</span>
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple <?php echo $room ? '' : 'required'; ?> class="<?php echo INPUT; ?>" />
  </label>

  <div class="flex flex-wrap gap-2">
    <button type="submit" class="<?php echo BTN_PRIMARY; ?>"><?php echo $room ? 'Save changes' : 'Add room'; ?></button>
    <a href="dashboard.php" class="<?php echo BTN_SECONDARY; ?>">Cancel</a>
  </div>
</form>

<?php if ($room): ?>
  <section class="<?php echo CARD; ?> mt-6">
    <h2 class="font-display text-lg font-semibold">Photos</h2>
    <p class="text-sm text-slate-500">The first photo is used as the cover.</p>
    <?php if (!$photos): ?><p class="mt-3 text-sm text-slate-500">No photos yet.</p><?php endif; ?>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
      <?php foreach ($photos as $index => $photo): ?>
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
          <img src="<?php echo e(photoUrl($photo['path'])); ?>" alt="" class="h-32 w-full object-cover" />
          <div class="flex items-center justify-between gap-1 p-2">
            <?php if ($index === 0): ?>
              <span class="text-xs font-semibold text-emerald-600">Cover</span>
            <?php else: ?>
              <form method="post"><?php echo csrfInput(); ?><input type="hidden" name="id" value="<?php echo $roomId; ?>" /><input type="hidden" name="action" value="make_cover" /><input type="hidden" name="photo_id" value="<?php echo (int) $photo['id']; ?>" /><button class="text-xs font-semibold text-slate-600 hover:underline dark:text-slate-300">Make cover</button></form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Remove this photo?');"><?php echo csrfInput(); ?><input type="hidden" name="id" value="<?php echo $roomId; ?>" /><input type="hidden" name="action" value="delete_photo" /><input type="hidden" name="photo_id" value="<?php echo (int) $photo['id']; ?>" /><button class="text-xs font-semibold text-rose-600 hover:underline">Remove</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="<?php echo CARD; ?> mt-6">
    <h2 class="font-display text-lg font-semibold text-rose-600">Delete room</h2>
    <p class="mt-1 text-sm text-slate-500">Permanently removes this room, its photos, and its booking history.</p>
    <form method="post" class="mt-3" onsubmit="return confirm('Delete this room permanently?');">
      <?php echo csrfInput(); ?>
      <input type="hidden" name="id" value="<?php echo $roomId; ?>" />
      <input type="hidden" name="action" value="delete_room" />
      <button type="submit" class="<?php echo BTN_DANGER; ?>">Delete room</button>
    </form>
  </section>
<?php endif; ?>
<?php
pageEnd();
