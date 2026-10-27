/* Shared Fetch API helper for PHP endpoints. */
const API_BASE_URL = "/api";

function resolveApiUrl(endpoint, params = null) {
  let cleanEndpoint = endpoint.trim();
  // Strip leading slashes
  cleanEndpoint = cleanEndpoint.replace(/^\/+/, "");
  // If endpoint already starts with 'api/', avoid duplicating
  let url = cleanEndpoint.startsWith("api/") 
    ? "/" + cleanEndpoint 
    : `${API_BASE_URL}/${cleanEndpoint}`;

  if (params && typeof params === "object") {
    const searchParams = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== "") {
        searchParams.append(key, value);
      }
    }
    const qs = searchParams.toString();
    if (qs) {
      url += (url.includes("?") ? "&" : "?") + qs;
    }
  }
  return url;
}

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

  if (config.body && typeof config.body !== "string") {
    config.body = JSON.stringify(config.body);
  }

  let response;
  try {
    response = await fetch(resolveApiUrl(endpoint), config);
  } catch (err) {
    throw new Error("Unable to connect to the server. Check your connection and try again.");
  }

  let payload;
  try {
    payload = await response.json();
  } catch (err) {
    throw new Error("The server returned an unexpected response.");
  }

  if (!response.ok || payload.success !== true) {
    const error = new Error(payload.message || "The request could not be completed.");
    error.status = response.status;
    error.details = Array.isArray(payload.errors) ? payload.errors : [];
    error.data = payload.data || null;
    throw error;
  }

  return payload;
}

async function apiGet(path, params = null) {
  try {
    const url = resolveApiUrl(path, params);
    const response = await fetch(url, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    });

    let payload = null;
    try {
      payload = await response.json();
    } catch {
      return {
        ok: false,
        status: response.status,
        message: "Invalid response from server.",
        data: null,
        errors: []
      };
    }

    return {
      ok: Boolean(payload && payload.success === true),
      status: response.status,
      message: payload?.message || "",
      data: payload?.data ?? null,
      errors: payload?.errors || []
    };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      message: err.message || "Network error. Please try again.",
      data: null,
      errors: []
    };
  }
}

async function apiPost(path, body = {}) {
  try {
    const url = resolveApiUrl(path);
    const response = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json"
      },
      body: JSON.stringify(body)
    });

    let payload = null;
    try {
      payload = await response.json();
    } catch {
      return {
        ok: false,
        status: response.status,
        message: "Invalid response from server.",
        data: null,
        errors: []
      };
    }

    return {
      ok: Boolean(payload && payload.success === true),
      status: response.status,
      message: payload?.message || "",
      data: payload?.data ?? null,
      errors: payload?.errors || []
    };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      message: err.message || "Network error. Please try again.",
      data: null,
      errors: []
    };
  }
}

// Attach to window for global access across scripts
if (typeof window !== "undefined") {
  window.API_BASE_URL = API_BASE_URL;
  window.resolveApiUrl = resolveApiUrl;
  window.apiRequest = apiRequest;
  window.apiGet = apiGet;
  window.apiPost = apiPost;
}
