// Payment result UI template.
// IMPORTANT: Query-string status is used only for local visual testing.
// Production status must be confirmed by your backend/payment provider.
const params = new URLSearchParams(window.location.search);
const demoStatus = (params.get("status") || "pending").toLowerCase();
const reference = params.get("reference") || params.get("orderReference") || "Not available";

const icon = document.querySelector("#status-icon");
const title = document.querySelector("#status-title");
const description = document.querySelector("#status-description");
const paymentStatus = document.querySelector("#payment-status");
const orderReference = document.querySelector("#order-reference");
const actionArea = document.querySelector("#action-area");

orderReference.textContent = reference;

if (demoStatus === "success") {
  icon.textContent = "✓";
  icon.className = "status-icon success";
  title.textContent = "Payment successful";
  description.textContent = "Your payment result is marked successful in this preview. The backend must confirm the transaction before issuing your digital ticket.";
  paymentStatus.textContent = "Success (preview only)";
  actionArea.innerHTML = '<a class="button primary" href="../my-tickets/my-tickets.html">Go to My Tickets</a><a class="button secondary" href="../ticket-view/ticket-view.html">View Digital Ticket</a>';
} else if (demoStatus === "failed" || demoStatus === "failure") {
  icon.textContent = "!";
  icon.className = "status-icon failure";
  title.textContent = "Payment not completed";
  description.textContent = "The payment was not marked successful. You can return to checkout and try again after checking the payment status.";
  paymentStatus.textContent = "Failed (preview only)";
  actionArea.innerHTML = '<a class="button primary" href="../checkout/checkout.html">Try Checkout Again</a><a class="button secondary" href="../tickets/tickets.html">Back to Tickets</a>';
} else {
  icon.textContent = "…";
  icon.className = "status-icon pending";
  title.textContent = "Payment awaiting confirmation";
  description.textContent = "We have not confirmed the payment yet. Please check your order status before attempting another payment.";
  paymentStatus.textContent = "Pending confirmation";
  actionArea.innerHTML = '<a class="button primary" href="../my-tickets/my-tickets.html">Check My Tickets</a><a class="button secondary" href="../tickets/tickets.html">Back to Tickets</a>';
}
