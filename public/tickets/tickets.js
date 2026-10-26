(() => {
  'use strict';

  // Demo prices only. The PHP API must supply and validate real prices and availability.
  const TICKET_PRICES = { student: 2500, general: 5000, vip: 15000 };
  const MAX_PER_CATEGORY = 10;
  const STORAGE_KEY = 'uiTechTicketSelection';
  const $ = (id) => document.getElementById(id);

  function money(amount) {
    return new Intl.NumberFormat('en-NG', {
      style: 'currency', currency: 'NGN', maximumFractionDigits: 0
    }).format(amount);
  }

  function quantity(name) {
    return Number($(`${name}-qty`).value) || 0;
  }

  function readSelection() {
    return Object.keys(TICKET_PRICES)
      .map((name) => ({ name, quantity: quantity(name), unitPrice: TICKET_PRICES[name] }))
      .filter((item) => item.quantity > 0);
  }

  function render() {
    let total = 0;
    let ticketCount = 0;
    Object.entries(TICKET_PRICES).forEach(([name, price]) => {
      const qty = quantity(name);
      const lineTotal = qty * price;
      total += lineTotal;
      ticketCount += qty;
      $(`${name}-total`).textContent = money(lineTotal);
      document.querySelector(`[data-ticket="${name}"]`).classList.toggle('selected', qty > 0);
    });
    $('grand-total').textContent = money(total);
    $('selection-count').textContent = ticketCount === 0
      ? 'No tickets selected yet'
      : `${ticketCount} ticket${ticketCount === 1 ? '' : 's'} selected across ${readSelection().length} categor${readSelection().length === 1 ? 'y' : 'ies'}`;
    $('continue-button').disabled = ticketCount === 0;
    $('current-year').textContent = new Date().getFullYear();
  }

  function showMessage(message, type = 'error') {
    const target = $('status-message');
    target.textContent = message;
    target.className = `status-message is-${type}`;
    target.hidden = false;
  }

  function setQuantity(input, nextValue) {
    const min = Number(input.min) || 0;
    const max = Number(input.max) || MAX_PER_CATEGORY;
    const safeValue = Math.max(min, Math.min(max, Math.floor(Number(nextValue) || 0)));
    input.value = String(safeValue);
    render();
    $('status-message').hidden = true;
  }

  function saveAndContinue() {
    const selected = readSelection();
    if (!selected.length) {
      showMessage('Please select at least one ticket before continuing.');
      return;
    }
    const count = selected.reduce((sum, item) => sum + item.quantity, 0);
    if (count > 10) {
      showMessage('For this demo, select no more than 10 tickets in total.');
      return;
    }
    const selection = {
      tickets: selected,
      estimatedSubtotal: selected.reduce((sum, item) => sum + item.quantity * item.unitPrice, 0),
      currency: 'NGN',
      status: 'selection-draft',
      updatedAt: new Date().toISOString()
    };
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(selection));
      window.location.href = '../checkout/checkout.html';
    } catch (error) {
      showMessage('Your browser could not save this selection. Please enable browser storage and try again.');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Restore the last selection when a user returns to this page.
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
      const items = Array.isArray(saved) ? saved : Array.isArray(saved?.tickets) ? saved.tickets : [];
      items.forEach((item) => {
        const name = String(item.name || item.type || item.category || '').toLowerCase();
        if (TICKET_PRICES[name]) setQuantity($(`${name}-qty`), item.quantity ?? item.qty ?? 0);
      });
    } catch (error) {
      // Ignore invalid saved data and let the attendee start a new selection.
    }

    document.querySelectorAll('.stepper button').forEach((button) => {
      button.addEventListener('click', () => {
        const input = $(button.dataset.target);
        setQuantity(input, quantity(button.dataset.target.replace('-qty', '')) + Number(button.dataset.step));
      });
    });
    document.querySelectorAll('.stepper input').forEach((input) => {
      input.addEventListener('input', () => setQuantity(input, input.value));
      input.addEventListener('change', () => setQuantity(input, input.value));
    });
    $('continue-button').addEventListener('click', saveAndContinue);

    document.querySelectorAll('.ui-logo').forEach((logo) => {
      const wrapper = logo.closest('.logo-wrap');
      logo.addEventListener('load', () => wrapper.classList.toggle('has-logo', logo.naturalWidth > 0));
      logo.addEventListener('error', () => wrapper.classList.remove('has-logo'));
      if (logo.complete && logo.naturalWidth > 0) wrapper.classList.add('has-logo');
    });
    render();
  });
})();
