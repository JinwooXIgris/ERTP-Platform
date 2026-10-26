// Admin dashboard frontend preview.
// Replace sample/empty states with authenticated backend API responses.
const refreshButton = document.querySelector("#refresh-button");
const registrationsCount = document.querySelector("#registrations-count");
const ticketsCount = document.querySelector("#tickets-count");
const paymentsTotal = document.querySelector("#payments-total");
const checkedInCount = document.querySelector("#checked-in-count");
const attendeesBody = document.querySelector("#attendees-body");

function renderPreview() {
  // Keep metrics unreported until real data is returned by the backend.
  registrationsCount.textContent = "—";
  ticketsCount.textContent = "—";
  paymentsTotal.textContent = "—";
  checkedInCount.textContent = "—";
  attendeesBody.innerHTML = '<tr><td colspan="4" class="empty">No live attendee data connected yet.</td></tr>';
}

refreshButton.addEventListener("click", renderPreview);
renderPreview();

// Backend integration notes:
// 1. Protect this page with admin authentication and authorization.
// 2. Fetch metrics and attendees from server-side API endpoints.
// 3. Never trust frontend-only role checks or display payment data from local storage.
// 4. Escape/sanitize any attendee data before rendering it in the table.
