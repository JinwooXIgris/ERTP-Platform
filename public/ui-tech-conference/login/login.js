// Attendee/admin sign-in. Redirect only after the server confirms authentication.
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("#login-form");
  const email = document.querySelector("#email");
  const password = document.querySelector("#password");
  const emailError = document.querySelector("#email-error");
  const passwordError = document.querySelector("#password-error");
  const message = document.querySelector("#form-message");
  const togglePassword = document.querySelector("#toggle-password");
  const forgotPassword = document.querySelector("#forgot-password");
  const submitButton = document.querySelector("#sign-in-submit");
  const year = document.querySelector("#current-year");

  if (year) year.textContent = new Date().getFullYear();

  // Show the UI monogram if the crest cannot load.
  document.querySelectorAll(".university-logo, .mobile-university-logo").forEach((img) => {
    const mark = img.nextElementSibling;
    const showFallback = () => {
      if (img.closest(".brand-image-wrap")) {
        img.closest(".brand-image-wrap").classList.remove("has-logo");
        if (mark) mark.style.display = "grid";
      } else if (img.closest(".mobile-logo-row")) {
        img.closest(".mobile-logo-row").classList.add("logo-missing");
      }
    };
    const hideFallback = () => {
      if (img.closest(".brand-image-wrap")) img.closest(".brand-image-wrap").classList.add("has-logo");
      if (img.closest(".mobile-logo-row")) img.closest(".mobile-logo-row").classList.remove("logo-missing");
    };
    img.addEventListener("error", showFallback, { once: true });
    img.addEventListener("load", hideFallback, { once: true });
    if (img.complete && img.naturalWidth > 0) hideFallback();
    else if (img.complete) showFallback();
  });

  const campusPhoto = document.querySelector(".campus-photo");
  const imageNote = document.querySelector(".image-credit-note");
  if (campusPhoto && imageNote) {
    const hideImageNote = () => { imageNote.hidden = true; };
    campusPhoto.addEventListener("load", hideImageNote, { once: true });
    if (campusPhoto.complete && campusPhoto.naturalWidth > 0) hideImageNote();
  }

  togglePassword?.addEventListener("click", () => {
    const showing = password.type === "text";
    password.type = showing ? "password" : "text";
    togglePassword.textContent = showing ? "Show" : "Hide";
    togglePassword.setAttribute("aria-label", showing ? "Show password" : "Hide password");
    togglePassword.setAttribute("aria-pressed", String(!showing));
    password.focus();
  });

  forgotPassword?.addEventListener("click", () => {
    clearErrors();
    showMessage("Password recovery is not connected yet. If you cannot sign in, contact the conference team or create an account first.", "info");
  });

  email?.addEventListener("input", () => { if (emailError) emailError.textContent = ""; clearMessage(); });
  password?.addEventListener("input", () => { if (passwordError) passwordError.textContent = ""; clearMessage(); });

  const params = new URLSearchParams(window.location.search);
  if (params.get("registered") === "1") {
    showMessage("Your account has been created. Sign in with the email address and password you registered with.", "success");
  }

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearErrors();
    clearMessage();
    let valid = true;
    const emailValue = email.value.trim();

    if (!emailValue) {
      emailError.textContent = "Enter your email address.";
      valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
      emailError.textContent = "Enter a valid email address.";
      valid = false;
    }

    if (!password.value) {
      passwordError.textContent = "Enter your password.";
      valid = false;
    } else if (password.value.length < 8) {
      passwordError.textContent = "Your password must be at least 8 characters.";
      valid = false;
    }

    if (!valid) {
      showMessage("Please check the highlighted fields and try again.", "error");
      return;
    }

    if (typeof apiRequest !== "function") {
      showMessage("The sign-in service could not load. Check the shared API script path.", "error");
      return;
    }

    const originalLabel = submitButton?.querySelector("span:first-child")?.textContent || "Sign in";
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute("aria-busy", "true");
      const label = submitButton.querySelector("span:first-child");
      if (label) label.textContent = "Signing in…";
    }

    let redirecting = false;
    try {
      const result = await apiRequest("login.php", {
        method: "POST",
        body: {
          email: emailValue,
          password: password.value,
          remember_me: Boolean(document.querySelector("#remember-me")?.checked)
        }
      });

      const role = getRole(result);
      const destination = getDestination(role);
      showMessage(result.message || "Sign-in successful. Redirecting…", "success");
      redirecting = true;
      window.setTimeout(() => { window.location.href = destination; }, 450);
    } catch (error) {
      if (error?.status === 404 || /unexpected response/i.test(error?.message || "")) {
        showMessage("The sign-in endpoint is not available yet. The backend needs to provide /api/login.php before accounts can sign in.", "error");
      } else {
        showMessage(error?.message || "We could not sign you in. Please try again.", "error");
      }
    } finally {
      if (!redirecting && submitButton) {
        submitButton.disabled = false;
        submitButton.removeAttribute("aria-busy");
        const label = submitButton.querySelector("span:first-child");
        if (label) label.textContent = originalLabel;
      }
    }
  });

  function getRole(payload) {
    const value = payload?.user?.role
      ?? payload?.data?.user?.role
      ?? payload?.data?.role
      ?? payload?.role
      ?? "attendee";
    return String(value).trim().toLowerCase();
  }

  function getDestination(role) {
    if (["admin", "administrator", "staff_admin"].includes(role)) {
      return "../admin/admin-dashboard.html";
    }

    // Respect an explicit, known in-site destination; never accept an arbitrary URL.
    const next = new URLSearchParams(window.location.search).get("next");
    if (next === "checkout") return "../checkout/checkout.html";
    if (next === "tickets") return "../tickets/tickets.html";

    // If the attendee selected tickets before signing in, continue to checkout.
    // Otherwise start at the ticket-selection page after a regular sign-in.
    return hasSavedSelection() ? "../checkout/checkout.html" : "../tickets/tickets.html";
  }

  function hasSavedSelection() {
    try {
      const saved = JSON.parse(localStorage.getItem("uiTechTicketSelection") || "null");
      const tickets = Array.isArray(saved) ? saved : Array.isArray(saved?.tickets) ? saved.tickets : [];
      return tickets.some((ticket) => Number(ticket?.quantity ?? ticket?.qty ?? 0) > 0);
    } catch {
      return false;
    }
  }

  function clearErrors() {
    if (emailError) emailError.textContent = "";
    if (passwordError) passwordError.textContent = "";
  }
  function clearMessage() {
    if (!message) return;
    message.hidden = true;
    message.textContent = "";
    message.className = "form-message";
  }
  function showMessage(text, type) {
    if (!message) return;
    message.textContent = text;
    message.className = `form-message is-${type}`;
    message.hidden = false;
  }
});
