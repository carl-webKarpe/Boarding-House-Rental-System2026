<?php
require_once __DIR__ . '/security/security_headers.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/sanitize.php';
require_once __DIR__ . '/room-data.php';

applySecurityHeaders();
startSecureSession();

$roomId = isset($_GET['room']) ? (int) $_GET['room'] : 0;
$room = $roomId ? findRoomById($roomId) : null;
$userName = sanitizeForOutput($_SESSION['username'] ?? 'Student');
$role = sanitizeForOutput($_SESSION['role'] ?? 'tenant');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Boarding House Rental System</title>
  <meta name="description" content="Modern boarding house discovery for students and tenants with verified listings, smart search, and secure booking tools." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#22C55E',
            accent: '#16A34A',
            ink: '#0F172A',
            muted: '#64748B'
          },
          fontFamily: {
            display: ['Poppins', 'sans-serif'],
            body: ['Inter', 'sans-serif']
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="style.css" />
</head>
<body class="min-h-screen bg-slate-50 font-body text-slate-800 transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100">
  <button id="darkModeToggle" type="button" class="fixed right-4 top-4 z-50 grid h-11 w-11 place-items-center rounded-full border border-slate-200 bg-white/90 text-slate-700 shadow-soft backdrop-blur transition hover:scale-105 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-100" aria-label="Toggle dark mode">
    <svg id="iconSun" class="hidden h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.73 0-1.42 1.42M7.05 16.95l-1.42 1.42M12 8a4 4 0 100 8 4 4 0 000-8z" /></svg>
    <svg id="iconMoon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 118.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
  </button>

  <div id="toastContainer" class="fixed left-1/2 top-4 z-[60] flex w-[90%] max-w-sm -translate-x-1/2 flex-col gap-3 sm:left-auto sm:right-6 sm:top-6 sm:translate-x-0" aria-live="polite"></div>

  <nav class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/85">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
      <a href="browse-rooms.php" class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600 text-lg font-semibold text-white shadow-lg shadow-emerald-600/20">BH</div>
        <div>
          <div class="font-display text-sm font-semibold text-slate-900 dark:text-white">Boarding House</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">Rental System</div>
        </div>
      </a>

      <label class="ml-auto hidden flex-1 items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500 shadow-sm md:flex dark:border-slate-700 dark:bg-slate-800/80">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" /></svg>
        <input id="globalSearch" type="search" placeholder="Search by location, school, or room" class="w-full bg-transparent outline-none placeholder:text-slate-400" />
      </label>

      <div class="hidden items-center gap-2 lg:flex">
        <a href="browse-rooms.php" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Home</a>
        <a href="#listings" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Boarding Houses</a>
        <a href="#favorites" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Favorites</a>
        <a href="#messages" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Messages</a>
        <a href="notifications.php" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Notifications</a>
        <a href="dashboard.php" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Bookings</a>
      </div>

      <div class="relative ml-2 flex items-center gap-2">
        <button id="profileMenuButton" type="button" class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-100">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700"><?php echo strtoupper(substr($userName, 0, 1)); ?></span>
          <span class="hidden sm:inline"><?php echo $userName; ?></span>
        </button>
        <div id="profileDropdown" class="absolute right-0 top-full mt-2 hidden w-44 rounded-2xl border border-slate-200 bg-white p-2 shadow-soft dark:border-slate-700 dark:bg-slate-900">
          <a href="dashboard.php" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Profile</a>
          <a href="dashboard.php" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Settings</a>
          <a href="logout.php" class="block rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="mx-auto max-w-7xl px-4 pb-24 pt-4 sm:px-6 sm:pt-6 lg:px-8 lg:pb-8">
    <div class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)_300px]">
      <aside class="space-y-5">
        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <div class="flex items-center gap-3 mb-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-lg font-semibold text-emerald-700"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            <div>
              <h2 class="font-display text-sm font-semibold text-slate-900 dark:text-white">Hello, <?php echo $userName; ?></h2>
              <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo ucfirst($role); ?> • Verified</p>
            </div>
          </div>
          <div class="rounded-2xl bg-emerald-50 p-3 text-sm dark:bg-emerald-950/30">
            <div class="flex items-center justify-between mb-2">
              <span class="text-slate-700 dark:text-slate-300">Booking health</span>
              <span class="font-semibold text-emerald-600">92%</span>
            </div>
            <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700">
              <div class="h-1.5 w-[92%] rounded-full bg-gradient-to-r from-emerald-500 to-green-400"></div>
            </div>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 mb-4">Quick links</h3>
          <div class="space-y-2 text-sm">
            <a href="#listings" class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 hover:bg-emerald-50 dark:bg-slate-800/70 dark:hover:bg-slate-800">
              <span>Explore listings</span>
              <span class="text-emerald-600">→</span>
            </a>
            <a href="#favorites" class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 hover:bg-emerald-50 dark:bg-slate-800/70 dark:hover:bg-slate-800">
              <span>Saved properties</span>
              <span class="text-emerald-600">→</span>
            </a>
            <a href="#messages" class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 hover:bg-emerald-50 dark:bg-slate-800/70 dark:hover:bg-slate-800">
              <span>Messages</span>
              <span class="text-emerald-600">→</span>
            </a>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 mb-3">Recent booking</h3>
          <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
            <p class="font-semibold text-sm text-slate-900 dark:text-white">Greenview Hostel</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Move-in Aug 15</p>
            <span class="inline-block mt-2 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 shadow-sm dark:bg-slate-900">Confirmed</span>
          </div>
        </section>
      </aside>

      <section class="space-y-6">
        <section class="overflow-hidden rounded-[32px] border border-slate-200 bg-gradient-to-br from-emerald-600 via-emerald-500 to-green-400 p-6 text-white shadow-soft sm:p-8">
          <div class="grid gap-6 xl:grid-cols-[1fr_auto] xl:items-end">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-100 mb-2">Verified stays for students</p>
              <h1 class="font-display text-3xl font-bold leading-tight sm:text-4xl">Find a safe, stylish boarding house near your school.</h1>
              <p class="mt-4 max-w-2xl text-sm text-emerald-50/90 sm:text-base">Search verified properties with Wi-Fi, parking, laundry, and student-friendly rules in one smooth experience.</p>
              <div class="mt-6 flex flex-wrap gap-2">
                <span class="rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs sm:text-sm backdrop-blur-sm">✓ Verified landlords</span>
                <span class="rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs sm:text-sm backdrop-blur-sm">✓ Safe neighborhoods</span>
                <span class="rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs sm:text-sm backdrop-blur-sm">✓ Live availability</span>
              </div>
            </div>
            <div class="rounded-[24px] border border-white/20 bg-white/10 p-4 backdrop-blur-lg hidden lg:block min-w-[280px]">
              <div class="grid gap-2 sm:grid-cols-2 text-center text-sm">
                <div class="rounded-xl bg-white/15 p-2">
                  <div class="text-2xl font-bold">24</div>
                  <div class="text-xs text-emerald-50/80">Open spots</div>
                </div>
                <div class="rounded-xl bg-white/15 p-2">
                  <div class="text-2xl font-bold">4.8★</div>
                  <div class="text-xs text-emerald-50/80">Avg rating</div>
                </div>
              </div>
              <div class="mt-3 text-xs">Popular now</div>
              <div id="heroSlider" class="mt-2 space-y-1">
                <div data-hero-slide class="rounded-lg bg-white/10 p-2 text-xs">Student-friendly studio • ₱4,800/mo</div>
                <div data-hero-slide class="hidden rounded-lg bg-white/10 p-2 text-xs">Shared room + Wi-Fi • ₱3,500/mo</div>
                <div data-hero-slide class="hidden rounded-lg bg-white/10 p-2 text-xs">Quiet dorm + parking • ₱2,800/mo</div>
              </div>
            </div>
          </div>

          <form id="searchForm" class="mt-6 grid gap-2 rounded-[24px] border border-white/20 bg-white/10 p-3 backdrop-blur-lg sm:grid-cols-2 lg:grid-cols-5">
            <label class="rounded-xl bg-white px-3 py-2 text-xs shadow-sm">
              <span class="block text-xs font-semibold uppercase text-slate-500 mb-1">Location</span>
              <input name="location" type="text" placeholder="City" class="w-full bg-transparent text-slate-700 outline-none text-sm" />
            </label>
            <label class="rounded-xl bg-white px-3 py-2 text-xs shadow-sm">
              <span class="block text-xs font-semibold uppercase text-slate-500 mb-1">School</span>
              <input name="school" type="text" placeholder="University" class="w-full bg-transparent text-slate-700 outline-none text-sm" />
            </label>
            <label class="rounded-xl bg-white px-3 py-2 text-xs shadow-sm">
              <span class="block text-xs font-semibold uppercase text-slate-500 mb-1">Price</span>
              <select name="price" class="w-full bg-transparent text-slate-700 outline-none text-sm">
                <option value="">Any budget</option>
                <option value="budget">Below ₱3,500</option>
                <option value="mid">₱3,500 - ₱4,500</option>
                <option value="premium">Above ₱4,500</option>
              </select>
            </label>
            <label class="rounded-xl bg-white px-3 py-2 text-xs shadow-sm">
              <span class="block text-xs font-semibold uppercase text-slate-500 mb-1">Room type</span>
              <select name="type" class="w-full bg-transparent text-slate-700 outline-none text-sm">
                <option value="">Any type</option>
                <option value="shared">Shared</option>
                <option value="solo">Solo</option>
                <option value="dormitory">Dorm</option>
              </select>
            </label>
            <button type="submit" class="rounded-xl bg-slate-950 px-3 py-2 font-semibold text-white transition hover:bg-slate-900 text-sm">Search</button>
          </form>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-[24px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-xs font-semibold text-emerald-600 uppercase">Recommended</p>
            <h3 class="mt-2 font-display text-sm font-semibold text-slate-900 dark:text-white">Top picks</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Bright rooms, verified landlords.</p>
          </div>
          <div class="rounded-[24px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-xs font-semibold text-emerald-600 uppercase">Newly listed</p>
            <h3 class="mt-2 font-display text-sm font-semibold text-slate-900 dark:text-white">Fresh this week</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Balcony, laundry, fast responses.</p>
          </div>
          <div class="rounded-[24px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-xs font-semibold text-emerald-600 uppercase">Popular</p>
            <h3 class="mt-2 font-display text-sm font-semibold text-slate-900 dark:text-white">Most booked</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Excellent reviews & location.</p>
          </div>
          <div class="rounded-[24px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-xs font-semibold text-emerald-600 uppercase">Special</p>
            <h3 class="mt-2 font-display text-sm font-semibold text-slate-900 dark:text-white">Zero deposit</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Limited offer, book now.</p>
          </div>
        </section>

        <section id="listings" class="rounded-[32px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80 sm:p-6">
          <div class="flex flex-col gap-4 mb-6 md:flex-row md:items-center md:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase text-emerald-600">Featured homes</p>
              <h2 class="mt-1 font-display text-2xl font-bold text-slate-900 dark:text-white">Browse boarding houses</h2>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="filter-chip active rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700" data-filter-chip="all">All</button>
              <button type="button" class="filter-chip rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" data-filter-chip="shared">Shared</button>
              <button type="button" class="filter-chip rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" data-filter-chip="solo">Solo</button>
              <button type="button" class="filter-chip rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" data-filter-chip="budget">Budget</button>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($rooms as $room): ?>
              <article class="group overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-soft dark:border-slate-800 dark:bg-slate-900/80" data-room-card data-title="<?php echo sanitizeForOutput($room['title']); ?>" data-location="<?php echo sanitizeForOutput($room['barangay'] . ', ' . $room['city']); ?>" data-price="<?php echo (int) $room['rent']; ?>" data-type="<?php echo strtolower(sanitizeForOutput($room['type'])); ?>" data-amenities="<?php echo sanitizeForOutput(implode(' ', $room['tags'])); ?>">
                <div class="relative h-48 overflow-hidden bg-slate-100 dark:bg-slate-800">
                  <img src="<?php echo sanitizeForOutput($room['image']); ?>" alt="<?php echo sanitizeForOutput($room['title']); ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-110" loading="lazy" />
                  <span class="absolute left-3 top-3 rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white"><?php echo sanitizeForOutput($room['badge']); ?></span>
                  <button type="button" class="save-btn absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow-sm transition hover:scale-110" data-save-room="<?php echo (int) $room['id']; ?>" aria-label="Save property">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v13.5a.75.75 0 0 1-1.23.56L12 15.75l-6.27 4.56a.75.75 0 0 1-1.23-.56V6.75Z" /></svg>
                  </button>
                </div>
                <div class="p-4">
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <h3 class="font-display text-sm font-bold text-slate-900 dark:text-white"><?php echo sanitizeForOutput($room['title']); ?></h3>
                      <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><?php echo sanitizeForOutput($room['barangay']); ?>, <?php echo sanitizeForOutput($room['city']); ?></p>
                    </div>
                    <div class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-bold text-amber-600 dark:bg-amber-950/40">★ <?php echo number_format((float) $room['rating'], 1); ?></div>
                  </div>
                  <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span><?php echo sanitizeForOutput($room['type']); ?></span>
                    <span><?php echo sanitizeForOutput($room['distance']); ?></span>
                  </div>
                  <div class="mt-3 flex flex-wrap gap-1">
                    <?php foreach (array_slice($room['tags'], 0, 2) as $tag): ?>
                      <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo sanitizeForOutput($tag); ?></span>
                    <?php endforeach; ?>
                  </div>
                  <div class="mt-4 flex items-center justify-between">
                    <div>
                      <div class="text-sm font-bold text-slate-900 dark:text-white">₱<?php echo number_format((int) $room['rent']); ?></div>
                      <div class="text-xs text-slate-500 dark:text-slate-400">/ month</div>
                    </div>
                    <button type="button" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700" data-book-room="<?php echo (int) $room['id']; ?>">Book</button>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="mt-6 flex flex-col items-center gap-3">
            <button id="loadMoreBtn" type="button" class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Load more</button>
            <div id="loadMoreStatus" class="text-xs text-slate-500 dark:text-slate-400">Showing featured properties</div>
          </div>
        </section>

        <section class="rounded-[32px] border border-slate-200 bg-white/90 p-6 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase text-emerald-600">Ready to book?</p>
              <h2 class="mt-1 font-display text-xl font-bold text-slate-900 dark:text-white">Find your perfect boarding house today</h2>
            </div>
            <a href="account-type.html" class="inline-flex rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">Create account</a>
          </div>
          <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Save favorites, message landlords, and book instantly.</p>
        </section>
      </section>

      <aside class="space-y-5">
        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-display text-sm font-semibold uppercase text-slate-500 dark:text-slate-400">Notifications</h3>
            <a href="notifications.php" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">View all →</a>
          </div>
          <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
            <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800/70">New room match near your school.</div>
            <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800/70">Your saved property has 25% off.</div>
            <div class="rounded-xl bg-slate-50 p-2 dark:bg-slate-800/70">Landlord replied to your message.</div>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-sm font-semibold uppercase text-slate-500 dark:text-slate-400 mb-4">Trending locations</h3>
          <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70">
              <span class="text-slate-700 dark:text-slate-300">Surigao City</span>
              <span class="font-semibold text-emerald-600">+18%</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70">
              <span class="text-slate-700 dark:text-slate-300">Canlanipa</span>
              <span class="font-semibold text-emerald-600">+12%</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70">
              <span class="text-slate-700 dark:text-slate-300">Washington</span>
              <span class="font-semibold text-emerald-600">+9%</span>
            </div>
          </div>
        </section>

        <section id="messages" class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-sm font-semibold uppercase text-slate-500 dark:text-slate-400 mb-4">Activity</h3>
          <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-800/70">Maria confirmed your booking.</div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-800/70">New message about shared room.</div>
          </div>
        </section>
      </aside>
    </div>
  </main>

      <aside class="space-y-4">
        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-lg font-semibold text-emerald-700"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            <div>
              <h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">Hello, <?php echo $userName; ?></h2>
              <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo ucfirst($role); ?> • Verified profile</p>
            </div>
          </div>
          <div class="mt-4 rounded-2xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800/70 dark:text-slate-300">
            <div class="flex items-center justify-between">
              <span>Booking health</span>
              <span class="font-semibold text-emerald-600">92%</span>
            </div>
            <div class="mt-2 h-2 rounded-full bg-slate-200 dark:bg-slate-700">
              <div class="h-2 w-[92%] rounded-full bg-gradient-to-r from-emerald-500 to-green-400"></div>
            </div>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Quick links</h3>
          <div class="mt-3 space-y-2 text-sm">
            <a href="#listings" class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2.5 hover:bg-emerald-50 dark:bg-slate-800/70 dark:hover:bg-slate-800">Explore listings <span class="text-emerald-600">→</span></a>
            <a href="#favorites" class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2.5 hover:bg-emerald-50 dark:bg-slate-800/70 dark:hover:bg-slate-800">Saved properties <span class="text-emerald-600">→</span></a>
            <a href="#messages" class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2.5 hover:bg-emerald-50 dark:bg-slate-800/70 dark:hover:bg-slate-800">Landlord messages <span class="text-emerald-600">→</span></a>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Recent booking</h3>
          <div class="mt-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-slate-900 dark:text-white">Greenview Hostel</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">Move-in on Aug 15</p>
              </div>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 shadow-sm dark:bg-slate-900">Confirmed</span>
            </div>
          </div>
        </section>
      </aside>

      <section class="space-y-6">
        <section class="overflow-hidden rounded-[32px] border border-slate-200 bg-gradient-to-br from-emerald-600 via-emerald-500 to-green-400 p-5 text-white shadow-soft sm:p-7">
          <div class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr] xl:items-end">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-100">Verified stays for students</p>
              <h1 class="mt-2 font-display text-3xl font-semibold leading-tight sm:text-4xl">Find a safe, stylish boarding house near your school.</h1>
              <p class="mt-3 max-w-2xl text-sm text-emerald-50/90 sm:text-base">Search verified properties with Wi-Fi, parking, laundry, and student-friendly rules in one smooth experience.</p>
              <div class="mt-5 flex flex-wrap gap-2">
                <span class="rounded-full border border-white/30 bg-white/10 px-3 py-1.5 text-sm">Verified landlords</span>
                <span class="rounded-full border border-white/30 bg-white/10 px-3 py-1.5 text-sm">Safe neighborhoods</span>
                <span class="rounded-full border border-white/30 bg-white/10 px-3 py-1.5 text-sm">Live availability</span>
              </div>
            </div>
            <div class="rounded-[24px] border border-white/20 bg-white/12 p-4 backdrop-blur-xl">
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-white/15 p-3">
                  <div class="text-3xl font-semibold">24</div>
                  <div class="text-sm text-emerald-50/90">Open spots today</div>
                </div>
                <div class="rounded-2xl bg-white/15 p-3">
                  <div class="text-3xl font-semibold">4.8</div>
                  <div class="text-sm text-emerald-50/90">Average rating</div>
                </div>
              </div>
              <div class="mt-3 rounded-2xl bg-slate-950/20 p-3">
                <div class="mb-2 flex items-center justify-between text-sm text-emerald-50/90">
                  <span>Popular now</span>
                  <span class="font-semibold">15 minutes ago</span>
                </div>
                <div id="heroSlider" class="space-y-2">
                  <div data-hero-slide class="rounded-xl bg-white/15 p-3">
                    <div class="font-semibold">Student-friendly studio in Surigao City</div>
                    <div class="text-sm text-emerald-50/90">Near the main university • ₱4,800 / month</div>
                  </div>
                  <div data-hero-slide class="hidden rounded-xl bg-white/15 p-3">
                    <div class="font-semibold">Shared room with Wi-Fi and laundry</div>
                    <div class="text-sm text-emerald-50/90">Walkable to school • ₱3,500 / month</div>
                  </div>
                  <div data-hero-slide class="hidden rounded-xl bg-white/15 p-3">
                    <div class="font-semibold">Quiet dorm with parking and security</div>
                    <div class="text-sm text-emerald-50/90">Budget smart and secure • ₱2,800 / month</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <form id="searchForm" class="mt-6 grid gap-3 rounded-[24px] border border-white/20 bg-white/10 p-3 backdrop-blur-lg md:grid-cols-2 xl:grid-cols-5">
            <label class="rounded-2xl bg-white px-3 py-2.5 text-sm shadow-sm">
              <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Location</span>
              <input name="location" type="text" placeholder="Surigao City" class="w-full bg-transparent text-slate-700 outline-none" />
            </label>
            <label class="rounded-2xl bg-white px-3 py-2.5 text-sm shadow-sm">
              <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">School</span>
              <input name="school" type="text" placeholder="University" class="w-full bg-transparent text-slate-700 outline-none" />
            </label>
            <label class="rounded-2xl bg-white px-3 py-2.5 text-sm shadow-sm">
              <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Price</span>
              <select name="price" class="w-full bg-transparent text-slate-700 outline-none">
                <option value="">Any budget</option>
                <option value="budget">Below ₱3,500</option>
                <option value="mid">₱3,500 - ₱4,500</option>
                <option value="premium">Above ₱4,500</option>
              </select>
            </label>
            <label class="rounded-2xl bg-white px-3 py-2.5 text-sm shadow-sm">
              <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Room type</span>
              <select name="type" class="w-full bg-transparent text-slate-700 outline-none">
                <option value="">Any type</option>
                <option value="shared">Shared</option>
                <option value="solo">Solo</option>
                <option value="dormitory">Dormitory</option>
              </select>
            </label>
            <button type="submit" class="rounded-2xl bg-slate-950 px-4 py-2.5 font-semibold text-white transition hover:bg-slate-900">Search homes</button>
          </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-sm font-semibold text-emerald-600">Recommended</p>
            <h3 class="mt-2 font-display text-lg font-semibold text-slate-900 dark:text-white">Top picks for your budget</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Bright rooms with verified landlords and flexible move-in dates.</p>
          </div>
          <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-sm font-semibold text-emerald-600">Newly listed</p>
            <h3 class="mt-2 font-display text-lg font-semibold text-slate-900 dark:text-white">Fresh properties this week</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">New homes with balcony access, laundry, and fast responses from landlords.</p>
          </div>
          <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-sm font-semibold text-emerald-600">Popular</p>
            <h3 class="mt-2 font-display text-lg font-semibold text-slate-900 dark:text-white">Most booked near schools</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">High-demand homes with excellent reviews and convenient commute routes.</p>
          </div>
          <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <p class="text-sm font-semibold text-emerald-600">Special offer</p>
            <h3 class="mt-2 font-display text-lg font-semibold text-slate-900 dark:text-white">Move in with zero deposit</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Limited offer for students who book before the end of the month.</p>
          </div>
        </section>

        <section id="listings" class="rounded-[32px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80 sm:p-6">
          <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-600">Explore homes</p>
              <h2 class="mt-1 font-display text-2xl font-semibold text-slate-900 dark:text-white">Featured boarding houses</h2>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="filter-chip active rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700" data-filter-chip="all">All</button>
              <button type="button" class="filter-chip rounded-full border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" data-filter-chip="shared">Shared</button>
              <button type="button" class="filter-chip rounded-full border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" data-filter-chip="solo">Solo</button>
              <button type="button" class="filter-chip rounded-full border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" data-filter-chip="budget">Budget</button>
              <button type="button" class="filter-chip rounded-full border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" data-filter-chip="verified">Verified</button>
            </div>
          </div>

          <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($rooms as $room): ?>
              <article class="group overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-soft dark:border-slate-800 dark:bg-slate-900/80" data-room-card data-title="<?php echo sanitizeForOutput($room['title']); ?>" data-location="<?php echo sanitizeForOutput($room['barangay'] . ', ' . $room['city']); ?>" data-price="<?php echo (int) $room['rent']; ?>" data-type="<?php echo strtolower(sanitizeForOutput($room['type'])); ?>" data-amenities="<?php echo sanitizeForOutput(implode(' ', $room['tags'])); ?>">
                <div class="relative">
                  <img src="<?php echo sanitizeForOutput($room['image']); ?>" alt="<?php echo sanitizeForOutput($room['title']); ?>" class="h-56 w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" />
                  <span class="absolute left-4 top-4 rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white"><?php echo sanitizeForOutput($room['badge']); ?></span>
                  <button type="button" class="save-btn absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow-sm transition hover:scale-110" data-save-room="<?php echo (int) $room['id']; ?>" aria-label="Save property">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v13.5a.75.75 0 0 1-1.23.56L12 15.75l-6.27 4.56a.75.75 0 0 1-1.23-.56V6.75Z" /></svg>
                  </button>
                </div>
                <div class="p-5">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <h3 class="font-display text-lg font-semibold text-slate-900 dark:text-white"><?php echo sanitizeForOutput($room['title']); ?></h3>
                      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?php echo sanitizeForOutput($room['barangay']); ?>, <?php echo sanitizeForOutput($room['city']); ?></p>
                    </div>
                    <div class="rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-600 dark:bg-amber-950/40">★ <?php echo number_format((float) $room['rating'], 1); ?></div>
                  </div>
                  <div class="mt-4 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
                    <span><?php echo sanitizeForOutput($room['type']); ?></span>
                    <span><?php echo sanitizeForOutput($room['distance']); ?> from school</span>
                  </div>
                  <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach (array_slice($room['tags'], 0, 3) as $tag): ?>
                      <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo sanitizeForOutput($tag); ?></span>
                    <?php endforeach; ?>
                  </div>
                  <div class="mt-5 flex items-center justify-between">
                    <div>
                      <div class="text-lg font-semibold text-slate-900 dark:text-white">₱<?php echo number_format((int) $room['rent']); ?></div>
                      <div class="text-sm text-slate-500 dark:text-slate-400">/ month</div>
                    </div>
                    <div class="flex gap-2">
                      <a href="browse-rooms.php?room=<?php echo (int) $room['id']; ?>" class="rounded-full border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">View</a>
                      <button type="button" class="rounded-full bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700" data-book-room="<?php echo (int) $room['id']; ?>">Book</button>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="mt-6 flex flex-col items-center gap-3">
            <button id="loadMoreBtn" type="button" class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Load more listings</button>
            <div id="loadMoreStatus" class="text-sm text-slate-500 dark:text-slate-400">Showing current featured properties.</div>
          </div>
        </section>

        <section class="rounded-[32px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-600">Need help?</p>
              <h2 class="mt-1 font-display text-xl font-semibold text-slate-900 dark:text-white">Ready to find your next boarding house?</h2>
            </div>
            <a href="account-type.html" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">Create account</a>
          </div>
          <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Sign up to save your favorite listings, receive alerts, and book faster.</p>
        </section>
      </section>

      <aside class="space-y-4">
        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <div class="flex items-center justify-between">
            <h3 class="font-display text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Notifications</h3>
            <a href="notifications.php" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">View all →</a>
          </div>
          <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
            <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/70">A new room match was added near your preferred school.</div>
            <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/70">Your saved property has a 25% discount before Friday.</div>
            <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/70">The landlord replied to your message in under 10 minutes.</div>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Trending locations</h3>
          <div class="mt-3 space-y-2 text-sm">
            <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70"><span>Surigao City</span><span class="font-semibold text-emerald-600">+18%</span></div>
            <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70"><span>Canlanipa</span><span class="font-semibold text-emerald-600">+12%</span></div>
            <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70"><span>Washington</span><span class="font-semibold text-emerald-600">+9%</span></div>
          </div>
        </section>

        <section id="messages" class="rounded-[28px] border border-slate-200 bg-white/90 p-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="font-display text-sm font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Mini activity</h3>
          <div class="mt-3 space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/70">Maria sent a booking confirmation for your saved room.</div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/70">A new message arrived about the shared room near the university.</div>
          </div>
        </section>
      </aside>
    </div>
  </main>

  <div id="bookingModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/70 p-4">
    <div class="w-full max-w-md rounded-[28px] border border-slate-200 bg-white p-6 shadow-soft dark:border-slate-700 dark:bg-slate-900">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-600">Quick booking</p>
          <h3 id="modalTitle" class="mt-1 font-display text-xl font-semibold text-slate-900 dark:text-white">Reserve this property</h3>
        </div>
        <button id="closeModalBtn" type="button" class="rounded-full p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">✕</button>
      </div>
      <div id="modalBody" class="mt-4 text-sm text-slate-600 dark:text-slate-300"></div>
      <div class="mt-5 flex justify-end gap-2">
        <button id="cancelModalBtn" type="button" class="rounded-full border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Cancel</button>
        <button type="button" class="rounded-full bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Confirm request</button>
      </div>
    </div>
  </div>

  <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/90 backdrop-blur lg:hidden dark:border-slate-800 dark:bg-slate-900/90">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-2">
      <a href="browse-rooms.php" class="flex flex-1 flex-col items-center rounded-2xl px-2 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300">🏠 Home</a>
      <a href="#listings" class="flex flex-1 flex-col items-center rounded-2xl px-2 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300">🔎 Search</a>
      <a href="#favorites" class="flex flex-1 flex-col items-center rounded-2xl px-2 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300">💛 Favorites</a>
      <a href="#messages" class="flex flex-1 flex-col items-center rounded-2xl px-2 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300">💬 Messages</a>
      <a href="dashboard.php" class="flex flex-1 flex-col items-center rounded-2xl px-2 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300">👤 Profile</a>
    </div>
  </nav>

  <script src="app.js"></script>
</body>
</html>
