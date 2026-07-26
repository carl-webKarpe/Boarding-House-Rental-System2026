/* ==========================================================================
   Boarding House Rental System — app.js
   Shared, reusable utilities used by BOTH index.html (login) and
   signup.html (registration). Page-specific form logic lives in its own
   file: the login form wiring below, and js/signup.js for registration.
   No external dependencies.
   ========================================================================== */

"use strict";

/* ---------------------------------------------------------------------- *
 * 1. DARK MODE  (shared)
 * ---------------------------------------------------------------------- */
function initDarkMode() {
  const root = document.documentElement;
  const toggleBtn = document.getElementById("darkModeToggle");
  const sunIcon = document.getElementById("iconSun");
  const moonIcon = document.getElementById("iconMoon");
  if (!toggleBtn || !sunIcon || !moonIcon) return;

  const applyMode = (isDark) => {
    root.classList.toggle("dark", isDark);
    root.setAttribute("data-theme", isDark ? "dark" : "light");
    document.body.classList.toggle("dark", isDark);
    document.body.classList.toggle("bg-slate-900", isDark);
    document.body.classList.toggle("text-slate-100", isDark);
    document.body.classList.toggle("bg-bgapp", !isDark);
    document.body.classList.toggle("text-textmain", !isDark);
    document.documentElement.style.colorScheme = isDark ? "dark" : "light";
    sunIcon.classList.toggle("hidden", !isDark);
    moonIcon.classList.toggle("hidden", isDark);
    toggleBtn.setAttribute("aria-pressed", String(isDark));
  };

  const saved = localStorage.getItem("bhrs-theme");
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  applyMode(saved ? saved === "dark" : prefersDark);

  toggleBtn.addEventListener("click", () => {
    const isDark = !root.classList.contains("dark");
    applyMode(isDark);
    localStorage.setItem("bhrs-theme", isDark ? "dark" : "light");
  });
}

/* ---------------------------------------------------------------------- *
 * 2. TOAST NOTIFICATIONS  (shared)
 * ---------------------------------------------------------------------- */
