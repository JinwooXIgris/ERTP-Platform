// Frontend-only ticket verification form.
// Replace the demo response with a request to the backend verification API.
const verifyForm = document.querySelector("#verify-form");
const referenceInput = document.querySelector("#ticket-reference");
const resultBox = document.querySelector("#verification-result");

verifyForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const reference = referenceInput.value.trim();

  if (!reference) {
    showResult("Enter a ticket reference before verifying.", "error");
    referenceInput.focus();
    return;
  }

  // This intentionally does NOT claim that the ticket is valid.
  // Backend team: connect this form to the official ticket-verification endpoint.
  showResult(
    `Reference "${reference}" captured. Live verification is not connected yet. Connect this form to the backend API before using it at the event.`,
    "info"
  );
});

function showResult(message, type) {
  resultBox.textContent = message;
  resultBox.className = `result ${type}`;
  resultBox.hidden = false;
}
