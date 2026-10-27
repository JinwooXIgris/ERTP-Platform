// Payment result UI
const params = new URLSearchParams(window.location.search);
const status = (params.get("status") || "pending").toLowerCase();
const reference = params.get("reference") || params.get("orderReference") || "Not available";
const orderId = params.get("order_id") || sessionStorage.getItem("currentOrderId") || "";

const icon = document.querySelector("#status-icon");
const title = document.querySelector("#status-title");
const description = document.querySelector("#status-description");
const paymentStatus = document.querySelector("#payment-status");
const orderReference = document.querySelector("#order-reference");
const actionArea = document.querySelector("#action-area");

if (orderReference) orderReference.textContent = reference;

const ticketViewUrl = orderId ? `../ticket-view/ticket-view.html?order_id=${orderId}&paid=1` : '../my-tickets/my-tickets.html';
const checkoutRetryUrl = orderId ? `../checkout/checkout.html?order_id=${orderId}` : '../checkout/checkout.html';

if (status === "success") {
  if (icon) {
    icon.textContent = "✓";
    icon.className = "status-icon success";
  }
  if (title) title.textContent = "Payment successful!";
  if (description) description.textContent = "Your payment has been successfully confirmed and your digital conference tickets are now issued.";
  if (paymentStatus) paymentStatus.textContent = "Confirmed & Paid";
  if (actionArea) {
    actionArea.innerHTML = `
      <a class="button primary" href="${ticketViewUrl}">View Digital Tickets</a>
      <a class="button secondary" href="../my-tickets/my-tickets.html">All My Tickets</a>
    `;
  }
} else if (status === "failed" || status === "failure") {
  if (icon) {
    icon.textContent = "!";
    icon.className = "status-icon failure";
  }
  if (title) title.textContent = "Payment not completed";
  if (description) description.textContent = "The transaction could not be processed. Your order remains pending so you can retry payment without losing your selection.";
  if (paymentStatus) paymentStatus.textContent = "Payment Failed";
  if (actionArea) {
    actionArea.innerHTML = `
      <a class="button primary" href="${checkoutRetryUrl}">Try Checkout Again</a>
      <a class="button secondary" href="../tickets/tickets.html">Back to Tickets</a>
    `;
  }
} else {
  if (icon) {
    icon.textContent = "…";
    icon.className = "status-icon pending";
  }
  if (title) title.textContent = "Payment awaiting confirmation";
  if (description) description.textContent = "Checking your order status with the conference payment system. Please check your tickets or retry if necessary.";
  if (paymentStatus) paymentStatus.textContent = "Pending confirmation";
  if (actionArea) {
    actionArea.innerHTML = `
      <a class="button primary" href="../my-tickets/my-tickets.html">Check My Tickets</a>
      <a class="button secondary" href="${checkoutRetryUrl}">Return to Checkout</a>
    `;
  }
}
