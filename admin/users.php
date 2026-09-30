<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

requireRole(ADMIN_ROLES);
$pdo = getDb();

$roleFilter = in_array($_GET['role'] ?? '', ['tenant', 'landlord', 'admin'], true) ? $_GET['role'] : '';
$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'inactive'], true) ? $_GET['status'] : '';
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

$where = ['1 = 1'];
$params = [];
if ($roleFilter === 'tenant') {
    $where[] = "role IN ('tenant', 'user')";
} elseif ($roleFilter === 'admin') {
    $where[] = "role IN ('admin', 'super_admin', 'staff')";
} elseif ($roleFilter === 'landlord') {
    $where[] = "role = 'landlord'";
}
if ($statusFilter === 'inactive') {
    $where[] = 'is_active = 0';
} elseif ($statusFilter !== '') {
    $where[] = 'approval_status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(username LIKE :q1 OR email LIKE :q2 OR first_name LIKE :q3 OR last_name LIKE :q4)';
    foreach ([':q1', ':q2', ':q3', ':q4'] as $key) {
        $params[$key] = '%' . $search . '%';
    }
}

$stmt = $pdo->prepare('SELECT id, username, email, first_name, last_name, role, approval_status, is_active, created_at FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT 200');
$stmt->execute($params);
$users = $stmt->fetchAll();

pageStart('Users', 'users');
?>
<h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">Users</h1>

<form method="get" class="<?php echo CARD; ?> mt-4 grid gap-3 sm:grid-cols-4">
  <input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Name, username, or email" class="<?php echo INPUT; ?> !mt-0 sm:col-span-2" />
  <select name="role" class="<?php echo INPUT; ?> !mt-0">
    <option value="">All roles</option>
    <?php foreach (['tenant' => 'Tenants', 'landlord' => 'Landlords', 'admin' => 'Admins'] as $value => $label): ?>
      <option value="<?php echo $value; ?>" <?php echo $roleFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
    <?php endforeach; ?>
  </select>
  <div class="flex gap-2">
    <select name="status" class="<?php echo INPUT; ?> !mt-0">
      <option value="">Any status</option>
      <?php foreach (['pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'inactive' => 'Deactivated'] as $value => $label): ?>
        <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
      <?php endforeach; ?>
    </select>
    <button class="<?php echo BTN_PRIMARY; ?>">Filter</button>
  </div>
</form>

<section class="<?php echo CARD; ?> mt-6 overflow-x-auto">
  <table class="w-full min-w-[720px] text-left text-sm">
    <thead class="text-xs uppercase text-slate-500">
      <tr><th class="py-2">Name</th><th>Email</th><th>Role</th><th>Approval</th><th>Account</th><th>Joined</th><th></th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
      <?php foreach ($users as $user): ?>
        <tr>
          <td class="py-3"><p class="font-semibold"><?php echo e(fullName($user)); ?></p><p class="text-xs text-slate-500">@<?php echo e($user['username']); ?></p></td>
          <td><?php echo e($user['email']); ?></td>
          <td><?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?></td>
          <td><?php echo statusBadge($user['approval_status']); ?></td>
          <td><?php echo statusBadge((int) $user['is_active'] === 1 ? 'active' : 'inactive'); ?></td>
          <td class="text-slate-500"><?php echo e(date('M j, Y', strtotime((string) $user['created_at']))); ?></td>
          <td class="text-right"><a href="user.php?id=<?php echo (int) $user['id']; ?>" class="font-semibold text-emerald-600 hover:underline">View</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="7" class="py-4 text-slate-500">No users found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php
pageEnd();
