<?php

declare(strict_types=1);

/**
 * Shared page layout (head, navigation, flash messages, footer) for all
 * PHP pages. Every page lives one folder deep (php/, landlord/, admin/),
 * so links use "../" paths.
 */

function navLinksForRole(string $role): array {
    if ($role === 'guest') {
        return [
            'browse' => ['Browse Rooms', '../php/browse-rooms.php'],
        ];
    }
    if (isAdminRole($role)) {
        return [
            'admin' => ['Admin Panel', '../admin/adminpanel.php'],
            'users' => ['Users', '../admin/users.php'],
            'listings' => ['Listings', '../admin/listings.php'],
            'browse' => ['Browse Rooms', '../php/browse-rooms.php'],
        ];
    }
    if ($role === ROLE_LANDLORD) {
        return [
            'landlord' => ['My Listings', '../landlord/dashboard.php'],
            'requests' => ['Booking Requests', '../landlord/bookings.php'],
            'browse' => ['Browse Rooms', '../php/browse-rooms.php'],
        ];
    }
    return [
        'browse' => ['Browse Rooms', '../php/browse-rooms.php'],
        'bookings' => ['My Bookings', '../php/dashboard.php'],
        'favorites' => ['Favorites', '../php/dashboard.php#favorites'],
        'notifications' => ['Notifications', '../php/notifications.php'],
    ];
}

