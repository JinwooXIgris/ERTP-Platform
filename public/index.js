/*
 * Landing-page behaviour and live API integration for ERTP Platform.
 * Loads event details, schedule, speakers, ticket categories, and session state.
 */
document.addEventListener("DOMContentLoaded", async () => {
  const menuButton = document.getElementById("menu-toggle");
  const nav = document.getElementById("primary-nav");
  const year = document.getElementById("current-year");

  if (year) year.textContent = new Date().getFullYear();

  // Mobile navigation toggle
  if (menuButton && nav) {
    menuButton.addEventListener("click", () => {
      const isOpen = nav.classList.toggle("is-open");
      menuButton.setAttribute("aria-expanded", String(isOpen));
      menuButton.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
      menuButton.textContent = isOpen ? "×" : "☰";
    });

    nav.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        nav.classList.remove("is-open");
        menuButton.setAttribute("aria-expanded", "false");
        menuButton.setAttribute("aria-label", "Open navigation");
        menuButton.textContent = "☰";
      });
    });
  }

  // Helper: Format date
  function formatEventDate(dateStr) {
    if (!dateStr) return "To be announced";
    try {
      const date = new Date(dateStr.replace(" ", "T"));
      if (isNaN(date.getTime())) return dateStr;
      return date.toLocaleDateString("en-NG", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit"
      });
    } catch {
      return dateStr;
    }
  }

  // Helper: Format Currency
  function formatMoney(amount) {
    const num = Number(amount) || 0;
    return "₦" + num.toLocaleString("en-NG", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  // 1. Load Auth State (me.php)
  async function loadAuthState() {
    try {
      const res = await (window.apiGet ? window.apiGet("/me.php") : fetch("/api/me.php").then(r => r.json()));
      const user = res?.data?.user;
      const navActions = document.querySelector(".nav-actions");
      if (!navActions) return;

      if (user) {
        let roleLinks = '';
        if (user.role === 'admin') {
          roleLinks += '<a class="btn btn-secondary" href="admin/admin-dashboard.html">Admin</a>';
        } else if (user.role === 'verifier') {
          roleLinks += '<a class="btn btn-secondary" href="verify/verify.html">Gate Verify</a>';
        }

        navActions.innerHTML = `
          ${roleLinks}
          <a class="btn btn-secondary" href="my-tickets/my-tickets.html">My Tickets</a>
          <button type="button" class="btn btn-secondary" id="logout-btn">Log out</button>
          <a class="btn btn-primary" href="tickets/tickets.html">Get tickets <span aria-hidden="true">↗</span></a>
          <button aria-controls="primary-nav" aria-expanded="false" aria-label="Open navigation" class="menu-toggle" id="menu-toggle">☰</button>
        `;

        document.getElementById("logout-btn")?.addEventListener("click", async () => {
          if (window.apiPost) {
            await window.apiPost("/logout.php");
          } else {
            await fetch("/api/logout.php", { method: "POST" });
          }
          window.location.reload();
        });
      }
    } catch (err) {
      console.warn("Could not load user auth state:", err);
    }
  }

  // 2. Load Event Details (event.php)
  async function loadEventData() {
    try {
      const res = await (window.apiGet ? window.apiGet("/event.php") : fetch("/api/event.php").then(r => r.json()));
      if (!res?.ok && !res?.success) return;
      const event = res?.data?.event;
      if (!event) return;

      // Update Date & Venue in Hero
      const dateEl = document.getElementById("meta-date");
      if (dateEl && event.event_date) {
        dateEl.textContent = formatEventDate(event.event_date);
      }

      const venueEl = document.getElementById("meta-venue");
      if (venueEl && event.venue) {
        venueEl.textContent = event.venue;
      }

      // Update Title & Descriptions
      const heroTitle = document.querySelector(".hero-copy h1");
      if (heroTitle && event.title) {
        heroTitle.innerHTML = `${event.title.replace(" 2026", "")}<br/><span>2026</span>`;
      }

      const heroDesc = document.querySelector(".hero-description");
      if (heroDesc && event.description) {
        heroDesc.textContent = event.description;
      }

      const aboutDesc = document.querySelector(".intro-copy p");
      if (aboutDesc && event.description) {
        aboutDesc.textContent = event.description;
      }

      // Update Schedule
      if (event.schedule) {
        const scheduleContainer = document.querySelector(".schedule-list");
        if (scheduleContainer) {
          const scheduleEntries = event.schedule.split("|").map(s => s.trim()).filter(Boolean);
          if (scheduleEntries.length > 0) {
            const symbols = ["↗", "✳", "⌘", "◎", "✦"];
            scheduleContainer.innerHTML = scheduleEntries.map((entry, idx) => {
              // Parse entry like "09:00 - 10:00 Registration & Breakfast" or "09:00 Keynote"
              let timeStr = "";
              let titleStr = entry;
              const timeMatch = entry.match(/^(\d{1,2}:\d{2}(?:\s*-\s*\d{1,2}:\d{2})?(?:\s*[AP]M)?)(?:\s*:?\s*)(.*)$/i);
              if (timeMatch) {
                timeStr = timeMatch[1].trim();
                titleStr = timeMatch[2].trim();
              }
              const sym = symbols[idx % symbols.length];
              return `
                <article class="schedule-item">
                  <div class="schedule-time">${timeStr || "SESSION"}</div>
                  <div class="schedule-marker"><span></span></div>
                  <div class="schedule-content">
                    <span class="schedule-type">PART ${String(idx + 1).padStart(2, "0")}</span>
                    <h3>${titleStr}</h3>
                  </div>
                  <div class="schedule-symbol">${sym}</div>
                </article>
              `;
            }).join("");

            const sampleNote = document.querySelector(".schedule-section .sample-note");
            if (sampleNote) sampleNote.textContent = "* Official conference schedule subject to minor operational adjustments.";
          }
        }
      }

      // Update Speakers
      if (event.speakers) {
        const speakerSection = document.getElementById("speakers");
        if (speakerSection) {
          const speakerDesc = speakerSection.querySelector(".container.speaker-inner > div p");
          if (speakerDesc) {
            speakerDesc.textContent = "Meet the esteemed innovators, researchers, and technology leaders presenting at UI Tech 2026.";
          }

          const speakerList = event.speakers.split("|").map(s => s.trim()).filter(Boolean);
          const orbitCenter = speakerSection.querySelector(".speaker-art");
          if (orbitCenter && speakerList.length > 0) {
            orbitCenter.innerHTML = `
              <div class="speaker-orbit orbit-1"></div>
              <div class="speaker-orbit orbit-2"></div>
              <div class="speaker-center" style="font-size: 0.95rem; line-height: 1.4;">KEYNOTE<br/><span>SPEAKERS</span></div>
              ${speakerList.slice(0, 4).map((sp, idx) => {
                const posClass = ["chip-one", "chip-two", "chip-three"][idx % 3];
                // Shorten name if too long for chip
                const shortName = sp.split("(")[0].trim();
                return `<span class="speaker-chip ${posClass}" title="${sp}" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${shortName}</span>`;
              }).join("")}
            `;
          }
        }
      }
    } catch (err) {
      console.warn("Could not load event data:", err);
    }
  }

  // 3. Load Ticket Categories (categories.php)
  async function loadCategories() {
    const listEl = document.getElementById("ticket-list");
    if (!listEl) return;

    try {
      const res = await (window.apiGet ? window.apiGet("/categories.php") : fetch("/api/categories.php").then(r => r.json()));
      const categories = res?.data?.categories || [];
      if (categories.length === 0) return;

      const icons = ["✳", "↗", "✦", "⌘"];
      listEl.innerHTML = categories.map((cat, idx) => {
        const isFeatured = cat.name.toLowerCase().includes("general") || cat.name.toLowerCase().includes("staff");
        const icon = icons[idx % icons.length];
        const isSoldOut = cat.sold_out || cat.remaining <= 0;
        
        return `
          <article class="ticket-card ${isFeatured ? 'ticket-featured' : ''}">
            ${isFeatured ? '<div class="popular-label">POPULAR PASS</div>' : ''}
            <div class="ticket-card-top">
              <span class="ticket-type">${cat.name.toUpperCase()} PASS</span>
              <span class="ticket-icon">${icon}</span>
            </div>
            <h3>${cat.name}</h3>
            <p class="ticket-description">${cat.remaining} tickets remaining (${cat.quota} total quota)</p>
            <div class="ticket-price">${formatMoney(cat.price)} <small>/ person</small></div>
            <ul>
              <li>Full conference access</li>
              <li>Official delegate badge</li>
              <li>Access to sessions &amp; networking</li>
            </ul>
            ${isSoldOut 
              ? `<button class="btn btn-secondary ticket-button" disabled style="opacity: 0.6; cursor: not-allowed;">Sold out</button>`
              : `<a class="btn ${isFeatured ? 'btn-primary' : 'btn-secondary'} ticket-button" href="tickets/tickets.html">Select ${cat.name} pass <span>→</span></a>`
            }
          </article>
        `;
      }).join("");

      const priceNote = document.querySelector(".tickets-section .sample-note");
      if (priceNote) {
        priceNote.textContent = "Live verified ticket categories and prices loaded from the University of Ibadan conference system.";
      }
    } catch (err) {
      console.warn("Could not load categories:", err);
    }
  }

  // Run all three in parallel
  await Promise.all([loadAuthState(), loadEventData(), loadCategories()]);
});
