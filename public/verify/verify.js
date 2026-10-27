// Gate Verification script wired to POST /api/verify-ticket.php
(() => {
  'use strict';

  const verifyForm = document.querySelector("#verify-form");
  const referenceInput = document.querySelector("#ticket-reference");
  const resultBox = document.querySelector("#verification-result");
  const verifyBtn = document.querySelector("#verify-button");

  function formatDate(dateStr) {
    if (!dateStr) return "N/A";
    try {
      const d = new Date(dateStr.replace(" ", "T"));
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString("en-NG", {
        weekday: "short", year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit"
      });
    } catch {
      return dateStr;
    }
  }

  function showResultHTML(html, type) {
    if (!resultBox) return;
    resultBox.innerHTML = html;
    resultBox.className = `result ${type}`;
    resultBox.hidden = false;
    resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  verifyForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const reference = referenceInput.value.trim();

    if (!reference) {
      showResultHTML('<p style="margin:0; font-weight:600;">Enter a ticket number or QR verification code before verifying.</p>', "error");
      referenceInput.focus();
      return;
    }

    if (verifyBtn) {
      verifyBtn.disabled = true;
      verifyBtn.textContent = "Verifying…";
    }

    try {
      const res = await (window.apiPost ? window.apiPost('/verify-ticket.php', { value: reference }) : fetch('/api/verify-ticket.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ value: reference })
      }).then(r => r.json()));

      if (res.status === 401) {
        showResultHTML(`
          <div style="color: #991b1b; background: #fee2e2; padding: 15px; border-radius: 8px; border: 1px solid #f87171;">
            <strong style="display:block; margin-bottom:5px;">Authentication Required</strong>
            <p style="margin:0 0 10px;">You must be signed in as a gate verifier or administrator to scan tickets.</p>
            <a class="button-link" href="../login/login.html?next=../verify/verify.html">Sign In Here</a>
          </div>
        `, "error");
        return;
      }

      if (res.status === 403) {
        showResultHTML(`
          <div style="color: #991b1b; background: #fee2e2; padding: 15px; border-radius: 8px; border: 1px solid #f87171;">
            <strong style="display:block; margin-bottom:5px;">Access Forbidden</strong>
            <p style="margin:0;">Your account does not have gate verifier or admin permissions.</p>
          </div>
        `, "error");
        return;
      }

      const outcome = res.data?.result || (res.ok ? "valid" : "invalid");
      const ticket = res.data?.ticket;

      if (outcome === "valid") {
        showResultHTML(`
          <div style="background: #f0fdf4; border: 1.5px solid #22c55e; border-radius: 10px; padding: 18px; color: #166534;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
              <span style="display:inline-block; padding:4px 10px; background:#22c55e; color:#fff; font-weight:800; border-radius:6px; font-size:13px;">
                ✓ VALID TICKET — ADMIT ATTENDEE
              </span>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; font-size:14px; margin-top:10px;">
              <div><span style="color:#15803d; font-size:11px; font-weight:700; display:block;">HOLDER NAME</span><strong>${ticket?.holder_name || 'Attendee'}</strong></div>
              <div><span style="color:#15803d; font-size:11px; font-weight:700; display:block;">CATEGORY</span><strong>${ticket?.category || 'General'} Pass</strong></div>
              <div><span style="color:#15803d; font-size:11px; font-weight:700; display:block;">TICKET NUMBER</span><strong style="font-family:monospace;">${ticket?.ticket_number || reference}</strong></div>
            </div>
            <p style="margin:12px 0 0; font-size:12px; color:#166534;">Ticket has been successfully marked as <strong>used</strong> in the system.</p>
          </div>
        `, "info");
      } else if (outcome === "used") {
        showResultHTML(`
          <div style="background: #fffbeb; border: 1.5px solid #f59e0b; border-radius: 10px; padding: 18px; color: #92400e;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
              <span style="display:inline-block; padding:4px 10px; background:#f59e0b; color:#fff; font-weight:800; border-radius:6px; font-size:13px;">
                ⚠️ ALREADY USED — DO NOT ADMIT
              </span>
            </div>
            <p style="margin:0 0 10px; font-size:13px;">This ticket was already scanned and used at the gate.</p>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; font-size:14px; margin-top:10px;">
              <div><span style="color:#b45309; font-size:11px; font-weight:700; display:block;">HOLDER NAME</span><strong>${ticket?.holder_name || 'Attendee'}</strong></div>
              <div><span style="color:#b45309; font-size:11px; font-weight:700; display:block;">CATEGORY</span><strong>${ticket?.category || 'General'} Pass</strong></div>
              <div><span style="color:#b45309; font-size:11px; font-weight:700; display:block;">USED AT</span><strong>${formatDate(ticket?.used_at)}</strong></div>
              <div><span style="color:#b45309; font-size:11px; font-weight:700; display:block;">TICKET NUMBER</span><strong style="font-family:monospace;">${ticket?.ticket_number || reference}</strong></div>
            </div>
          </div>
        `, "info");
      } else {
        // Invalid
        showResultHTML(`
          <div style="background: #fef2f2; border: 1.5px solid #ef4444; border-radius: 10px; padding: 18px; color: #991b1b;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
              <span style="display:inline-block; padding:4px 10px; background:#ef4444; color:#fff; font-weight:800; border-radius:6px; font-size:13px;">
                ✕ INVALID / NOT FOUND
              </span>
            </div>
            <p style="margin:0; font-size:13px;">No matching ticket found for: <code style="font-family:monospace; background:rgba(0,0,0,0.06); padding:2px 5px; border-radius:3px;">${reference}</code>. Check the ticket number or QR code and try again.</p>
          </div>
        `, "error");
      }
    } catch (err) {
      showResultHTML(`
        <div style="color: #991b1b; background: #fee2e2; padding: 15px; border-radius: 8px;">
          <strong style="display:block; margin-bottom:5px;">Network Error</strong>
          <p style="margin:0;">Could not connect to the verification server. Please check your connection.</p>
        </div>
      `, "error");
    } finally {
      if (verifyBtn) {
        verifyBtn.disabled = false;
        verifyBtn.textContent = "Verify";
      }
    }
  });
})();
