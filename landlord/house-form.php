<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireRole([ROLE_LANDLORD]);
$landlordId = (int) currentUserId();
$pdo = getDb();

$houseId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$house = $houseId > 0 ? landlordHouse($houseId, $landlordId) : null;
if ($houseId > 0 && !$house) {
    flash('error', 'Boarding house not found.');
    redirect('dashboard.php');
}

$fields = [
    'name' => '', 'description' => '', 'address' => '', 'barangay' => '', 'city' => '', 'province' => '',
    'nearby_school' => '', 'distance_to_school' => '', 'gender_policy' => 'any', 'house_rules' => '',
    'contact_phone' => '', 'status' => 'active', 'latitude' => '', 'longitude' => '',
];
$values = $house ? array_intersect_key($house, $fields) + $fields : $fields;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidPost('house-form.php' . ($houseId ? '?id=' . $houseId : ''));

    $values = [
        'name' => postString('name', 150),
        'description' => postString('description', 3000),
        'address' => postString('address', 255),
        'barangay' => postString('barangay', 100),
        'city' => postString('city', 100),
        'province' => postString('province', 100),
        'nearby_school' => postString('nearby_school', 150),
        'distance_to_school' => postString('distance_to_school', 30),
        'gender_policy' => in_array($_POST['gender_policy'] ?? '', ['any', 'male', 'female'], true) ? $_POST['gender_policy'] : 'any',
        'house_rules' => postString('house_rules', 3000),
        'contact_phone' => postString('contact_phone', 20),
        'status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
        'latitude' => postString('latitude', 40),
        'longitude' => postString('longitude', 20),
    ];

    // Accept "9.7608, 126.0490" pasted into the latitude box (Google Maps format).
    if ($values['longitude'] === '' && str_contains($values['latitude'], ',')) {
        [$values['latitude'], $values['longitude']] = array_map('trim', explode(',', $values['latitude'], 2));
    }
    if ($values['latitude'] !== '' || $values['longitude'] !== '') {
        $lat = filter_var($values['latitude'], FILTER_VALIDATE_FLOAT);
        $lng = filter_var($values['longitude'], FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $errors[] = 'Map location must be a valid latitude and longitude, e.g. 9.7608 and 126.0490.';
        }
    }

    foreach (['name' => 'Name', 'address' => 'Street address', 'barangay' => 'Barangay', 'city' => 'City/municipality', 'province' => 'Province'] as $key => $label) {
        if ($values[$key] === '') {
            $errors[] = $label . ' is required.';
        }
    }
    if ($values['contact_phone'] !== '' && !validatePhoneValue($values['contact_phone'])['valid']) {
        $errors[] = 'Contact number must be a valid PH mobile number (09XXXXXXXXX).';
    }

    if (!$errors) {
        $params = [];
        foreach ($values as $key => $value) {
            $params[':' . $key] = $value === '' && !in_array($key, ['name', 'address', 'barangay', 'city', 'province'], true) ? null : $value;
        }
        if ($house) {
            $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($values)));
            $params[':id'] = $houseId;
            $params[':landlord'] = $landlordId;
            $pdo->prepare("UPDATE boarding_houses SET $sets WHERE id = :id AND landlord_id = :landlord")->execute($params);
            flash('success', 'Boarding house updated.');
            redirect('dashboard.php');
        }

        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_map(static fn ($k) => ":$k", array_keys($values)));
        $params[':landlord'] = $landlordId;
        $pdo->prepare("INSERT INTO boarding_houses (landlord_id, $columns) VALUES (:landlord, $placeholders)")->execute($params);
        $newId = (int) $pdo->lastInsertId();
        auditLog('house_create', 'Boarding house #' . $newId . ' created', $landlordId);
        flash('success', 'Boarding house added. Now add its rooms.');
        redirect('room-form.php?house_id=' . $newId);
    }
}

pageStart($house ? 'Edit Boarding House' : 'Add Boarding House', 'landlord');
?>
<a href="dashboard.php" class="text-sm font-semibold text-emerald-600 hover:underline">← Back to my listings</a>
<h1 class="mt-3 font-display text-2xl font-bold text-slate-900 dark:text-white"><?php echo $house ? 'Edit boarding house' : 'Add a boarding house'; ?></h1>

