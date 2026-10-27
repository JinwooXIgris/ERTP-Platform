// Admin Dashboard logic wired to live PHP API endpoints
(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const money = (val) => new Intl.NumberFormat('en-NG', {
    style: 'currency', currency: 'NGN', maximumFractionDigits: 2
  }).format(Number(val) || 0);

  let attendeesPage = 1;
  let attendeesTotal = 0;
  let ordersPage = 1;
  let ordersTotal = 0;
  let usersPage = 1;
  let usersTotal = 0;
  let currentEvent = null;

  function showAlert(msg, type = 'success') {
    const alertBox = $('admin-alert');
    if (!alertBox) return;
    alertBox.textContent = msg;
    alertBox.style.display = 'block';
    alertBox.style.backgroundColor = type === 'success' ? '#dcfce7' : '#fee2e2';
    alertBox.style.color = type === 'success' ? '#166534' : '#991b1b';
    alertBox.style.border = type === 'success' ? '1px solid #86efac' : '1px solid #f87171';
    alertBox.hidden = false;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => {
      alertBox.hidden = true;
      alertBox.style.display = 'none';
    }, 5000);
  }

  // 1. Auth Guard
  async function checkAdminAuth() {
    try {
      const res = await (window.apiGet ? window.apiGet('/me.php') : fetch('/api/me.php').then(r => r.json()));
      const user = res?.data?.user;
      if (!user || user.role !== 'admin') {
        window.location.href = '../login/login.html?next=../admin/admin-dashboard.html';
        return false;
      }
      const adminInfo = $('admin-user-info');
      if (adminInfo) {
        adminInfo.textContent = `${user.full_name} (${user.email})`;
      }
      return true;
    } catch {
      window.location.href = '../login/login.html?next=../admin/admin-dashboard.html';
      return false;
    }
  }

  // 2. Load Stats
  async function loadStats() {
    try {
      const res = await (window.apiGet ? window.apiGet('/admin/stats.php') : fetch('/api/admin/stats.php').then(r => r.json()));
      if (!res.ok && res.success !== true) return;

      const totals = res.data?.totals || {};
      const perCategory = res.data?.per_category || [];

      if ($('registrations-count')) $('registrations-count').textContent = totals.orders_paid ?? 0;
      if ($('tickets-count')) $('tickets-count').textContent = totals.tickets_sold ?? 0;
      if ($('payments-total')) $('payments-total').textContent = money(totals.revenue ?? 0);
      if ($('checked-in-count')) $('checked-in-count').textContent = totals.tickets_used ?? 0;

      // Render categories
      const catList = $('categories-list');
      if (catList && perCategory.length > 0) {
        catList.innerHTML = perCategory.map(cat => `
          <div class="ticket-row" style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #edf0f4;">
            <div>
              <strong>${cat.name} Pass</strong>
              <small style="color:#64748b;">Sold: ${cat.sold} / Quota: ${cat.quota} | Revenue: ${money(cat.revenue)}</small>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="price">${money(cat.price)}</span>
              <input type="number" step="100" min="0" value="${cat.price}" id="price-input-${cat.category_id}" style="width:85px; padding:4px 6px; font-size:12px; border:1px solid #cbd5e1; border-radius:4px;" />
              <button type="button" class="secondary btn-update-price" data-cat-id="${cat.category_id}" style="padding:4px 10px; font-size:11px;">Update</button>
            </div>
          </div>
        `).join('');

        catList.querySelectorAll('.btn-update-price').forEach(btn => {
          btn.addEventListener('click', async () => {
            const catId = Number(btn.dataset.catId);
            const inputVal = document.getElementById(`price-input-${catId}`)?.value;
            const newPrice = Number(inputVal);
            if (isNaN(newPrice) || newPrice < 0) {
              showAlert('Enter a valid price.', 'error');
              return;
            }
            btn.disabled = true;
            btn.textContent = 'Saving…';
            try {
              const pRes = await (window.apiPost ? window.apiPost('/admin/update-price.php', {
                category_id: catId,
                price: newPrice.toFixed(2)
              }) : fetch('/api/admin/update-price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ category_id: catId, price: newPrice.toFixed(2) })
              }).then(r => r.json()));

              if (pRes.ok || pRes.success === true) {
                showAlert(`Price for category #${catId} updated to ${money(newPrice)}!`, 'success');
                loadStats();
              } else {
                showAlert(pRes.message || 'Could not update price.', 'error');
              }
            } catch (err) {
              showAlert(err.message || 'Price update failed.', 'error');
            } finally {
              btn.disabled = false;
              btn.textContent = 'Update';
            }
          });
        });
      }
    } catch (err) {
      console.warn('Could not load stats:', err);
    }
  }

  // 3. Load Event Details
  async function loadEvent() {
    try {
      const res = await (window.apiGet ? window.apiGet('/event.php') : fetch('/api/event.php').then(r => r.json()));
      if (!res.ok && res.success !== true) return;
      currentEvent = res.data?.event;
      if (!currentEvent) return;

      if ($('event-title-display')) $('event-title-display').textContent = currentEvent.title;
      if ($('event-desc-display')) $('event-desc-display').textContent = currentEvent.description;
      if ($('event-venue-display')) $('event-venue-display').textContent = currentEvent.venue;
      if ($('event-date-display')) $('event-date-display').textContent = currentEvent.event_date;

      // Populate edit form
      if ($('edit-event-title')) $('edit-event-title').value = currentEvent.title;
      if ($('edit-event-date')) $('edit-event-date').value = currentEvent.event_date;
      if ($('edit-event-venue')) $('edit-event-venue').value = currentEvent.venue;
      if ($('edit-event-desc')) $('edit-event-desc').value = currentEvent.description;
      if ($('edit-event-schedule')) $('edit-event-schedule').value = currentEvent.schedule;
      if ($('edit-event-speakers')) $('edit-event-speakers').value = currentEvent.speakers;
    } catch (err) {
      console.warn('Could not load event:', err);
    }
  }

  // 4. Load Attendees
  async function loadAttendees(page = 1) {
    attendeesPage = page;
    const searchVal = $('attendees-search')?.value.trim() || '';
    const tbody = $('attendees-body');
    if (!tbody) return;

    try {
      const params = { page: attendeesPage };
      if (searchVal) params.q = searchVal;

      const res = await (window.apiGet ? window.apiGet('/admin/attendees.php', params) : fetch(`/api/admin/attendees.php?page=${page}&q=${encodeURIComponent(searchVal)}`).then(r => r.json()));
      const attendees = res.data?.attendees || [];
      attendeesTotal = res.data?.total || 0;
      const perPage = res.data?.per_page || 20;

      const totalPages = Math.max(1, Math.ceil(attendeesTotal / perPage));
      if ($('attendees-page-info')) {
        $('attendees-page-info').textContent = `Showing page ${attendeesPage} of ${totalPages} (${attendeesTotal} total tickets)`;
      }
      if ($('attendees-prev-btn')) $('attendees-prev-btn').disabled = attendeesPage <= 1;
      if ($('attendees-next-btn')) $('attendees-next-btn').disabled = attendeesPage >= totalPages;

      if (attendees.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty">No attendees found.</td></tr>';
        return;
      }

      tbody.innerHTML = attendees.map(a => {
        const isUsed = a.status === 'used';
        return `
          <tr>
            <td><code style="font-family:monospace; font-weight:700;">${a.ticket_number}</code></td>
            <td><strong>${a.holder_name}</strong></td>
            <td>${a.email}</td>
            <td>${a.phone}</td>
            <td>${a.category}</td>
            <td>
              <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:700; background:${isUsed ? 'rgba(217, 119, 6, 0.15)' : 'rgba(34, 197, 94, 0.15)'}; color:${isUsed ? '#b45309' : '#15803d'};">
                ${isUsed ? 'Used at gate' : 'Issued'}
              </span>
            </td>
            <td>${a.issued_at || '—'}</td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      tbody.innerHTML = '<tr><td colspan="7" class="empty">Failed to load attendees.</td></tr>';
    }
  }

  // 5. Load Orders
  async function loadOrders(page = 1) {
    ordersPage = page;
    const searchVal = $('orders-search')?.value.trim() || '';
    const tbody = $('orders-body');
    if (!tbody) return;

    try {
      const params = { page: ordersPage };
      if (searchVal) params.q = searchVal;

      const res = await (window.apiGet ? window.apiGet('/admin/orders.php', params) : fetch(`/api/admin/orders.php?page=${page}&q=${encodeURIComponent(searchVal)}`).then(r => r.json()));
      const orders = res.data?.orders || [];
      ordersTotal = res.data?.total || 0;
      const perPage = res.data?.per_page || 20;

      const totalPages = Math.max(1, Math.ceil(ordersTotal / perPage));
      if ($('orders-page-info')) {
        $('orders-page-info').textContent = `Showing page ${ordersPage} of ${totalPages} (${ordersTotal} total orders)`;
      }
      if ($('orders-prev-btn')) $('orders-prev-btn').disabled = ordersPage <= 1;
      if ($('orders-next-btn')) $('orders-next-btn').disabled = ordersPage >= totalPages;

      if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty">No orders found.</td></tr>';
        return;
      }

      tbody.innerHTML = orders.map(o => {
        const isPaid = o.status === 'paid';
        const paymentsList = (o.payments || []).map(p => `
          <div style="font-size:11px; font-family:monospace;">
            ${p.reference} <span style="color:${p.status === 'success' ? '#16a34a' : '#dc2626'};">(${p.status})</span>
          </div>
        `).join('');

        return `
          <tr>
            <td><strong>#${o.id}</strong></td>
            <td>${o.user_name}</td>
            <td>${o.email}</td>
            <td><strong>${money(o.total_amount)}</strong></td>
            <td>
              <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:700; background:${isPaid ? 'rgba(34, 197, 94, 0.15)' : 'rgba(234, 179, 8, 0.15)'}; color:${isPaid ? '#15803d' : '#854d0e'};">
                ${o.status}
              </span>
            </td>
            <td>${paymentsList || '<span style="color:#94a3b8;">No payments yet</span>'}</td>
            <td>${o.created_at || '—'}</td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      tbody.innerHTML = '<tr><td colspan="7" class="empty">Failed to load orders.</td></tr>';
    }
  }

  // 6. Load Users
  async function loadUsers(page = 1) {
    usersPage = page;
    const searchVal = $('users-search')?.value.trim() || '';
    const tbody = $('users-body');
    if (!tbody) return;

    try {
      const params = { page: usersPage };
      if (searchVal) params.q = searchVal;

      const res = await (window.apiGet ? window.apiGet('/admin/users.php', params) : fetch(`/api/admin/users.php?page=${page}&q=${encodeURIComponent(searchVal)}`).then(r => r.json()));
      const users = res.data?.users || [];
      usersTotal = res.data?.total || 0;
      const perPage = res.data?.per_page || 20;

      const totalPages = Math.max(1, Math.ceil(usersTotal / perPage));
      if ($('users-page-info')) {
        $('users-page-info').textContent = `Showing page ${usersPage} of ${totalPages} (${usersTotal} total users)`;
      }
      if ($('users-prev-btn')) $('users-prev-btn').disabled = usersPage <= 1;
      if ($('users-next-btn')) $('users-next-btn').disabled = usersPage >= totalPages;

      if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty">No users found.</td></tr>';
        return;
      }

      tbody.innerHTML = users.map(u => `
        <tr>
          <td><strong>#${u.id}</strong></td>
          <td><strong>${u.full_name}</strong></td>
          <td>${u.email}</td>
          <td>${u.phone}</td>
          <td><span style="text-transform:capitalize;">${u.attendee_type}</span></td>
          <td>
            <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:700; background:${u.role === 'admin' ? 'rgba(99, 102, 241, 0.15)' : u.role === 'verifier' ? 'rgba(234, 179, 8, 0.15)' : 'rgba(100, 116, 139, 0.15)'}; color:${u.role === 'admin' ? '#4338ca' : u.role === 'verifier' ? '#854d0e' : '#334155'};">
              ${u.role}
            </span>
          </td>
          <td>${u.created_at || '—'}</td>
        </tr>
      `).join('');
    } catch (err) {
      tbody.innerHTML = '<tr><td colspan="7" class="empty">Failed to load users.</td></tr>';
    }
  }

  // Refresh all
  async function refreshAll() {
    await Promise.all([loadStats(), loadEvent(), loadAttendees(1), loadOrders(1), loadUsers(1)]);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const isAuthed = await checkAdminAuth();
    if (!isAuthed) return;

    $('refresh-button')?.addEventListener('click', () => {
      showAlert('Refreshing dashboard data…', 'success');
      refreshAll();
    });

    // Event edit toggle
    const toggleBtn = $('toggle-event-edit');
    const editForm = $('event-edit-form');
    toggleBtn?.addEventListener('click', () => {
      if (editForm) {
        const isHidden = editForm.style.display === 'none';
        editForm.style.display = isHidden ? 'block' : 'none';
        toggleBtn.textContent = isHidden ? 'Close Editor' : 'Edit Event';
      }
    });

    $('cancel-event-btn')?.addEventListener('click', () => {
      if (editForm) {
        editForm.style.display = 'none';
        if (toggleBtn) toggleBtn.textContent = 'Edit Event';
      }
    });

    // Save Event Form
    editForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const saveBtn = $('save-event-btn');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
      }

      const body = {
        title: $('edit-event-title')?.value.trim(),
        event_date: $('edit-event-date')?.value.trim(),
        venue: $('edit-event-venue')?.value.trim(),
        description: $('edit-event-desc')?.value.trim(),
        schedule: $('edit-event-schedule')?.value.trim(),
        speakers: $('edit-event-speakers')?.value.trim()
      };

      try {
        const res = await (window.apiPost ? window.apiPost('/admin/update-event.php', body) : fetch('/api/admin/update-event.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        }).then(r => r.json()));

        if (res.ok || res.success === true) {
          showAlert('Event details successfully updated!', 'success');
          if (editForm) editForm.style.display = 'none';
          if (toggleBtn) toggleBtn.textContent = 'Edit Event';
          loadEvent();
        } else {
          showAlert(res.message || 'Failed to update event.', 'error');
        }
      } catch (err) {
        showAlert(err.message || 'Error updating event.', 'error');
      } finally {
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save Event Details';
        }
      }
    });

    // Search Attendees
    $('search-attendees-btn')?.addEventListener('click', () => loadAttendees(1));
    $('attendees-search')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') loadAttendees(1); });
    $('attendees-prev-btn')?.addEventListener('click', () => { if (attendeesPage > 1) loadAttendees(attendeesPage - 1); });
    $('attendees-next-btn')?.addEventListener('click', () => loadAttendees(attendeesPage + 1));

    // Search Orders
    $('search-orders-btn')?.addEventListener('click', () => loadOrders(1));
    $('orders-search')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') loadOrders(1); });
    $('orders-prev-btn')?.addEventListener('click', () => { if (ordersPage > 1) loadOrders(ordersPage - 1); });
    $('orders-next-btn')?.addEventListener('click', () => loadOrders(ordersPage + 1));

    // Search Users
    $('search-users-btn')?.addEventListener('click', () => loadUsers(1));
    $('users-search')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') loadUsers(1); });
    $('users-prev-btn')?.addEventListener('click', () => { if (usersPage > 1) loadUsers(usersPage - 1); });
    $('users-next-btn')?.addEventListener('click', () => loadUsers(usersPage + 1));

    // Admin Logout
    $('admin-logout-btn')?.addEventListener('click', async (e) => {
      e.preventDefault();
      if (window.apiPost) await window.apiPost('/logout.php');
      else await fetch('/api/logout.php', { method: 'POST' });
      window.location.href = '../login/login.html';
    });

    refreshAll();
  });
})();
