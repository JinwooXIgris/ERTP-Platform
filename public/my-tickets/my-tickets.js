(() => {
  'use strict';

  const list = document.getElementById('ticket-list');
  const empty = document.getElementById('empty-state');
  const notice = document.getElementById('notice');

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

  function makeCard(t) {
    const isUsed = t.status === 'used';
    const statusLabel = isUsed ? 'Used at Gate' : 'Valid / Issued';
    const statusClass = isUsed ? 'failed' : 'confirmed';

    const article = document.createElement('article');
    article.className = 'ticket-card';
    article.innerHTML = `
      <div class="ticket-main">
        <div class="ticket-mark" aria-hidden="true">${isUsed ? '✓' : '▤'}</div>
        <div class="ticket-info">
          <div class="ticket-top">
            <h3>${t.category} Pass</h3>
            <span class="status ${statusClass}">${statusLabel}</span>
          </div>
          <p>${t.event_title || 'University of Ibadan Technology Conference 2026'}</p>
          <div class="meta-grid">
            <div>
              <span>ATTENDEE</span>
              <strong>${t.holder_name || 'Attendee'}</strong>
            </div>
            <div>
              <span>TICKET NUMBER</span>
              <strong style="font-family: monospace;">${t.ticket_number}</strong>
            </div>
            <div>
              <span>EVENT DATE</span>
              <strong>${formatDate(t.event_date)}</strong>
            </div>
            <div>
              <span>ORDER ID</span>
              <strong>#${t.order_id}</strong>
            </div>
          </div>
        </div>
      </div>
      <div class="ticket-actions">
        <a class="button open-ticket" href="../ticket-view/ticket-view.html?order_id=${t.order_id}">View / Print Ticket</a>
        <button class="button button-outline print-single" type="button">Print</button>
        <button class="text-button copy-ref" type="button">Copy ticket #</button>
      </div>
    `;

    article.querySelector('.print-single').addEventListener('click', () => {
      window.location.href = `../ticket-view/ticket-view.html?order_id=${t.order_id}`;
    });

    article.querySelector('.copy-ref').addEventListener('click', async (e) => {
      try {
        await navigator.clipboard.writeText(String(t.ticket_number));
        e.currentTarget.textContent = 'Copied!';
        setTimeout(() => { e.currentTarget.textContent = 'Copy ticket #'; }, 2000);
      } catch {
        if (notice) {
          notice.hidden = false;
          notice.textContent = `Ticket number: ${t.ticket_number}`;
        }
      }
    });

    return article;
  }

  async function loadMyTickets() {
    try {
      const res = await (window.apiGet ? window.apiGet('/my-tickets.php') : fetch('/api/my-tickets.php').then(r => r.json()));

      if (res.status === 401 || (res.errors && res.errors.some(e => e.message?.includes('Authentication')))) {
        if (notice) {
          notice.hidden = false;
          notice.textContent = 'Please sign in to view your tickets. Redirecting to sign in…';
        }
        setTimeout(() => {
          window.location.href = '../login/login.html?next=../my-tickets/my-tickets.html';
        }, 1200);
        return;
      }

      if (!res.ok && res.success !== true) {
        if (notice) {
          notice.hidden = false;
          notice.textContent = res.message || 'Unable to retrieve your tickets. Please try again.';
        }
        return;
      }

      const tickets = res.data?.tickets || [];
      if (list) list.innerHTML = '';

      if (tickets.length > 0) {
        tickets.forEach(t => {
          list.appendChild(makeCard(t));
        });
        if (empty) empty.hidden = true;
        if (notice) {
          notice.hidden = false;
          notice.className = 'notice';
          notice.textContent = `You have ${tickets.length} confirmed conference ticket${tickets.length === 1 ? '' : 's'}. Click "View / Print Ticket" to display your digital QR code.`;
        }
      } else {
        if (empty) empty.hidden = false;
        if (notice) notice.hidden = true;
      }
    } catch (err) {
      if (notice) {
        notice.hidden = false;
        notice.textContent = 'Network error loading tickets. Please check your connection and refresh.';
      }
    }
  }

  document.addEventListener('DOMContentLoaded', loadMyTickets);
})();