<?php if ($errors): ?>
  <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
    <?php foreach ($errors as $error): ?><p><?php echo e($error); ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" class="<?php echo CARD; ?> mt-6 space-y-5">
  <?php echo csrfInput(); ?>
  <input type="hidden" name="id" value="<?php echo $houseId; ?>" />

  <label class="<?php echo LABEL; ?>">Boarding house name *
    <input name="name" required maxlength="150" value="<?php echo e($values['name']); ?>" class="<?php echo INPUT; ?>" placeholder="e.g. Santos Boarding House" />
  </label>
  <label class="<?php echo LABEL; ?>">Description
    <textarea name="description" rows="4" maxlength="3000" class="<?php echo INPUT; ?>" placeholder="What makes your place great for students?"><?php echo e($values['description']); ?></textarea>
  </label>

  <div class="grid gap-4 sm:grid-cols-2">
    <label class="<?php echo LABEL; ?>">Street address *
      <input name="address" required maxlength="255" value="<?php echo e($values['address']); ?>" class="<?php echo INPUT; ?>" placeholder="Block/Lot, Street" />
    </label>
    <label class="<?php echo LABEL; ?>">Barangay *
      <input name="barangay" required maxlength="100" value="<?php echo e($values['barangay']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">City / Municipality *
      <input name="city" required maxlength="100" value="<?php echo e($values['city']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">Province *
      <input name="province" required maxlength="100" value="<?php echo e($values['province']); ?>" class="<?php echo INPUT; ?>" />
    </label>
    <label class="<?php echo LABEL; ?>">Nearest school
      <input name="nearby_school" maxlength="150" value="<?php echo e($values['nearby_school']); ?>" class="<?php echo INPUT; ?>" placeholder="e.g. SIIT" />
    </label>
    <label class="<?php echo LABEL; ?>">Distance to school
      <input name="distance_to_school" maxlength="30" value="<?php echo e($values['distance_to_school']); ?>" class="<?php echo INPUT; ?>" placeholder="e.g. 500 m or 5-min walk" />
    </label>
    <label class="<?php echo LABEL; ?>">Accepts
      <select name="gender_policy" class="<?php echo INPUT; ?>">
        <?php foreach (['any' => 'Male and female', 'male' => 'Male only', 'female' => 'Female only'] as $value => $label): ?>
          <option value="<?php echo $value; ?>" <?php echo $values['gender_policy'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="<?php echo LABEL; ?>">Contact number for tenants
      <input name="contact_phone" maxlength="20" value="<?php echo e($values['contact_phone']); ?>" class="<?php echo INPUT; ?>" placeholder="09XXXXXXXXX" />
    </label>
  </div>

  <fieldset>
    <legend class="<?php echo LABEL; ?>">Map location <span class="font-normal text-slate-400">(so tenants can see it on the SIIT map)</span></legend>
    <p class="mt-1 text-xs text-slate-500">In Google Maps, right-click your boarding house and click the numbers at the top to copy them, then paste into Latitude.</p>
    <div class="mt-2 grid gap-4 sm:grid-cols-2">
      <label class="<?php echo LABEL; ?>">Latitude
        <input name="latitude" maxlength="40" value="<?php echo e($values['latitude']); ?>" class="<?php echo INPUT; ?>" placeholder="e.g. 9.7608" />
      </label>
      <label class="<?php echo LABEL; ?>">Longitude
        <input name="longitude" maxlength="20" value="<?php echo e($values['longitude']); ?>" class="<?php echo INPUT; ?>" placeholder="e.g. 126.0490" />
      </label>
    </div>
  </fieldset>

  <label class="<?php echo LABEL; ?>">House rules <span class="font-normal text-slate-400">(one per line)</span>
    <textarea name="house_rules" rows="4" maxlength="3000" class="<?php echo INPUT; ?>" placeholder="No smoking&#10;Curfew at 10 PM"><?php echo e($values['house_rules']); ?></textarea>
  </label>

  <label class="<?php echo LABEL; ?>">Listing status
    <select name="status" class="<?php echo INPUT; ?>">
      <option value="active" <?php echo $values['status'] === 'active' ? 'selected' : ''; ?>>Active — visible to tenants</option>
      <option value="inactive" <?php echo $values['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive — hidden from tenants</option>
    </select>
  </label>

  <div class="flex gap-2">
    <button type="submit" class="<?php echo BTN_PRIMARY; ?>"><?php echo $house ? 'Save changes' : 'Add boarding house'; ?></button>
    <a href="dashboard.php" class="<?php echo BTN_SECONDARY; ?>">Cancel</a>
  </div>
</form>
<?php
pageEnd();
