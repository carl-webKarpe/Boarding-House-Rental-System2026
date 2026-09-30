<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireRole(ADMIN_ROLES);
$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidPost('listings.php');
    $houseId = (int) ($_POST['house_id'] ?? 0);
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    $pdo->prepare('UPDATE boarding_houses SET status = :status WHERE id = :id')->execute([':status' => $status, ':id' => $houseId]);
    auditLog('admin_house_' . $status, 'Boarding house #' . $houseId . ' set ' . $status, currentUserId());
    flash('success', $status === 'active' ? 'Listing is visible again.' : 'Listing hidden from tenants.');
    redirect('listings.php');
}

$houses = $pdo->query("
    SELECT bh.*, u.username, u.first_name, u.last_name, u.approval_status,
           (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id) AS room_count,
           (SELECT MIN(r.monthly_rent) FROM rooms r WHERE r.boarding_house_id = bh.id) AS min_rent
    FROM boarding_houses bh JOIN users u ON u.id = bh.landlord_id
    ORDER BY bh.created_at DESC")->fetchAll();

pageStart('Listings', 'listings');
?>
<h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">Boarding house listings</h1>
<p class="text-sm text-slate-500">Hide a listing if it breaks the rules or has wrong information.</p>

<section class="<?php echo CARD; ?> mt-6 overflow-x-auto">
  <table class="w-full min-w-[720px] text-left text-sm">
    <thead class="text-xs uppercase text-slate-500">
      <tr><th class="py-2">Boarding house</th><th>Landlord</th><th>Rooms</th><th>From</th><th>Status</th><th></th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
      <?php foreach ($houses as $house): ?>
        <tr>
          <td class="py-3"><p class="font-semibold"><?php echo e($house['name']); ?></p><p class="text-xs text-slate-500"><?php echo e($house['barangay']); ?>, <?php echo e($house['city']); ?></p></td>
          <td><a href="user.php?id=<?php echo (int) $house['landlord_id']; ?>" class="hover:underline"><?php echo e(fullName($house)); ?></a> <?php echo $house['approval_status'] !== 'approved' ? statusBadge($house['approval_status']) : ''; ?></td>
          <td><?php echo (int) $house['room_count']; ?></td>
          <td><?php echo $house['min_rent'] !== null ? e(money($house['min_rent'])) : '—'; ?></td>
          <td><?php echo statusBadge($house['status']); ?></td>
          <td class="text-right">
            <form method="post">
              <?php echo csrfInput(); ?>
              <input type="hidden" name="house_id" value="<?php echo (int) $house['id']; ?>" />
              <input type="hidden" name="status" value="<?php echo $house['status'] === 'active' ? 'inactive' : 'active'; ?>" />
              <button class="font-semibold <?php echo $house['status'] === 'active' ? 'text-rose-600' : 'text-emerald-600'; ?> hover:underline"><?php echo $house['status'] === 'active' ? 'Hide' : 'Show'; ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$houses): ?><tr><td colspan="6" class="py-4 text-slate-500">No listings yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php
pageEnd();
