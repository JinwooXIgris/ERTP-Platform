/* Shared Fetch API helper for PHP endpoints. */
const API_BASE_URL = "TP-B/api/";
async function apiRequest(endpoint, options = {}) {
  const config = {
    method: options.method || "GET",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(options.headers || {})
    },
    ...options
  };
  if (config.body && typeof config.body !== "string") config.body = JSON.stringify(config.body);
  let response;
  try { response = await fetch(`${API_BASE_URL}/${endpoint.replace(/^\//, "")}`, config); }
  catch { throw new Error("Unable to connect to the server. Check your connection and try again."); }
  let payload;
  try { payload = await response.json(); }
  catch { throw new Error("The server returned an unexpected response."); }
  if (!response.ok || payload.success !== true) {
    const error = new Error(payload.message || "The request could not be completed.");
    error.status = response.status;
    error.details = Array.isArray(payload.errors) ? payload.errors : [];
    throw error;
  }
  return payload;
}
