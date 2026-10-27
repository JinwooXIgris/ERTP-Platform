(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const money = (value) => new Intl.NumberFormat('en-NG', {
    style: 'currency', currency: 'NGN', maximumFractionDigits: 0
  }).format(Number(value) || 0);

  const params = new URLSearchParams(window.location.search);
  const orderId = params.get('order_id') || sessionStorage.getItem('currentOrderId');

  const linesContainer = $('order-lines-container');
  const subtotalEl = $('subtotal');
  const totalEl = $('total');
  const orderIdLabel = $('order-id-label');
  const messageEl = $('checkout-message');
  const form = $('checkout-form');
  const submitBtn = $('pay-submit-btn');

  let currentOrder = null;

  function showMessage(text, type = 'error') {
    if (!messageEl) return;
    messageEl.textContent = text;
    messageEl.className = `message is-${type}`;
    messageEl.style.display = 'block';
    messageEl.style.backgroundColor = type === 'error' ? '#fee2e2' : '#dcfce7';
    messageEl.style.color = type === 'error' ? '#991b1b' : '#166534';
    messageEl.style.border = type === 'error' ? '1px solid #f87171' : '1px solid #86efac';
    messageEl.hidden = false;
  }

  function clearMessage() {
    if (!messageEl) return;
    messageEl.hidden = true;
    messageEl.textContent = '';
    messageEl.style.display = 'none';
  }

  function generatePaymentReference() {
    return 'PAY-' + Array.from(crypto.getRandomValues(new Uint8Array(8)))
      .map(b => b.toString(16).padStart(2, '0'))
      .join('')
      .toUpperCase();
  }

  async function loadOrder() {
    if (!orderId) {
      showMessage('No order found. Please select your tickets first.');
      if (linesContainer) linesContainer.innerHTML = '<p style="padding: 15px;"><a class="button-link" href="../tickets/tickets.html">← Go to ticket selection</a></p>';
      if (submitBtn) submitBtn.disabled = true;
      return;
    }

    if (orderIdLabel) orderIdLabel.textContent = `Order #${orderId}`;

    try {
      const res = await (window.apiGet ? window.apiGet('/order.php', { id: orderId }) : fetch(`/api/order.php?id=${orderId}`).then(r => r.json()));

      if (res.status === 401 || (res.errors && res.errors.some(e => e.message?.includes('Authentication')))) {
        showMessage('Please sign in to view your order. Redirecting…', 'info');
        setTimeout(() => window.location.href = `../login/login.html?next=../checkout/checkout.html?order_id=${orderId}`, 1200);
        return;
      }

      if (!res.ok && res.success !== true) {
        showMessage(res.message || 'Unable to load order details.', 'error');
        if (submitBtn) submitBtn.disabled = true;
        return;
      }

      currentOrder = res.data?.order;

      if (currentOrder?.status === 'paid') {
        showMessage('This order is already paid! Redirecting to your digital tickets…', 'success');
        setTimeout(() => window.location.href = `../ticket-view/ticket-view.html?order_id=${orderId}&paid=1`, 1000);
        return;
      }

      const lines = currentOrder?.lines || [];
      if (linesContainer && lines.length > 0) {
        linesContainer.innerHTML = lines.map(line => `
          <div class="ticket-line" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.06);">
            <div>
              <strong style="font-size: 1.05rem;">${line.category_name} Pass × ${line.quantity}</strong>
              <p style="margin: 3px 0 0; color: #64748b; font-size: 0.9rem;">${money(line.unit_price)} each</p>
            </div>
            <span class="price" style="font-weight: 700; font-size: 1.1rem;">${money(line.line_total)}</span>
          </div>
        `).join('');
      }

      const totalVal = currentOrder?.total_amount || 0;
      if (subtotalEl) subtotalEl.textContent = money(totalVal);
      if (totalEl) totalEl.textContent = money(totalVal);
      if (submitBtn) {
        submitBtn.innerHTML = `Pay ${money(totalVal)} <span aria-hidden="true">→</span>`;
        submitBtn.disabled = false;
      }
    } catch (err) {
      showMessage('Error connecting to the order service.', 'error');
    }
  }

  async function loadEventInfo() {
    try {
      const res = await (window.apiGet ? window.apiGet('/event.php') : fetch('/api/event.php').then(r => r.json()));
      const ev = res?.data?.event;
      if (ev) {
        const dateEl = $('event-date-display');
        const venueEl = $('event-venue-display');
        if (venueEl && ev.venue) venueEl.textContent = ev.venue;
        if (dateEl && ev.event_date) {
          const d = new Date(ev.event_date.replace(' ', 'T'));
          dateEl.textContent = isNaN(d.getTime()) ? ev.event_date : d.toLocaleDateString('en-NG', {
            weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
          });
        }
      }
    } catch { }
  }

  // --- NEW PAYSTACK SUBMIT LOGIC ---
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearMessage();

    if (!orderId || !currentOrder) {
      showMessage('Order details not ready. Please refresh the page.', 'error');
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Initializing Payment…';
    }

    const reference = generatePaymentReference();
    const amountInKobo = Math.round(parseFloat(currentOrder.total_amount) * 100);

    try {
      let handler = PaystackPop.setup({
        key: 'pk_test_e4be8dbe3a41e75d0980565c6c4929b4debeec31', // Your test key
        email: currentOrder.email || "test@example.com",
        amount: amountInKobo,
        currency: 'NGN',
        ref: reference,
        callback: function (response) {
          // Wrapped the async code safely inside
          (async function () {
            showMessage('Payment successful on Paystack. Verifying transaction...', 'info');

            const payload = {
              order_id: Number(orderId),
              reference: response.reference,
              method: 'paystack'
            };

            try {
              const res = await (window.apiPost ? window.apiPost('/payment.php', payload) : fetch('/api/payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
              }).then(r => r.json()));

              if (res.ok || res.success === true) {
                showMessage('Payment verified! Redirecting to your digital tickets…', 'success');
                setTimeout(() => {
                  window.location.href = `../ticket-view/ticket-view.html?order_id=${orderId}&paid=1`;
                }, 1000);
              } else {
                showMessage(res.message || 'Payment backend verification failed.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = `Try Again (${money(currentOrder.total_amount)}) <span aria-hidden="true">→</span>`;
              }
            } catch (err) {
              showMessage('Network error during backend verification.', 'error');
              submitBtn.disabled = false;
            }
          })(); // Immediately invoke the inner async function
        },
        onClose: function () {
          showMessage('Payment window closed before completing the transaction.', 'error');
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = `Pay ${money(currentOrder.total_amount)} <span aria-hidden="true">→</span>`;
          }
        }
      });

      handler.openIframe();
    } catch (err) {
      showMessage(err.message || 'Payment popup failed to open.', 'error');
      if (submitBtn) submitBtn.disabled = false;
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    loadEventInfo();
    loadOrder();
  });
})();