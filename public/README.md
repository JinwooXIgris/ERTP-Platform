# UI Technology Conference — Frontend

## Run locally
Open `index.html` with a local static server (recommended: VS Code Live Server). Do not open via a `file://` URL if browser storage or API calls are being tested.

## Frontend pages
- `index.html`: public event landing page
- `register/`, `login/`: account forms
- `tickets/`: ticket category selection
- `checkout/`: order review frontend preview
- `payment-result/`: payment state UI
- `my-tickets/`, `ticket-view/`: attendee ticket pages
- `verify/`: staff ticket lookup UI
- `admin/`: administration dashboard UI

## Shared assets
Images are centralized in `assets/images/`; shared CSS is in `assets/css/` and `shared/`. Avoid duplicating images into individual feature folders.

## Backend handoff — required before production
1. Implement PHP endpoints and MySQL schema in `api/` and `database/`.
2. Set API base URL in `shared/api.js` to the deployment-appropriate relative API path.
3. Authenticate attendees and administrators server-side; enforce roles for admin and verification endpoints.
   The login form sends `POST /api/login.php` with `email`, `password`, and `remember_me`; a successful JSON response should include `success: true` and the authenticated user role (`user.role` or `data.user.role`) so the frontend can route admins to the dashboard.
4. Calculate prices and availability on the server; never trust browser/localStorage/URL prices.
5. Initialize and verify payments server-side with the provider; only issue tickets after verified payment.
6. Generate unique ticket references and QR payloads server-side. Validate tickets at check-in server-side.
7. Replace frontend preview/localStorage data with API responses.
