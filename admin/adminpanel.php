<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireRole(ADMIN_ROLES);
$pdo = getDb();

$stats = $pdo->query("
    SELECT
      (SELECT COUNT(*) FROM users WHERE role IN ('tenant', 'user')) AS tenants,
      (SELECT COUNT(*) FROM users WHERE role = 'landlord' AND approval_status = 'approved') AS landlords,
      (SELECT COUNT(*) FROM users WHERE role = 'landlord' AND approval_status = 'pending') AS pending_landlords,
      (SELECT COUNT(*) FROM boarding_houses) AS houses,
      (SELECT COUNT(*) FROM rooms) AS rooms,
      (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS pending_bookings,
      (SELECT COUNT(*) FROM bookings WHERE status = 'approved') AS approved_bookings
")->fetch();

$pending = $pdo->query("
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.created_at, lp.property_name, lp.municipality
    FROM users u LEFT JOIN landlord_profiles lp ON lp.user_id = u.id
    WHERE u.role = 'landlord' AND u.approval_status = 'pending'
    ORDER BY u.created_at")->fetchAll();

$activity = $pdo->query('
    SELECT a.action, a.details, a.created_at, u.username
    FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.id DESC LIMIT 10')->fetchAll();

pageStart('Admin Panel', 'admin');
?>
<h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">Admin Panel</h1>
<p class="text-sm text-slate-500">Overview of users, listings, and bookings.</p>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php foreach ([
      'Tenants' => $stats['tenants'], 'Approved landlords' => $stats['landlords'],
      'Boarding houses' => $stats['houses'], 'Rooms' => $stats['rooms'],
      'Landlords awaiting approval' => $stats['pending_landlords'], 'Pending booking requests' => $stats['pending_bookings'],
      'Approved bookings' => $stats['approved_bookings'],
  ] as $label => $value): ?>
    <div class="<?php echo CARD; ?>">
      <p class="text-sm text-slate-500"><?php echo e($label); ?></p>
      <p class="mt-1 font-display text-3xl font-bold text-slate-900 dark:text-white"><?php echo (int) $value; ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <section class="<?php echo CARD; ?>">
    <h2 class="font-display text-lg font-semibold">Landlords awaiting approval</h2>
    <?php if (!$pending): ?>
      <p class="mt-3 text-sm text-slate-500">Nothing to review. 🎉</p>
    <?php endif; ?>
    <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
      <?php foreach ($pending as $landlord): ?>
        <li class="flex items-center justify-between gap-3 py-3">
          <div>
            <p class="font-semibold"><?php echo e(fullName($landlord)); ?></p>
            <p class="text-sm text-slate-500"><?php echo e($landlord['property_name'] ?? '—'); ?><?php echo $landlord['municipality'] ? ' · ' . e($landlord['municipality']) : ''; ?></p>
          </div>
          <a href="user.php?id=<?php echo (int) $landlord['id']; ?>" class="<?php echo BTN_PRIMARY; ?> !py-1.5">Review</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="<?php echo CARD; ?>">
    <h2 class="font-display text-lg font-semibold">Recent activity</h2>
    <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
      <?php foreach ($activity as $item): ?>
        <li class="flex justify-between gap-3 py-2">
          <span><strong><?php echo e($item['username'] ?? 'System'); ?></strong> · <?php echo e($item['details']); ?></span>
          <span class="shrink-0 text-xs text-slate-400"><?php echo e(date('M j, g:i A', strtotime((string) $item['created_at']))); ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php
pageEnd();
