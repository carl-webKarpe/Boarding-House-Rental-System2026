/* ==========================================================================
   Boarding House Rental System — home.js
   Vanilla JS only. No framework, no backend calls — this file drives the
   front-end prototype behavior for the public Home Page.
   ========================================================================== */

"use strict";

/* ---------------------------------------------------------------------- *
 * Sample listings data — replace with real data from the database later.
 * ---------------------------------------------------------------------- */
const LISTINGS = [
  {
    id: "greenview",
    name: "Green View Boarding House",
    location: "DAPA, Siargao",
    price: 1500,
    roomType: "Single Room",
    availability: "Available",
    rooms: 6,
    amenities: ["Wi-Fi", "Electricity", "Water", "Study Area"],
    img: "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQP2gJlXIUqunx_oRPYPnk3T67yT5JCJlJDHbZc9K22Jg&s=10",
  },
  {
    id: "islandhome",
    name: "Island Home Boarding House",
    location: "Dapa, Siargao",
    price: 2500,
    roomType: "Shared Room",
    availability: "Available",
    rooms: 10,
    amenities: ["Wi-Fi", "Kitchen", "Water", "Laundry"],
    img: "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRwJuvdYKswbB60ZWFzolyPkYWrTbm6q4qshU3lAMDJ1g&s",
  },
  {
    id: "studenthaven",
    name: "Student Haven",
    location: "General Luna, Siargao",
    price: 5000,
    roomType: "Single Room",
    availability: "Available",
    rooms: 4,
    amenities: ["Wi-Fi", "Private Bathroom", "Study Area"],
    img: "https://picsum.photos/seed/studenthaven/480/360",
  },
  {
    id: "sunrise",
    name: "Sunrise Bedspace Inn",
    location: "Del Carmen, Siargao",
    price: 2000,
    roomType: "Bedspace",
    availability: "Coming Soon",
    rooms: 12,
    amenities: ["Wi-Fi", "Shared Kitchen", "Electric Fan"],
    img: "https://picsum.photos/seed/sunrise/480/360",
  },
];

/* ---------------------------------------------------------------------- *
 * Listings rendering + filtering
 * ---------------------------------------------------------------------- */
function currency(amount) {
  return "\u20B1" + amount.toLocaleString("en-PH");
}

function listingCardHTML(item) {
  const badgeClass = item.availability === "Available" ? "" : " listing-card__badge--soon";
  return `
    <article class="listing-card reveal is-visible" data-id="${item.id}">
      <div class="listing-card__img-wrap">
        <img class="listing-card__img" src="${item.img}" alt="${item.name}" loading="lazy" />
        <span class="listing-card__badge${badgeClass}">${item.availability}</span>
      </div>
      <div class="listing-card__body">
        <h3>${item.name}</h3>
        <span class="listing-card__loc">${item.location}</span>
        <span class="listing-card__price">${currency(item.price)} <span>/ month</span></span>
        <span class="listing-card__meta">${item.roomType} &middot; ${item.rooms} rooms available</span>
        <span class="listing-card__amenities">${item.amenities.join(" \u2022 ")}</span>
        <button type="button" class="listing-card__cta" data-view="${item.id}">View Details</button>
      </div>
    </article>
  `;
}

function renderListings(items) {
  const grid = document.getElementById("listingsGrid");
  const empty = document.getElementById("listingsEmpty");
  if (!grid) return;

  grid.innerHTML = items.map(listingCardHTML).join("");
  empty.classList.toggle("hidden", items.length > 0);

  // Wire up "View Details" buttons for the cards just rendered.
  grid.querySelectorAll("[data-view]").forEach((btn) => {
    btn.addEventListener("click", () => openListingModal(btn.getAttribute("data-view")));
  });
}

function applyFilters() {
  const location = document.getElementById("fLocation").value.trim().toLowerCase();
  const maxPrice = parseFloat(document.getElementById("fPrice").value);
  const roomType = document.getElementById("fType").value;
  const availability = document.getElementById("fAvailability").value;

  const filtered = LISTINGS.filter((item) => {
    if (location && !item.location.toLowerCase().includes(location)) return false;
    if (!isNaN(maxPrice) && maxPrice > 0 && item.price > maxPrice) return false;
    if (roomType && item.roomType !== roomType) return false;
    if (availability && item.availability !== availability) return false;
    return true;
  });

  renderListings(filtered);

  const hint = document.getElementById("searchHint");
  hint.textContent = filtered.length
    ? `Showing ${filtered.length} boarding house${filtered.length === 1 ? "" : "s"} that match your search.`
    : "";

  document.getElementById("listings").scrollIntoView({ behavior: "smooth", block: "start" });
}