function pageStart(string $title, string $active = ''): void {
    $role = currentRole();
    $links = navLinksForRole($role);
    $userName = (string) ($_SESSION['username'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo e($title); ?> | Boarding House Rental System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: { primary: '#16A34A', accent: '#15803D', ink: '#0F172A', muted: '#64748B' },
          fontFamily: { display: ['Poppins', 'sans-serif'], body: ['Inter', 'sans-serif'] },
          boxShadow: { soft: '0 20px 45px -20px rgba(15, 23, 42, 0.18)' }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="../style.css" />
</head>
<body class="min-h-screen bg-slate-50 font-body text-slate-800 dark:bg-slate-950 dark:text-slate-100">
  <div id="toastContainer" class="fixed left-1/2 top-4 z-[60] flex w-[90%] max-w-sm -translate-x-1/2 flex-col gap-3 sm:left-auto sm:right-6 sm:top-6 sm:translate-x-0" aria-live="polite"></div>

  <nav class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
      <a href="../html/index.html" class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 font-display text-base font-semibold text-white">BH</div>
        <div class="hidden sm:block">
          <div class="font-display text-sm font-semibold text-slate-900 dark:text-white">Boarding House</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">Rental System</div>
        </div>
      </a>

      <div class="ml-4 hidden items-center gap-1 lg:flex">
        <?php foreach ($links as $key => [$label, $href]): ?>
          <a href="<?php echo e($href); ?>" class="rounded-full px-3 py-2 text-sm font-semibold transition <?php echo $key === $active ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'; ?>"><?php echo e($label); ?></a>
        <?php endforeach; ?>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <button id="darkModeToggle" type="button" class="grid h-10 w-10 place-items-center rounded-full border border-slate-200 text-slate-600 transition hover:border-emerald-300 dark:border-slate-700 dark:text-slate-200" aria-label="Toggle dark mode">
          <svg id="iconSun" class="hidden h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.73 0-1.42 1.42M7.05 16.95l-1.42 1.42M12 8a4 4 0 100 8 4 4 0 000-8z" /></svg>
          <svg id="iconMoon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 118.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
        </button>
        <?php if ($role === 'guest'): ?>
          <a href="../html/loginform.html" class="rounded-full px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Login</a>
          <a href="../html/account-type.html" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Sign up</a>
        <?php else: ?>
          <div class="relative">
            <button id="profileMenuButton" type="button" class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
              <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700"><?php echo e(strtoupper(substr($userName, 0, 1))); ?></span>
              <span class="hidden sm:inline"><?php echo e($userName); ?></span>
            </button>
            <div id="profileDropdown" class="absolute right-0 top-full mt-2 hidden w-48 rounded-2xl border border-slate-200 bg-white p-2 shadow-soft dark:border-slate-700 dark:bg-slate-900">
              <p class="px-3 py-1 text-xs text-slate-500"><?php echo e(ucwords(str_replace('_', ' ', $role))); ?></p>
              <a href="../php/profile.php" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">My Profile</a>
              <a href="../php/logout.php" class="block rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">Logout</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="flex gap-1 overflow-x-auto px-4 pb-2 lg:hidden">
      <?php foreach ($links as $key => [$label, $href]): ?>
        <a href="<?php echo e($href); ?>" class="whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-semibold <?php echo $key === $active ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'; ?>"><?php echo e($label); ?></a>
      <?php endforeach; ?>
    </div>
  </nav>

  <main class="mx-auto max-w-7xl px-4 pb-16 pt-6 sm:px-6 lg:px-8">
    <?php foreach (takeFlashes() as $flash): ?>
      <div class="mb-4 rounded-2xl border px-4 py-3 text-sm font-medium <?php echo $flash['type'] === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'; ?>" role="alert">
        <?php echo e($flash['message']); ?>
      </div>
    <?php endforeach; ?>
<?php
}

function pageEnd(): void {
    ?>
  </main>
  <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-500 dark:border-slate-800">
    &copy; <?php echo date('Y'); ?> Boarding House Rental System
  </footer>
  <script src="../registerJS/app.js"></script>
</body>
</html>
<?php
}

/** Card wrapper classes used across pages. */
const CARD = 'rounded-3xl border border-slate-200 bg-white p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900 sm:p-6';
const INPUT = 'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800';
const LABEL = 'block text-sm font-semibold text-slate-700 dark:text-slate-200';
const BTN_PRIMARY = 'inline-flex items-center justify-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50';
const BTN_SECONDARY = 'inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200';
const BTN_DANGER = 'inline-flex items-center justify-center rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10';

/** Room listing card used on Browse and in the tenant's favorites. */
function roomCard(array $room, bool $isFavorite = false): void {
    $canFavorite = isTenantRole(currentRole());
    $returnTo = '../php/' . basename((string) $_SERVER['SCRIPT_NAME']) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    ?>
  <article class="group flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-soft dark:border-slate-800 dark:bg-slate-900">
    <div class="relative h-48 overflow-hidden bg-slate-100 dark:bg-slate-800">
      <a href="../php/room.php?id=<?php echo (int) $room['id']; ?>">
        <img src="<?php echo e(photoUrl($room['cover_photo'] ?? null)); ?>" alt="<?php echo e($room['name']); ?>" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
      </a>
      <span class="absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm"><?php echo e(roomTypeLabel($room['room_type'])); ?></span>
      <?php if ($canFavorite): ?>
        <form method="post" action="../php/favorite.php" class="absolute right-3 top-3">
          <?php echo csrfInput(); ?>
          <input type="hidden" name="room_id" value="<?php echo (int) $room['id']; ?>" />
          <input type="hidden" name="return" value="<?php echo e($returnTo); ?>" />
          <button type="submit" class="grid h-9 w-9 place-items-center rounded-full bg-white/95 shadow-sm transition hover:scale-110 <?php echo $isFavorite ? 'text-rose-500' : 'text-slate-500'; ?>" aria-label="<?php echo $isFavorite ? 'Remove from favorites' : 'Save to favorites'; ?>">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="<?php echo $isFavorite ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
          </button>
        </form>
      <?php endif; ?>
    </div>
    <div class="flex flex-1 flex-col p-4">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <h3 class="truncate font-display text-base font-semibold text-slate-900 dark:text-white">
            <a href="../php/room.php?id=<?php echo (int) $room['id']; ?>" class="hover:text-emerald-600"><?php echo e($room['name']); ?></a>
          </h3>
          <p class="truncate text-xs text-slate-500 dark:text-slate-400"><?php echo e($room['house_name']); ?> · <?php echo e($room['barangay']); ?>, <?php echo e($room['city']); ?></p>
        </div>
        <?php if ($room['avg_rating'] !== null): ?>
          <span class="shrink-0 rounded-lg bg-amber-50 px-2 py-1 text-xs font-bold text-amber-600 dark:bg-amber-500/10">★ <?php echo e(number_format((float) $room['avg_rating'], 1)); ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($room['nearby_school'])): ?>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">🎓 <?php echo e($room['distance_to_school'] ? $room['distance_to_school'] . ' from ' : 'Near '); ?><?php echo e($room['nearby_school']); ?></p>
      <?php endif; ?>
      <div class="mt-3 flex flex-wrap gap-1">
        <?php foreach (array_slice(amenityList($room['amenities']), 0, 3) as $amenity): ?>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo e($amenity); ?></span>
        <?php endforeach; ?>
      </div>
      <div class="mt-auto flex items-end justify-between pt-4">
        <div>
          <span class="font-display text-lg font-bold text-slate-900 dark:text-white"><?php echo e(money($room['monthly_rent'])); ?></span>
          <span class="text-xs text-slate-500">/ month</span>
        </div>
        <?php if ($room['status'] === 'available' && (int) $room['available_slots'] > 0): ?>
          <span class="text-xs font-semibold text-emerald-600"><?php echo (int) $room['available_slots']; ?> slot<?php echo (int) $room['available_slots'] === 1 ? '' : 's'; ?> left</span>
        <?php else: ?>
          <span class="text-xs font-semibold text-slate-400">Full</span>
        <?php endif; ?>
      </div>
    </div>
  </article>
<?php
}
