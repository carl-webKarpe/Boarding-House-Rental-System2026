<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../security/auth.php';

requireLogin();
$userId = (int) currentUserId();
$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidPost('profile.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $firstName = postString('first_name', 80);
        $lastName = postString('last_name', 80);
        $phone = validatePhoneValue($_POST['phone'] ?? '');
        if ($firstName === '' || $lastName === '') {
            flash('error', 'First and last name are required.');
        } elseif (!$phone['valid']) {
            flash('error', $phone['message']);
        } else {
            $pdo->prepare('UPDATE users SET first_name = :first, middle_name = :middle, last_name = :last, phone = :phone, address = :address WHERE id = :id')
                ->execute([
                    ':first' => $firstName,
                    ':middle' => postString('middle_name', 80) ?: null,
                    ':last' => $lastName,
                    ':phone' => $phone['value'],
                    ':address' => postString('address', 255) ?: null,
                    ':id' => $userId,
                ]);
            flash('success', 'Profile updated.');
        }
    }

    if ($action === 'password') {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $stmt->fetchColumn())) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($new !== (string) ($_POST['confirm_password'] ?? '')) {
            flash('error', 'New passwords do not match.');
        } elseif (!($check = validatePasswordValue($new))['valid']) {
            flash('error', $check['message']);
        } elseif (changePassword($userId, $new)) {
            session_regenerate_id(true);
            flash('success', 'Password changed.');
        } else {
            flash('error', 'Password could not be changed.');
        }
    }
    redirect('profile.php');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

pageStart('My Profile');
?>
<h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">My Profile</h1>
<p class="text-sm text-slate-500"><?php echo e($user['email']); ?> · @<?php echo e($user['username']); ?> · <?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?></p>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <form method="post" class="<?php echo CARD; ?> space-y-4">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="action" value="profile" />
    <h2 class="font-display text-lg font-semibold">Personal details</h2>
    <div class="grid gap-3 sm:grid-cols-3">
      <label class="<?php echo LABEL; ?>">First name<input name="first_name" required maxlength="80" value="<?php echo e($user['first_name']); ?>" class="<?php echo INPUT; ?>" /></label>
      <label class="<?php echo LABEL; ?>">Middle name<input name="middle_name" maxlength="80" value="<?php echo e($user['middle_name']); ?>" class="<?php echo INPUT; ?>" /></label>
      <label class="<?php echo LABEL; ?>">Last name<input name="last_name" required maxlength="80" value="<?php echo e($user['last_name']); ?>" class="<?php echo INPUT; ?>" /></label>
    </div>
    <label class="<?php echo LABEL; ?>">Mobile number<input name="phone" required placeholder="09XXXXXXXXX" value="<?php echo e($user['phone']); ?>" class="<?php echo INPUT; ?>" /></label>
    <label class="<?php echo LABEL; ?>">Address<input name="address" maxlength="255" value="<?php echo e($user['address']); ?>" class="<?php echo INPUT; ?>" /></label>
    <button type="submit" class="<?php echo BTN_PRIMARY; ?>">Save changes</button>
  </form>

  <form method="post" class="<?php echo CARD; ?> space-y-4">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="action" value="password" />
    <h2 class="font-display text-lg font-semibold">Change password</h2>
    <label class="<?php echo LABEL; ?>">Current password<input type="password" name="current_password" required autocomplete="current-password" class="<?php echo INPUT; ?>" /></label>
    <label class="<?php echo LABEL; ?>">New password<input type="password" name="new_password" required autocomplete="new-password" class="<?php echo INPUT; ?>" /></label>
    <label class="<?php echo LABEL; ?>">Confirm new password<input type="password" name="confirm_password" required autocomplete="new-password" class="<?php echo INPUT; ?>" /></label>
    <p class="text-xs text-slate-500">At least 8 characters with uppercase, lowercase, a number, and a symbol.</p>
    <button type="submit" class="<?php echo BTN_PRIMARY; ?>">Change password</button>
  </form>
</div>
<?php
pageEnd();