/* ---------------------------------------------------------------------- *
 * "View Details" modal
 * ---------------------------------------------------------------------- */
function openListingModal(id) {
  const item = LISTINGS.find((l) => l.id === id);
  if (!item) return;

  const overlay = document.getElementById("modalOverlay");
  const body = document.getElementById("modalBody");

  body.innerHTML = `
    <img class="listing-card__img" style="border-radius:16px;margin-bottom:16px;" src="${item.img}" alt="${item.name}" />
    <h3 id="modalTitle">${item.name}</h3>
    <span class="listing-card__loc">${item.location}</span>
    <span class="listing-card__price">${currency(item.price)} <span>/ month</span></span>
    <dl>
      <dt>Room Type</dt><dd>${item.roomType}</dd>
      <dt>Availability</dt><dd>${item.availability}</dd>
      <dt>Rooms Open</dt><dd>${item.rooms}</dd>
      <dt>Amenities</dt><dd>${item.amenities.join(", ")}</dd>
    </dl>
    <a href="account-type.html" class="btn btn--dark" style="margin-top:22px;width:100%;">Reserve This Room</a>
  `;

  overlay.classList.add("is-open");
  document.body.style.overflow = "hidden";
}

function closeListingModal() {
  document.getElementById("modalOverlay").classList.remove("is-open");
  document.body.style.overflow = "";
}

/* ---------------------------------------------------------------------- *
 * Navbar: sticky shrink-on-scroll + mobile hamburger menu
 * ---------------------------------------------------------------------- */
function initNavbar() {
  const navbar = document.getElementById("navbar");
  const burger = document.getElementById("burgerBtn");
  const links = document.getElementById("navLinks");

  function onScroll() {
    navbar.classList.toggle("is-scrolled", window.scrollY > 30);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  burger.addEventListener("click", () => {
    const isOpen = links.classList.toggle("is-open");
    burger.classList.toggle("is-open", isOpen);
    burger.setAttribute("aria-expanded", String(isOpen));
  });

  // Close the mobile menu whenever a nav link is tapped.
  links.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      links.classList.remove("is-open");
      burger.classList.remove("is-open");
      burger.setAttribute("aria-expanded", "false");
    });
  });

  // Highlight the current section's nav link on scroll.
  const sections = ["top", "listings", "how-it-works", "about", "footer"]
    .map((id) => document.getElementById(id))
    .filter(Boolean);

  const navlinkFor = (id) => document.querySelector(`.navlink[href="#${id}"]`);

  const sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          document.querySelectorAll(".navlink").forEach((a) => a.classList.remove("is-active"));
          const active = navlinkFor(entry.target.id);
          if (active) active.classList.add("is-active");
        }
      });
    },
    { rootMargin: "-45% 0px -50% 0px" }
  );
  sections.forEach((section) => sectionObserver.observe(section));
}

/* ---------------------------------------------------------------------- *
 * Scroll-reveal animation for elements marked .reveal
 * ---------------------------------------------------------------------- */
function initScrollReveal() {
  const targets = document.querySelectorAll(".reveal");
  const revealObserver = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );
  targets.forEach((el) => revealObserver.observe(el));
}

/* ---------------------------------------------------------------------- *
 * Animated stat counters (10+, 50+, 100+)
 * ---------------------------------------------------------------------- */
function initStatCounters() {
  const stats = document.querySelectorAll(".stat__num");
  if (!stats.length) return;

  function animateCount(el) {
    const target = parseInt(el.getAttribute("data-count"), 10) || 0;
    const duration = 1200;
    const start = performance.now();

    function tick(now) {
      const progress = Math.min((now - start) / duration, 1);
      el.textContent = Math.round(progress * target);
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  const statsObserver = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );
  stats.forEach((el) => statsObserver.observe(el));
}

/* ---------------------------------------------------------------------- *
 * INIT
 * ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", () => {
  initNavbar();
  initScrollReveal();
  initStatCounters();

  renderListings(LISTINGS);

  document.getElementById("searchForm").addEventListener("submit", (e) => {
    e.preventDefault();
    applyFilters();
  });

  document.getElementById("modalClose").addEventListener("click", closeListingModal);
  document.getElementById("modalOverlay").addEventListener("click", (e) => {
    if (e.target.id === "modalOverlay") closeListingModal();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeListingModal();
  });

  document.getElementById("year").textContent = new Date().getFullYear();
});
