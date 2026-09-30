<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$filters = [
    'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
    'type' => (string) ($_GET['type'] ?? ''),
    'min_price' => (string) ($_GET['min_price'] ?? ''),
    'max_price' => (string) ($_GET['max_price'] ?? ''),
    'amenity' => (string) ($_GET['amenity'] ?? ''),
    'gender' => (string) ($_GET['gender'] ?? ''),
    'available' => !empty($_GET['available']),
    'sort' => (string) ($_GET['sort'] ?? ''),
];

try {
    $rooms = searchRooms($filters, $perPage + 1, ($page - 1) * $perPage);
    $favoriteIds = favoriteRoomIds(currentUserId());
} catch (Throwable $e) {
    writeLog('Browse rooms failed: ' . $e->getMessage(), 'ERROR');
    $rooms = [];
    $favoriteIds = [];
    flash('error', 'Rooms could not be loaded right now.');
}
$hasMore = count($rooms) > $perPage;
$rooms = array_slice($rooms, 0, $perPage);

function pageLink(int $page): string {
    $query = $_GET;
    $query['page'] = $page;
    return 'browse-rooms.php?' . http_build_query($query);
}

pageStart('Browse Rooms', 'browse');
?>
<section class="overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-emerald-500 to-green-400 p-6 text-white shadow-soft sm:p-8">
  <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-100">Student housing</p>
  <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">Find a safe, affordable boarding house near your school</h1>
  <form method="get" class="mt-5 flex flex-col gap-2 sm:flex-row">
    <input type="search" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Search by school, barangay, city, or boarding house" class="w-full rounded-full px-5 py-3 text-sm text-slate-800 outline-none" />
    <button type="submit" class="rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
  </form>
</section>

<div class="mt-6 grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
  <aside>
    <form method="get" class="<?php echo CARD; ?> space-y-4 lg:sticky lg:top-24">
      <input type="hidden" name="q" value="<?php echo e($filters['q']); ?>" />
      <h2 class="font-display text-sm font-semibold uppercase tracking-wider text-slate-500">Filters</h2>
      <label class="<?php echo LABEL; ?>">Room type
        <select name="type" class="<?php echo INPUT; ?>">
          <option value="">Any type</option>
          <?php foreach (ROOM_TYPES as $value => $label): ?>
            <option value="<?php echo e($value); ?>" <?php echo $filters['type'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="grid grid-cols-2 gap-2">
        <label class="<?php echo LABEL; ?>">Min ₱
          <input type="number" name="min_price" min="0" step="1" value="<?php echo e($filters['min_price']); ?>" class="<?php echo INPUT; ?>" />
        </label>
        <label class="<?php echo LABEL; ?>">Max ₱
          <input type="number" name="max_price" min="0" step="1" value="<?php echo e($filters['max_price']); ?>" class="<?php echo INPUT; ?>" />
        </label>
      </div>
      <label class="<?php echo LABEL; ?>">Must have
        <select name="amenity" class="<?php echo INPUT; ?>">
          <option value="">Any amenity</option>
          <?php foreach (COMMON_AMENITIES as $amenity): ?>
            <option value="<?php echo e($amenity); ?>" <?php echo $filters['amenity'] === $amenity ? 'selected' : ''; ?>><?php echo e($amenity); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="<?php echo LABEL; ?>">For
        <select name="gender" class="<?php echo INPUT; ?>">
          <option value="">Anyone</option>
          <option value="male" <?php echo $filters['gender'] === 'male' ? 'selected' : ''; ?>>Male tenants</option>
          <option value="female" <?php echo $filters['gender'] === 'female' ? 'selected' : ''; ?>>Female tenants</option>
        </select>
      </label>
      <label class="<?php echo LABEL; ?>">Sort by
        <select name="sort" class="<?php echo INPUT; ?>">
          <option value="">Newest</option>
          <option value="price_asc" <?php echo $filters['sort'] === 'price_asc' ? 'selected' : ''; ?>>Lowest price</option>
          <option value="price_desc" <?php echo $filters['sort'] === 'price_desc' ? 'selected' : ''; ?>>Highest price</option>
          <option value="rating" <?php echo $filters['sort'] === 'rating' ? 'selected' : ''; ?>>Top rated</option>
        </select>
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="available" value="1" <?php echo $filters['available'] ? 'checked' : ''; ?> class="h-4 w-4 rounded text-emerald-600" />
        Only rooms with open slots
      </label>
      <div class="flex gap-2">
        <button type="submit" class="<?php echo BTN_PRIMARY; ?> flex-1">Apply</button>
        <a href="browse-rooms.php" class="<?php echo BTN_SECONDARY; ?>">Reset</a>
      </div>
    </form>
  </aside>

  <section>
    <div class="mb-4 flex items-center justify-between">
      <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white">
        <?php echo $filters['q'] !== '' ? 'Results for “' . e($filters['q']) . '”' : 'Available rooms'; ?>
      </h2>
      <?php if (!isLoggedIn()): ?>
        <a href="../html/loginform.html" class="text-sm font-semibold text-emerald-600 hover:underline">Log in to book or save rooms</a>
      <?php endif; ?>
    </div>

    <?php if (!$rooms): ?>
      <div class="<?php echo CARD; ?> text-center">
        <p class="font-semibold">No rooms match your search.</p>
        <p class="mt-1 text-sm text-slate-500">Try removing some filters or searching a nearby barangay.</p>
      </div>
    <?php else: ?>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($rooms as $room): ?>
          <?php roomCard($room, in_array((int) $room['id'], $favoriteIds, true)); ?>
        <?php endforeach; ?>
      </div>
      <div class="mt-6 flex justify-center gap-2">
        <?php if ($page > 1): ?><a href="<?php echo e(pageLink($page - 1)); ?>" class="<?php echo BTN_SECONDARY; ?>">← Previous</a><?php endif; ?>
        <?php if ($hasMore): ?><a href="<?php echo e(pageLink($page + 1)); ?>" class="<?php echo BTN_SECONDARY; ?>">Next →</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php
pageEnd();
