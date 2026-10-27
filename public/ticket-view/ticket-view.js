(() => {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const orderId = params.get('order_id') || sessionStorage.getItem('currentOrderId');
  const container = document.getElementById('tickets-list-container');
  const noticeEl = document.getElementById('ticketNotice');

  function formatDate(dateStr) {
    if (!dateStr) return "To be announced";
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

  // Fallback SVG QR generator in case QRCode class is not defined
  function generateFallbackQR(code) {
    // Generate an SVG matrix visualization
    let hash = 0;
    for (let i = 0; i < code.length; i++) {
      hash = ((hash << 5) - hash) + code.charCodeAt(i);
      hash |= 0;
    }
    const size = 21;
    let rects = '';
    // Draw finder patterns at 3 corners
    function drawFinder(x, y) {
      let f = `<rect x="${x}" y="${y}" width="7" height="7" fill="#0f172a"/>`;
      f += `<rect x="${x+1}" y="${y+1}" width="5" height="5" fill="#ffffff"/>`;
      f += `<rect x="${x+2}" y="${y+2}" width="3" height="3" fill="#0f172a"/>`;
      return f;
    }
    rects += drawFinder(0, 0);
    rects += drawFinder(14, 0);
    rects += drawFinder(0, 14);

    // Pseudorandom grid based on code characters
    for (let r = 0; r < size; r++) {
      for (let c = 0; c < size; c++) {
        // Skip finders
        if ((r < 8 && c < 8) || (r < 8 && c > 12) || (r > 12 && c < 8)) continue;
        const charCode = code.charCodeAt((r * size + c) % code.length);
        if ((charCode + r + c) % 2 === 0) {
          rects += `<rect x="${c}" y="${r}" width="1" height="1" fill="#0f172a"/>`;
        }
      }
    }

    return `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21" width="130" height="130" shape-rendering="crispEdges">
        <rect width="21" height="21" fill="#ffffff"/>
        ${rects}
      </svg>
    `;
  }

  function renderQRCode(targetEl, code) {
    if (typeof QRCode === 'function') {
      try {
        targetEl.innerHTML = '';
        new QRCode(targetEl, {
          text: code,
          width: 130,
          height: 130,
          colorDark: "#0f172a",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.M
        });
        return;
      } catch (e) {
        console.warn("QRCodeJS generation failed, using SVG fallback:", e);
      }
    }
    // Fallback
    targetEl.innerHTML = generateFallbackQR(code);
  }

  function renderTickets(tickets) {
    if (!container) return;
    if (!tickets || tickets.length === 0) {
      container.innerHTML = `
        <div class="notice" style="background: #fff; padding: 25px; border-radius: 8px; text-align: center;">
          <p>No tickets found for this order.</p>
          <a class="button-link" href="../my-tickets/my-tickets.html">← Go to My Tickets</a>
        </div>
      `;
      return;
    }

    container.innerHTML = tickets.map((t, index) => {
      const isUsed = t.status === 'used';
      const statusText = isUsed ? 'ALREADY USED' : 'VALID / ADMIT ONE';
      const statusColor = isUsed ? '#d97706' : '#16a34a';
      const statusBg = isUsed ? 'rgba(217, 119, 6, 0.12)' : 'rgba(22, 163, 74, 0.12)';

      return `
        <article aria-label="Digital ticket" class="ticket-card" id="ticket-card-${t.id}" style="margin-bottom: 25px;">
          <div class="ticket-banner">
            <img alt="University of Ibadan brand banner" class="brand-banner" src="../assets/images/ui-brand-banner.png"/>
            <div class="event-label">
              <span>UNIVERSITY OF IBADAN</span>
              <strong>${t.event_title || 'TECHNOLOGY CONFERENCE 2026'}</strong>
              <small>CONNECT · LEARN · INNOVATE</small>
            </div>
          </div>
          <div class="ticket-body">
            <div class="ticket-topline" style="display: flex; justify-content: space-between; align-items: center;">
              <span class="status-pill" style="background: ${statusBg}; color: ${statusColor}; border: 1px solid ${statusColor}; font-weight: 700;">
                ${statusText}
              </span>
              <span class="pass-type" style="font-weight: 700; color: #1e3a8a;">
                ${(t.category || 'GENERAL').toUpperCase()} PASS ${tickets.length > 1 ? `(#${index + 1})` : ''}
              </span>
            </div>
            <h2>${t.holder_name || 'Attendee'}</h2>
            <p class="attendee-email" style="color: #64748b;">University of Ibadan Tech Delegate</p>
            <div class="details-grid">
              <div>
                <span class="detail-label">TICKET NUMBER</span>
                <strong style="font-family: monospace; font-size: 1.15rem; color: #0f172a;">${t.ticket_number}</strong>
              </div>
              <div>
                <span class="detail-label">TICKET CATEGORY</span>
                <strong>${t.category} Pass</strong>
              </div>
              <div>
                <span class="detail-label">EVENT DATE</span>
                <strong>${formatDate(t.event_date)}</strong>
              </div>
              <div>
                <span class="detail-label">GATE ADMISSION</span>
                <strong>Main Auditorium Gate</strong>
              </div>
            </div>
            <div class="ticket-divider"><span></span><span></span></div>
            <div class="entry-row">
              <div class="entry-copy">
                <strong>Present your ticket at check-in</strong>
                <p>Gate officers can scan this QR code or enter your ticket number <code>${t.ticket_number}</code> into the gate verification system.</p>
                ${isUsed ? `<p style="color: #d97706; font-size: 0.85rem; margin-top: 5px;">⚠️ Ticket marked as used at gate.</p>` : ''}
              </div>
              <div class="qr-container" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; min-width: 140px;">
                <div id="qr-target-${t.id}"></div>
                <small style="margin-top: 6px; font-family: monospace; font-size: 0.75rem; color: #64748b;">
                  ${t.ticket_number}
                </small>
              </div>
            </div>
          </div>
          <div class="ticket-footer">
            <span>UI TECH CONFERENCE 2026</span>
            <span>RETAIN FOR CHECK-IN</span>
          </div>
        </article>
      `;
    }).join('');

    // Generate QR codes for each ticket
    tickets.forEach(t => {
      const qrTarget = document.getElementById(`qr-target-${t.id}`);
      if (qrTarget) {
        renderQRCode(qrTarget, t.verification_code || t.ticket_number);
      }
    });

    if (noticeEl) {
      noticeEl.innerHTML = `<strong>Verified Order:</strong> Displaying ${tickets.length} digital admission ticket${tickets.length === 1 ? '' : 's'}. You can print this page or present the QR code directly on your mobile device at the venue.`;
    }
  }

  async function loadTickets() {
    if (!orderId) {
      // Try to load newest tickets via my-tickets
      try {
        const myRes = await (window.apiGet ? window.apiGet('/my-tickets.php') : fetch('/api/my-tickets.php').then(r => r.json()));
        if (myRes?.ok || myRes?.success) {
          const allTickets = myRes.data?.tickets || [];
          if (allTickets.length > 0) {
            renderTickets(allTickets);
            return;
          }
        }
      } catch {}

      if (container) {
        container.innerHTML = `
          <div class="notice" style="background: #fff; padding: 25px; border-radius: 8px; text-align: center;">
            <p>No order specified. View all your purchased tickets from your attendee portal.</p>
            <a class="button-link" href="../my-tickets/my-tickets.html">← Go to My Tickets</a>
          </div>
        `;
      }
      return;
    }

    try {
      const res = await (window.apiGet ? window.apiGet('/ticket.php', { order_id: orderId }) : fetch(`/api/ticket.php?order_id=${orderId}`).then(r => r.json()));

      if (res.status === 401 || (res.errors && res.errors.some(e => e.message?.includes('Authentication')))) {
        if (noticeEl) noticeEl.textContent = 'Please sign in to view your tickets. Redirecting…';
        setTimeout(() => {
          window.location.href = `../login/login.html?next=../ticket-view/ticket-view.html?order_id=${orderId}`;
        }, 1200);
        return;
      }

      if (!res.ok && res.success !== true) {
        if (container) {
          container.innerHTML = `
            <div class="notice" style="background: #fee2e2; color: #991b1b; padding: 25px; border-radius: 8px; text-align: center;">
              <p>${res.message || 'Unable to load tickets for this order.'}</p>
              <a class="button-link" href="../my-tickets/my-tickets.html">← View My Tickets</a>
            </div>
          `;
        }
        return;
      }

      const tickets = res.data?.tickets || [];
      renderTickets(tickets);
    } catch (err) {
      if (container) {
        container.innerHTML = `
          <div class="notice" style="background: #fee2e2; color: #991b1b; padding: 25px; border-radius: 8px; text-align: center;">
            <p>Error connecting to ticket service. Please check your connection and refresh.</p>
          </div>
        `;
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('printTicket')?.addEventListener('click', () => window.print());
    loadTickets();
  });
})();