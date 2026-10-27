(() => {
  'use strict';

  const STORAGE_KEY = 'uiTechTicketSelection';
  const MAX_PER_ORDER = 10;
  let categories = [];
  const quantities = {}; // category_id -> quantity

  const $ = (id) => document.getElementById(id);

  function money(amount) {
    const num = Number(amount) || 0;
    return new Intl.NumberFormat('en-NG', {
      style: 'currency', currency: 'NGN', maximumFractionDigits: 0
    }).format(num);
  }

  function showMessage(message, type = 'error') {
    const target = $('status-message');
    if (!target) return;
    target.textContent = message;
    target.className = `status-message is-${type}`;
    target.hidden = false;
  }

  function clearMessage() {
    const target = $('status-message');
    if (target) {
      target.hidden = true;
      target.textContent = '';
    }
  }

  function updateOrderBar() {
    let total = 0;
    let totalTickets = 0;
    let selectedCategoriesCount = 0;

    categories.forEach(cat => {
      const qty = quantities[cat.id] || 0;
      const price = Number(cat.price) || 0;
      const lineTotal = qty * price;
      total += lineTotal;
      totalTickets += qty;
      if (qty > 0) selectedCategoriesCount++;

      const lineTotalEl = document.getElementById(`line-total-${cat.id}`);
      if (lineTotalEl) {
        lineTotalEl.textContent = money(lineTotal);
      }

      const card = document.querySelector(`[data-category-id="${cat.id}"]`);
      if (card) {
        card.classList.toggle('selected', qty > 0);
      }
    });

    const grandTotalEl = $('grand-total');
    if (grandTotalEl) grandTotalEl.textContent = money(total);

    const countEl = $('selection-count');
    if (countEl) {
      countEl.textContent = totalTickets === 0
        ? 'No tickets selected yet'
        : `${totalTickets} ticket${totalTickets === 1 ? '' : 's'} selected across ${selectedCategoriesCount} categor${selectedCategoriesCount === 1 ? 'y' : 'ies'}`;
    }

    const continueBtn = $('continue-button');
    if (continueBtn) {
      continueBtn.disabled = totalTickets === 0 || totalTickets > MAX_PER_ORDER;
    }
  }

  function setCategoryQuantity(catId, value) {
    const cat = categories.find(c => c.id === catId);
    if (!cat) return;

    const remaining = cat.remaining !== undefined ? cat.remaining : cat.quota;
    const maxAllowed = Math.min(MAX_PER_ORDER, remaining);

    let safeValue = Math.max(0, Math.min(maxAllowed, Math.floor(Number(value) || 0)));
    
    // Check total order limit
    const otherQtySum = Object.entries(quantities).reduce((sum, [id, q]) => {
      return Number(id) === catId ? sum : sum + q;
    }, 0);

    if (otherQtySum + safeValue > MAX_PER_ORDER) {
      safeValue = MAX_PER_ORDER - otherQtySum;
      showMessage(`You can select at most ${MAX_PER_ORDER} tickets in total per order.`, 'error');
    } else {
      clearMessage();
    }

    quantities[catId] = safeValue;

    const input = document.getElementById(`qty-${catId}`);
    if (input) input.value = String(safeValue);

    updateOrderBar();
  }

  function renderCategoryCards() {
    const grid = document.querySelector('.ticket-grid');
    if (!grid) return;

    if (!categories || categories.length === 0) {
      grid.innerHTML = '<p class="page-note">Loading ticket categories from the server…</p>';
      return;
    }

    const icons = ['▤', '✦', '♛', '⌘'];
    grid.innerHTML = categories.map((cat, idx) => {
      const isFeatured = cat.name.toLowerCase().includes('general') || cat.name.toLowerCase().includes('staff');
      const icon = icons[idx % icons.length];
      const isSoldOut = cat.sold_out || cat.remaining <= 0;
      const currentQty = quantities[cat.id] || 0;
      const maxAllowed = Math.min(MAX_PER_ORDER, cat.remaining || 0);

      return `
        <article class="ticket-card ${isFeatured ? 'featured' : ''} ${isSoldOut ? 'sold-out' : ''}" data-category-id="${cat.id}">
          ${isFeatured ? '<div class="popular-ribbon">POPULAR CHOICE</div>' : ''}
          <div class="card-top">
            <span aria-hidden="true" class="ticket-icon">${icon}</span>
            <span class="ticket-tag">${cat.remaining} remaining of ${cat.quota}</span>
          </div>
          <h3>${cat.name} Pass</h3>
          <p class="ticket-description">Access pass for ${cat.name.toLowerCase()} delegates.</p>
          <div class="price-line">
            <strong>${money(cat.price)}</strong>
            <span>/ person</span>
          </div>
          <ul class="benefits">
            <li>Full conference access</li>
            <li>All main talks and sessions</li>
            <li>Digital ticket with QR verification</li>
          </ul>
          ${isSoldOut ? `
            <div class="sold-out-badge" style="padding: 10px; background: rgba(220,53,69,0.1); color: #dc3545; font-weight: 600; text-align: center; border-radius: 6px; margin-top: 15px;">
              SOLD OUT
            </div>
          ` : `
            <div class="quantity-control">
              <label for="qty-${cat.id}">Quantity</label>
              <div class="stepper">
                <button type="button" aria-label="Decrease quantity" class="btn-step" data-cat-id="${cat.id}" data-delta="-1">−</button>
                <input type="number" id="qty-${cat.id}" inputmode="numeric" min="0" max="${maxAllowed}" value="${currentQty}" aria-label="${cat.name} quantity" />
                <button type="button" aria-label="Increase quantity" class="btn-step" data-cat-id="${cat.id}" data-delta="1">+</button>
              </div>
            </div>
            <div class="line-total">
              <span>Ticket total</span>
              <strong id="line-total-${cat.id}">${money((Number(cat.price) || 0) * currentQty)}</strong>
            </div>
          `}
        </article>
      `;
    }).join('');

    // Attach event listeners to steppers
    grid.querySelectorAll('.btn-step').forEach(btn => {
      btn.addEventListener('click', () => {
        const catId = Number(btn.dataset.catId);
        const delta = Number(btn.dataset.delta);
        const current = quantities[catId] || 0;
        setCategoryQuantity(catId, current + delta);
      });
    });

    grid.querySelectorAll('.stepper input').forEach(input => {
      const catId = Number(input.id.replace('qty-', ''));
      input.addEventListener('input', () => setCategoryQuantity(catId, input.value));
      input.addEventListener('change', () => setCategoryQuantity(catId, input.value));
    });

    updateOrderBar();
  }

  async function handleContinue() {
    clearMessage();
    const items = [];
    Object.entries(quantities).forEach(([id, qty]) => {
      if (qty > 0) {
        items.push({ category_id: Number(id), quantity: Number(qty) });
      }
    });

    if (items.length === 0) {
      showMessage('Please select at least one ticket before continuing.');
      return;
    }

    const totalQty = items.reduce((sum, item) => sum + item.quantity, 0);
    if (totalQty > MAX_PER_ORDER) {
      showMessage(`Total tickets in an order cannot exceed ${MAX_PER_ORDER}.`);
      return;
    }

    const continueBtn = $('continue-button');
    if (continueBtn) {
      continueBtn.disabled = true;
      continueBtn.textContent = 'Creating order…';
    }

    try {
      const res = await (window.apiPost ? window.apiPost('/order.php', { items }) : fetch('/api/order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ items })
      }).then(r => r.json()));

      if (res.status === 401 || (res.errors && res.errors.some(e => e.message?.includes('Authentication')))) {
        // Save current draft selection and redirect to login
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ items }));
        showMessage('Please sign in or create an account to complete your order. Redirecting to sign in…', 'info');
        setTimeout(() => {
          window.location.href = '../login/login.html?next=../tickets/tickets.html';
        }, 1200);
        return;
      }

      if (!res.ok && res.success !== true) {
        const errorMsg = res.message || (res.errors && res.errors[0]?.message) || 'Unable to create order. Please try again.';
        showMessage(errorMsg, 'error');
        if (continueBtn) {
          continueBtn.disabled = false;
          continueBtn.innerHTML = 'Continue to checkout <span aria-hidden="true">→</span>';
        }
        return;
      }

      const orderId = res.data?.order?.id;
      if (orderId) {
        localStorage.removeItem(STORAGE_KEY);
        sessionStorage.setItem('currentOrderId', String(orderId));
        window.location.href = `../checkout/checkout.html?order_id=${orderId}`;
      } else {
        throw new Error('Order ID missing in response.');
      }
    } catch (err) {
      showMessage(err.message || 'Unable to connect to the server. Please check your connection.', 'error');
      if (continueBtn) {
        continueBtn.disabled = false;
        continueBtn.innerHTML = 'Continue to checkout <span aria-hidden="true">→</span>';
      }
    }
  }

  // Load Categories & Auth
  async function init() {
    $('current-year').textContent = new Date().getFullYear();

    // Check auth for nav
    try {
      const authRes = await (window.apiGet ? window.apiGet('/me.php') : fetch('/api/me.php').then(r => r.json()));
      const user = authRes?.data?.user;
      if (user) {
        const navLogin = document.querySelector('.nav-login');
        if (navLogin) {
          navLogin.textContent = 'Sign out';
          navLogin.href = '#';
          navLogin.addEventListener('click', async (e) => {
            e.preventDefault();
            if (window.apiPost) await window.apiPost('/logout.php');
            else await fetch('/api/logout.php', { method: 'POST' });
            window.location.reload();
          });
        }
      }
    } catch {}

    // Load Event details for hero meta
    try {
      const evRes = await (window.apiGet ? window.apiGet('/event.php') : fetch('/api/event.php').then(r => r.json()));
      const ev = evRes?.data?.event;
      if (ev) {
        const heroMeta = document.querySelector('.hero-meta');
        if (heroMeta && ev.event_date) {
          const date = new Date(ev.event_date.replace(' ', 'T'));
          const formatted = isNaN(date.getTime()) ? ev.event_date : date.toLocaleDateString('en-NG', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
          });
          heroMeta.innerHTML = `
            <span><b aria-hidden="true">◷</b> ${formatted}</span>
            <span><b aria-hidden="true">⌖</b> ${ev.venue || 'University of Ibadan'}</span>
          `;
        }
      }
    } catch {}

    // Load Categories from API
    try {
      const res = await (window.apiGet ? window.apiGet('/categories.php') : fetch('/api/categories.php').then(r => r.json()));
      categories = res?.data?.categories || [];
      categories.forEach(cat => {
        quantities[cat.id] = 0;
      });

      renderCategoryCards();

      // Check if draft items exist in localStorage
      try {
        const draft = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (draft?.items && Array.isArray(draft.items)) {
          draft.items.forEach(item => {
            if (item.category_id && quantities[item.category_id] !== undefined) {
              setCategoryQuantity(item.category_id, item.quantity);
            }
          });
        }
      } catch {}

      const continueBtn = $('continue-button');
      if (continueBtn) {
        continueBtn.addEventListener('click', handleContinue);
      }
    } catch (err) {
      showMessage('Unable to load ticket categories. Please refresh the page.', 'error');
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
