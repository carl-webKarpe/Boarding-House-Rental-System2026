<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireRole(ADMIN_ROLES);
$pdo = getDb();
$userId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT u.*, lp.property_name, lp.business_address, lp.barangay, lp.municipality, lp.province FROM users u LEFT JOIN landlord_profiles lp ON lp.user_id = u.id WHERE u.id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    flash('error', 'User not found.');
    redirect('users.php');
}

$stmt = $pdo->prepare('SELECT id, doc_type, original_name, mime_type, created_at FROM user_documents WHERE user_id = :id ORDER BY id');
$stmt->execute([':id' => $userId]);
$documents = $stmt->fetchAll();

$houses = $user['role'] === ROLE_LANDLORD ? landlordHouses($userId) : [];

$docLabels = [
    'government_id' => 'Government ID', 'student_id' => 'Student ID', 'selfie' => 'Selfie holding ID',
    'business_permit' => 'Business permit', 'proof_of_ownership' => 'Proof of ownership',
];
$self = '../admin/user.php?id=' . $userId;
$isSelf = $userId === currentUserId();

function adminActionButton(int $userId, string $action, string $label, string $class, string $return): void {
    ?>
  <form method="post" action="actions.php">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="user_id" value="<?php echo $userId; ?>" />
    <input type="hidden" name="action" value="<?php echo e($action); ?>" />
    <input type="hidden" name="return" value="<?php echo e($return); ?>" />
    <button type="submit" class="<?php echo $class; ?>"><?php echo e($label); ?></button>
  </form>
<?php
}

pageStart(fullName($user), 'users');
?>
<a href="users.php" class="text-sm font-semibold text-emerald-600 hover:underline">← All users</a>

<div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
  <section class="<?php echo CARD; ?>">
    <div class="flex flex-wrap items-center gap-2">
      <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white"><?php echo e(fullName($user)); ?></h1>
      <?php echo statusBadge($user['approval_status']); ?>
      <?php echo statusBadge((int) $user['is_active'] === 1 ? 'active' : 'inactive'); ?>
    </div>
    <p class="text-sm text-slate-500">@<?php echo e($user['username']); ?> · <?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?></p>

    <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
      <?php foreach ([
          'Email' => $user['email'],
          'Mobile' => $user['phone'],
          'Gender' => $user['gender'] ? ucwords(str_replace('_', ' ', $user['gender'])) : null,
          'Birth date' => $user['birth_date'] ? date('M j, Y', strtotime((string) $user['birth_date'])) : null,
          'Address' => $user['address'],
          'Registered' => date('M j, Y g:i A', strtotime((string) $user['created_at'])),
      ] as $label => $value): ?>
        <div><dt class="text-xs text-slate-500"><?php echo e($label); ?></dt><dd class="font-medium"><?php echo e($value ?: '—'); ?></dd></div>
      <?php endforeach; ?>
    </dl>

    <?php if ($user['role'] === ROLE_LANDLORD): ?>
      <h2 class="mt-6 font-display text-lg font-semibold">Business details</h2>
      <dl class="mt-2 grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="text-xs text-slate-500">Boarding house name</dt><dd class="font-medium"><?php echo e($user['property_name'] ?: '—'); ?></dd></div>
        <div><dt class="text-xs text-slate-500">Business address</dt><dd class="font-medium"><?php echo e(implode(', ', array_filter([$user['business_address'], $user['barangay'], $user['municipality'], $user['province']])) ?: '—'); ?></dd></div>
      </dl>
      <h2 class="mt-6 font-display text-lg font-semibold">Listings</h2>
      <?php if (!$houses): ?><p class="mt-2 text-sm text-slate-500">No boarding houses yet.</p><?php endif; ?>
      <ul class="mt-2 space-y-1 text-sm">
        <?php foreach ($houses as $house): ?>
          <li><?php echo e($house['name']); ?> — <?php echo (int) $house['room_count']; ?> room(s) <?php echo statusBadge($house['status']); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h2 class="mt-6 font-display text-lg font-semibold">Uploaded documents</h2>
    <?php if (!$documents): ?><p class="mt-2 text-sm text-slate-500">No documents uploaded.</p><?php endif; ?>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
      <?php foreach ($documents as $doc): ?>
        <a href="document.php?id=<?php echo (int) $doc['id']; ?>" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl border border-slate-200 hover:border-emerald-400 dark:border-slate-700">
          <?php if (str_starts_with($doc['mime_type'], 'image/')): ?>
            <img src="document.php?id=<?php echo (int) $doc['id']; ?>" alt="" class="h-40 w-full bg-slate-100 object-contain dark:bg-slate-800" />
          <?php else: ?>
            <div class="grid h-40 place-items-center bg-slate-100 text-sm font-semibold text-slate-500 dark:bg-slate-800">📄 PDF document</div>
          <?php endif; ?>
          <p class="p-2 text-sm font-semibold"><?php echo e($docLabels[$doc['doc_type']] ?? $doc['doc_type']); ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="<?php echo CARD; ?> h-fit space-y-3">
    <h2 class="font-display text-lg font-semibold">Actions</h2>
    <?php if ($isSelf): ?>
      <p class="text-sm text-slate-500">This is your own account.</p>
    <?php else: ?>
      <?php if ($user['role'] === ROLE_LANDLORD && $user['approval_status'] !== 'approved'): ?>
        <?php adminActionButton($userId, 'approve', 'Approve landlord', BTN_PRIMARY . ' w-full', $self); ?>
      <?php endif; ?>
      <?php if ($user['role'] === ROLE_LANDLORD && $user['approval_status'] !== 'rejected'): ?>
        <?php adminActionButton($userId, 'reject', $user['approval_status'] === 'approved' ? 'Revoke approval' : 'Reject landlord', BTN_DANGER . ' w-full', $self); ?>
      <?php endif; ?>
      <?php if ((int) $user['is_active'] === 1): ?>
        <?php adminActionButton($userId, 'deactivate', 'Deactivate account', BTN_SECONDARY . ' w-full', $self); ?>
      <?php else: ?>
        <?php adminActionButton($userId, 'activate', 'Activate account', BTN_SECONDARY . ' w-full', $self); ?>
      <?php endif; ?>
      <p class="text-xs text-slate-500">Deactivated users cannot log in. A landlord's listings are hidden while they are not approved or deactivated.</p>
    <?php endif; ?>
  </aside>
</div>
<?php
pageEnd();
