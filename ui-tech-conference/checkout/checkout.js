(() => {
  const $ = (selector) => document.querySelector(selector);
  const money = (value) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(value);
  const selectionKey = 'uiTechTicketSelection';
  const orderKey = 'uiTechOrderDraft';
  const params = new URLSearchParams(location.search);
  let selection = null;
  try { selection = JSON.parse(localStorage.getItem(selectionKey) || 'null'); } catch { selection = null; }
  const chosen = Array.isArray(selection?.tickets) ? selection.tickets : [];
  const summary = $('#order-items');
  const nameEl = $('#ticket-name');
  const descEl = $('#ticket-description');
  const priceEl = $('#ticket-price');
  const qtyEl = $('#quantity-value');
  const decrease = $('#decrease');
  const increase = $('#increase');
  const subtotalEl = $('#subtotal');
  const feeEl = $('#service-fee');
  const totalEl = $('#total');
  const form = $('#checkout-form');
  const message = $('#checkout-message');
  let quantity = 1;
  let currentPrice = null;
  let chosenName = params.get('ticketType') || 'Conference tickets';

  if (chosen.length) {
    chosenName = chosen.map(t => `${t.name[0].toUpperCase()}${t.name.slice(1)} × ${t.quantity}`).join(', ');
    const subtotal = Number(selection.estimatedSubtotal) || chosen.reduce((sum,t)=>sum+(Number(t.unitPrice)||0)*(Number(t.quantity)||0),0);
    currentPrice = subtotal;
    quantity = 1;
    if (nameEl) nameEl.textContent = chosenName;
    if (descEl) descEl.textContent = 'Your selected ticket categories and quantities.';
    if (priceEl) priceEl.textContent = money(subtotal);
    if (summary) { summary.hidden = false; summary.innerHTML = ''; chosen.forEach(t => { const row=document.createElement('p'); row.textContent=`${t.name[0].toUpperCase()}${t.name.slice(1)} pass — ${t.quantity} × ${money(t.unitPrice)}`; summary.appendChild(row); }); }
  } else {
    const preview = Number(params.get('price'));
    if (params.has('price') && Number.isFinite(preview) && preview >= 0) currentPrice = preview;
    if (params.get('ticketType') && nameEl) nameEl.textContent = params.get('ticketType');
    if (priceEl) priceEl.textContent = currentPrice === null ? 'Price confirmed at checkout' : money(currentPrice);
    if (descEl) descEl.textContent = 'Review your order. Final pricing must be confirmed by the server.';
  }
  function update() {
    if (qtyEl) qtyEl.textContent = String(quantity);
    const amount = currentPrice === null ? null : currentPrice * quantity;
    if (subtotalEl) subtotalEl.textContent = amount === null ? '₦—' : money(amount);
    if (feeEl) feeEl.textContent = amount === null ? '₦—' : money(0);
    if (totalEl) totalEl.textContent = amount === null ? '₦—' : money(amount);
  }
  decrease?.addEventListener('click', () => { if (quantity > 1) quantity--; update(); });
  increase?.addEventListener('click', () => { if (quantity < 10) quantity++; update(); });
  form?.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const reference = 'UITC-DEMO-' + Date.now().toString(36).toUpperCase();
    const order = { tickets: chosen, ticketType: chosenName, quantity: chosen.length ? chosen.reduce((n,t)=>n+Number(t.quantity||0),0) : quantity, subtotal: currentPrice === null ? null : currentPrice*quantity, currency: 'NGN', reference, status: 'awaiting-server-payment-confirmation', createdAt: new Date().toISOString() };
    try { localStorage.setItem(orderKey, JSON.stringify(order)); } catch {}
    location.href = '../payment-result/payment-result.html?status=pending&reference=' + encodeURIComponent(reference);
  });
  update();
})();