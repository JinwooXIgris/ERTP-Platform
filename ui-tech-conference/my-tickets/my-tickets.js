(() => {
  const list = document.getElementById('ticket-list');
  const empty = document.getElementById('empty-state');
  const notice = document.getElementById('notice');
  // Demo-compatible keys. A real implementation should fetch tickets from the authenticated PHP API.
  const keys = ['uiTechTickets', 'uiTechTicket', 'uiTechOrder', 'ticketOrder', 'checkoutOrder', 'orderDraft'];
  const safeRead = key => { try { const raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : null; } catch { return null; } };
  const first = (...values) => values.find(v => v !== undefined && v !== null && String(v).trim() !== '');
  function normalize(data, key) {
    if (!data) return [];
    if (Array.isArray(data)) return data.flatMap((v, i) => normalize(v, key + i));
    if (typeof data !== 'object') return [];
    if (Array.isArray(data.tickets)) return data.tickets.map((t, i) => ({...t, _key:key + i}));
    const looksLikeTicket = ['ticketType','ticketName','category','ticketCategory','reference','referenceNumber','ticketReference','orderReference'].some(k => data[k] != null);
    return looksLikeTicket ? [{...data, _key:key}] : [];
  }
  let tickets = [];
  keys.forEach(key => { tickets.push(...normalize(safeRead(key), key)); });
  const seen = new Set();
  tickets = tickets.filter(t => { const id = first(t.ticketReference,t.referenceNumber,t.reference,t.orderReference,t.id,t._key); if (seen.has(String(id))) return false; seen.add(String(id)); return true; });
  function statusInfo(ticket) {
    const raw = String(first(ticket.paymentStatus,ticket.status,'Pending')).toLowerCase();
    if (raw.includes('success') || raw.includes('paid') || raw.includes('confirm')) return ['Confirmed','confirmed'];
    if (raw.includes('fail') || raw.includes('cancel')) return ['Failed','failed'];
    return ['Pending','pending'];
  }
  function makeCard(t, i) {
    const name = first(t.ticketType,t.ticketName,t.category,t.ticketCategory,t.name,'Conference Pass');
    const qty = Number(first(t.quantity,t.ticketQuantity,1)) || 1;
    const amount = Number(first(t.totalAmount,t.total,t.amount,Number(first(t.price,0))*qty)) || 0;
    const ref = first(t.ticketReference,t.referenceNumber,t.reference,t.orderReference,t.id,'DEMO-' + String(i+1).padStart(4,'0'));
    const attendee = first(t.fullName,t.attendeeName,t.nameOfAttendee,t.name,'Attendee details unavailable');
    const email = first(t.email,t.attendeeEmail,'—');
    const [status,statusClass] = statusInfo(t);
    const article = document.createElement('article'); article.className='ticket-card';
    article.innerHTML = `<div class="ticket-main"><div class="ticket-mark" aria-hidden="true">▤</div><div class="ticket-info"><div class="ticket-top"><h3></h3><span class="status ${statusClass}">${status}</span></div><p>University of Ibadan Technology Conference</p><div class="meta-grid"><div><span>ATTENDEE</span><strong class="attendee"></strong></div><div><span>QUANTITY</span><strong>${qty}</strong></div><div><span>ORDER REFERENCE</span><strong class="reference"></strong></div><div><span>AMOUNT</span><strong>${amount ? '₦' + amount.toLocaleString('en-NG') : 'Not available'}</strong></div></div></div></div><div class="ticket-actions"><a class="button open-ticket" href="../ticket-view/ticket-view.html">View ticket</a><button class="button button-outline print-ticket" type="button">Print list</button><button class="text-button copy-ref" type="button">Copy reference</button></div>`;
    article.querySelector('h3').textContent = name; article.querySelector('.attendee').textContent = attendee; article.querySelector('.reference').textContent = ref;
    article.querySelector('.open-ticket').addEventListener('click', () => {
      try { localStorage.setItem('uiTechSelectedTicket', JSON.stringify({...t, ticketReference:ref, ticketType:name, fullName:attendee, email, quantity:qty, totalAmount:amount, paymentStatus:status})); } catch {}
    });
    article.querySelector('.print-ticket').addEventListener('click', () => window.print());
    article.querySelector('.copy-ref').addEventListener('click', async e => { try { await navigator.clipboard.writeText(String(ref)); e.currentTarget.textContent='Copied!'; } catch { notice.hidden=false; notice.textContent='Your reference is: ' + ref; } });
    return article;
  }
  if (tickets.length) { tickets.forEach((t,i) => list.appendChild(makeCard(t,i))); empty.hidden=true; }
  else { empty.hidden=false; }
  if (tickets.length) { notice.hidden=false; notice.textContent='These tickets are read from browser demo data. Only the server can confirm a real ticket or payment.'; }
})();