function showToast(message, type = "success") {
  const container = document.getElementById("toastContainer");
  if (!container) return;

  const palette = {
    success: { bg: "bg-emerald-600", icon: "M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" },
    error: { bg: "bg-red-500", icon: "M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" },
    info: { bg: "bg-slate-700", icon: "M11.25 11.25h.008v.008h-.008v-.008ZM12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Z" },
  };
  const style = palette[type] || palette.info;

  const toast = document.createElement("div");
  toast.className = `toast-enter ${style.bg} text-white text-sm font-medium rounded-2xl shadow-soft px-4 py-3 flex items-start gap-2`;
  toast.setAttribute("role", "status");
  toast.innerHTML = `
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="${style.icon}" />
    </svg>
    <span>${message}</span>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.remove("toast-enter");
    toast.classList.add("toast-exit");
    toast.addEventListener("animationend", () => toast.remove(), { once: true });
  }, 3500);
}

/* ---------------------------------------------------------------------- *
 * 3. PASSWORD VISIBILITY TOGGLE  (shared, generalized for multiple fields)
 * ---------------------------------------------------------------------- */
function initPasswordToggle(toggleId, inputId, eyeOpenId, eyeClosedId) {
  const toggleBtn = document.getElementById(toggleId);
  const input = document.getElementById(inputId);
  const eyeOpen = document.getElementById(eyeOpenId);
  const eyeClosed = document.getElementById(eyeClosedId);
  if (!toggleBtn || !input || !eyeOpen || !eyeClosed) return;

  toggleBtn.addEventListener("click", () => {
    const isVisible = input.type === "text";
    input.type = isVisible ? "password" : "text";
    eyeOpen.classList.toggle("hidden", !isVisible);
    eyeClosed.classList.toggle("hidden", isVisible);
    toggleBtn.setAttribute("aria-label", isVisible ? "Show password" : "Hide password");
    toggleBtn.setAttribute("aria-pressed", String(!isVisible));
  });
}

/* ---------------------------------------------------------------------- *
 * 4. VALIDATION HELPERS  (shared)
 * ---------------------------------------------------------------------- */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Toggle an error message under a field, and matching red/normal input styles.
 * Pass an empty string / falsy message to clear the error.
 */
function setFieldError(inputEl, errorEl, message) {
  if (!inputEl || !errorEl) return;

  if (message) {
    inputEl.classList.add("border-red-400", "focus:ring-red-300", "focus:border-red-400");
    inputEl.classList.remove("border-slate-200", "focus:ring-primary/60", "focus:border-primary", "border-emerald-400");
    inputEl.setAttribute("aria-invalid", "true");
    errorEl.textContent = `⚠ ${message}`;
    errorEl.className = "field-error text-xs text-red-500 mt-1.5";
  } else {
    inputEl.classList.remove("border-red-400", "focus:ring-red-300", "focus:border-red-400");
    inputEl.classList.add("border-slate-200", "focus:ring-primary/60", "focus:border-primary");
    inputEl.setAttribute("aria-invalid", "false");
    errorEl.textContent = "";
    errorEl.className = "hidden";
  }
}

/** Show a positive confirmation message (e.g. "✓ Passwords matched") under a field. */
function setFieldSuccess(inputEl, errorEl, message) {
  if (!inputEl || !errorEl) return;
  inputEl.classList.remove("border-red-400", "focus:ring-red-300", "focus:border-red-400");
  inputEl.classList.add("border-emerald-400");
  inputEl.setAttribute("aria-invalid", "false");
  errorEl.textContent = `✓ ${message}`;
  errorEl.className = "field-error text-xs text-emerald-600 mt-1.5";
}

function validateEmail(value) {
  if (!value.trim()) return "Email is required.";
  if (!EMAIL_PATTERN.test(value.trim())) return "Please enter a valid email address.";
  return "";
}

function validatePassword(value) {
  if (!value) return "Password is required.";
  if (value.length < 8) return "Password must be at least 8 characters.";
  return "";
}

/* ---------------------------------------------------------------------- *
 * 5. LOGIN FORM (index.html only — safely no-ops elsewhere)
 * ---------------------------------------------------------------------- */
function initLoginForm() {
  const form = document.getElementById("loginForm");
  if (!form) return;

  const emailInput = document.getElementById("email");
  const emailError = document.getElementById("emailError");
  const passwordInput = document.getElementById("password");
  const passwordError = document.getElementById("passwordError");
  const rememberMe = document.getElementById("rememberMe");
  const loginBtn = document.getElementById("loginBtn");
  const loginBtnText = document.getElementById("loginBtnText");
  const loginSpinner = document.getElementById("loginSpinner");

  // Restore remembered email
  const rememberedEmail = localStorage.getItem("bhrs-remember-email");
  if (rememberedEmail) {
    emailInput.value = rememberedEmail;
    rememberMe.checked = true;
  }

  const runValidation = () => {
    const emailMsg = validateEmail(emailInput.value);
    const passwordMsg = validatePassword(passwordInput.value);

    setFieldError(emailInput, emailError, emailInput.value ? emailMsg : "");
    setFieldError(passwordInput, passwordError, passwordInput.value ? passwordMsg : "");

    const isValid = !emailMsg && !passwordMsg;
    loginBtn.disabled = !isValid;
    return isValid;
  };

  emailInput.addEventListener("input", runValidation);
  passwordInput.addEventListener("input", runValidation);
  emailInput.addEventListener("blur", () => setFieldError(emailInput, emailError, validateEmail(emailInput.value)));
  passwordInput.addEventListener("blur", () => setFieldError(passwordInput, passwordError, validatePassword(passwordInput.value)));

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const emailMsg = validateEmail(emailInput.value);
    const passwordMsg = validatePassword(passwordInput.value);
    setFieldError(emailInput, emailError, emailMsg);
    setFieldError(passwordInput, passwordError, passwordMsg);

    if (emailMsg || passwordMsg) {
      showToast("Please complete all required fields.", "error");
      return;
    }

    if (rememberMe.checked) {
      localStorage.setItem("bhrs-remember-email", emailInput.value.trim());
    } else {
      localStorage.removeItem("bhrs-remember-email");
    }

    loginBtn.disabled = true;
    loginBtnText.classList.add("hidden");
    loginSpinner.classList.remove("hidden");

    try {
      const response = await fetch("api/login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: emailInput.value.trim(),
          password: passwordInput.value,
        }),
      });

      const data = await response.json();
      loginSpinner.classList.add("hidden");
      loginBtnText.classList.remove("hidden");
      loginBtn.disabled = false;

      if (!response.ok || !data.success) {
        showToast(data.message || "Login failed.", "error");
        return;
      }

      showToast(`Welcome back, ${data.username}!`, "success");
      form.reset();
      if (rememberMe.checked) {
        emailInput.value = localStorage.getItem("bhrs-remember-email") || "";
      }
      runValidation();
      setTimeout(() => {
        window.location.href = "dashboard.php";
      }, 1000);
    } catch (error) {
      loginSpinner.classList.add("hidden");
      loginBtnText.classList.remove("hidden");
      loginBtn.disabled = false;
      showToast("Could not reach the server. Make sure XAMPP Apache and MySQL are running.", "error");
    }
  });

  runValidation();
}

/* ---------------------------------------------------------------------- *
 * 6. INIT
 * ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", () => {
  initDarkMode();
  initPasswordToggle("togglePassword", "password", "eyeOpen", "eyeClosed");
  initLoginForm();
});
