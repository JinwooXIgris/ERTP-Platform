# Backend & Database: Division of Work

**Event Registration & Ticket Payment Platform**  
Web Development Group 5 · Backend & Database Workstream (2 people)  
SRS Addendum v1.1

---

## Overview

The Backend team has **two people** (BE-A and BE-B). To avoid conflicts and enable parallel development, we split ownership of **files and endpoints**.

**Key rule:** Each file is owned by ONE person. No shared ownership.  
**When you need code from the other person:** Wait for it to be pushed to `develop`, pull it, and use it.

---

## The Split

### **BE-A (Backend Infrastructure & Authentication)**

**Owns these files (creates and maintains):**

| File | Purpose | Status |
|------|---------|--------|
| `includes/db.php` | PDO connection, timezone, error handling | Creates by Day 2 |
| `includes/auth.php` | Sessions, login/logout, role checks | Creates by Day 2 |
| `includes/config.example.php` | Config template (already created) | Reference |
| `database/schema.sql` | Table definitions (already created) | Reference |
| `database/seed.sql` | Test data (already created) | Reference |

**Owns these endpoints (writes the code):**

| Endpoint | HTTP | Auth | Purpose |
|----------|------|------|---------|
| `/api/register.php` | POST | None | New user registration |
| `/api/login.php` | POST | None | User login (starts session) |
| `/api/logout.php` | POST | User | End session |
| `/api/me.php` | GET | Public | Current user info (or null) |

**Hands off to BE-B:**
- `response.php` helper signature (BE-B implements, BE-A tests)
- `validate.php` helper signature (BE-B implements, BE-A uses)

**Waits on:**
- Nothing (BE-A is the foundation; unblocks everyone)

**Timeline:**
- **Day 2 (Freeze):** Push `db.php`, `auth.php`, database files locked
- **Days 3–4:** Build registration and login endpoints
- **Day 5:** Done; endpoints live for FE-A to consume

---

### **BE-B (Response Helpers, Validation, Event & Admin Endpoints)**

**Owns these files (creates and maintains):**

| File | Purpose | Status |
|------|---------|--------|
| `includes/response.php` | JSON envelope, `ok()`, `fail()` | Creates by Day 2 |
| `includes/validate.php` | Input validation helpers | Creates by Day 2 |

**Owns these endpoints (writes the code):**

| Endpoint | HTTP | Auth | Purpose |
|----------|------|------|---------|
| `/api/event.php` | GET | None | Conference details |
| `/api/categories.php` | GET | None | Ticket categories + prices |
| `/api/admin/stats.php` | GET | Admin | Sales totals, tickets per category |
| `/api/admin/attendees.php` | GET | Admin | Attendee list (searchable, paginated) |
| `/api/admin/update-price.php` | POST | Admin | Change category price |
| `/api/admin/update-event.php` | POST | Admin | Edit event details |

**Hands off to other workstreams:**
- Helper functions (`response.php`, `validate.php`) — ALL other endpoints depend on these
- Event endpoint — FE-A uses this for the landing page

**Waits on:**
- `db.php` from BE-A (needs database connection)
- `auth.php` from BE-A (needs session/role checks)

**Timeline:**
- **Day 2 (Freeze):** Push `response.php` and `validate.php` with final signatures
- **Days 3–4:** Build event and category endpoints (public, no auth)
- **Days 5–6:** Build admin endpoints (with role checks)
- **Day 7:** Done; FE-B can start using these for the admin dashboard

---

## File Dependency Graph

```
schema.sql & seed.sql (locked, reference only)
        ↓
   db.php (BE-A)
        ↓
   auth.php (BE-A)
    ↙        ↘
register.php  response.php & validate.php (BE-B)
login.php            ↓
logout.php    event.php (BE-B)
me.php       categories.php (BE-B)
             admin/* (BE-B)
             
             All endpoints depend on response.php & validate.php
```

---

## Concrete Division of Labor

### **Files & Responsibility**

| File/Endpoint | Owner | What They Do |
|---|---|---|
| `db.php` | BE-A | Create singleton PDO, set timezone, error handling |
| `auth.php` | BE-A | Sessions, `current_user()`, `require_login()`, `require_role()` |
| `response.php` | BE-B | `ok()`, `fail()`, `json_response()`, envelope standard |
| `validate.php` | BE-B | `require_method()`, `clean_string()`, `valid_email()`, `valid_int()`, `valid_money()` |
| `register.php` | BE-A | Registration logic; uses `validate.php` and `response.php` |
| `login.php` | BE-A | Login logic; calls `auth.php::login_user()` |
| `logout.php` | BE-A | Logout logic; calls `auth.php::logout_user()` |
| `me.php` | BE-A | Returns `current_user()` or `null` |
| `event.php` | BE-B | SELECT from events; return via `response.php` |
| `categories.php` | BE-B | SELECT from ticket_categories; return active ones with remaining quota |
| `admin/stats.php` | BE-B | Aggregate queries (SUM, COUNT); auth checks with `auth.php` |
| `admin/attendees.php` | BE-B | SELECT from tickets with user data; search/pagination |
| `admin/update-price.php` | BE-B | UPDATE ticket_categories; validate and respond |
| `admin/update-event.php` | BE-B | UPDATE events; validate all six fields |

---

## Who Depends on Whom?

```
BE-A makes db.php and auth.php
  ↓
Both BE-A and BE-B use them in every endpoint

BE-B makes response.php and validate.php
  ↓
BE-A uses them in register.php, login.php, logout.php
BE-B uses them in all admin and event endpoints
FE-A and FE-B get the standard envelope from all responses
TP-A and TP-B rely on response.php for their endpoints
```

---

## Day-by-Day Execution

### **Day 1: Kickoff**
- [ ] Database created locally (schema.sql + seed.sql run)
- [ ] `config.example.php` → `config.php` (each person fills in their local credentials)
- [ ] Repo cloned and folder structure confirmed
- [ ] This division of work reviewed and signed off

---

### **Day 2: Freeze (Helper Functions & Signatures)**

**BE-A does:**
- [ ] Create `includes/db.php` with `db()` singleton and timezone setup
- [ ] Create `includes/auth.php` with all function signatures (minimal bodies OK for now)
  - `current_user()`
  - `require_login()`
  - `require_role(...$roles)`
  - `login_user($userRow)`
  - `logout_user()`
- [ ] Push both to `develop`

**BE-B does:**
- [ ] Create `includes/response.php` with signatures:
  - `json_response(bool $success, string $message, array $data, array $errors, int $status)`
  - `ok(string $message, array $data, int $status)`
  - `fail(string $message, int $status, array $errors, array $data)`
  - `read_json()`
- [ ] Create `includes/validate.php` with signatures:
  - `require_method(string $method)`
  - `clean_string(mixed $v, int $min, int $max)`
  - `valid_email(mixed $v)`
  - `valid_int(mixed $v, int $min, int $max)`
  - `valid_money(mixed $v, string $min, string $max)`
- [ ] Push both to `develop`

**Handoff:** Both pull each other's helpers and confirm they can `require()` them.

---

### **Days 3–4: Auth Endpoints (BE-A)**

**BE-A builds and tests:**
- [ ] `api/register.php` — validates input, hashes password, inserts user
- [ ] `api/login.php` — validates email/password, regenerates session
- [ ] `api/logout.php` — clears session
- [ ] `api/me.php` — returns current user or null

**Tests:**
- Register with valid/invalid data (400 responses)
- Duplicate email (409)
- Successful login → session ID regenerated
- Logout → session cleared

**Push to `develop` when done.**

---

### **Days 3–4: Event & Category Endpoints (BE-B)**

**BE-B builds and tests (in parallel with BE-A):**
- [ ] `api/event.php` — SELECT from events; return all fields
- [ ] `api/categories.php` — SELECT active categories; calculate remaining quota

**Tests:**
- Event endpoint returns correct conference details
- Categories show current prices and quota
- Sold-out detection (when quota reached)

**Push to `develop` when done.**

---

### **Days 5–6: Admin Endpoints (BE-B)**

**BE-B builds and tests:**
- [ ] `api/admin/stats.php` — aggregate totals, per-category breakdown
- [ ] `api/admin/attendees.php` — list + search + pagination
- [ ] `api/admin/update-price.php` — change category price (admin only)
- [ ] `api/admin/update-event.php` — edit all event fields (admin only)

**Tests:**
- Correct auth: 401 if logged out, 403 if not admin
- Stats match database queries
- Search and pagination work
- Price updates don't affect old orders (unit_price snapshot)

**Push to `develop` when done.**

---

### **Days 7–9: Ticketing & Payment (TP-A & TP-B build)**

Both BE-A and BE-B support TP by:
- Answering questions about database schema
- Providing test data (e.g., a paid order for TP-B to create tickets from)
- Reviewing TP's SQL if needed

No new backend files; TP owns their own modules.

---

### **Days 10–11: System Testing & Bug Fixes**

**QA tests all BE endpoints:**
- Invalid input handling
- Auth & role checks
- SQL injection attempts (must be blocked by prepared statements)
- Concurrent requests (no race conditions in the schema)

**BE-A & BE-B fix bugs, refactor if needed.**

---

### **Days 12–14: Polish & Submission**

- [ ] All endpoints live and tested
- [ ] Code reviewed (one pull request per feature, one reviewer)
- [ ] Documentation updated
- [ ] Demo ready

---

## How to Work Together

### **Communication**
- One group chat for quick questions
- 10-minute daily stand-up: "What did I do? What am I doing? Any blockers?"
- Code reviews: one person reviews before merging to `develop`

### **Branching & Merging**
```bash
# Each person works on a feature branch
git checkout -b feature/register-endpoint    # BE-A
git checkout -b feature/response-helpers     # BE-B

# After testing locally, create a pull request
# One reviewer, then merge to develop
# Never push directly to main
```

### **If You're Blocked**
- **BE-A blocks?** Unlikely; you're foundational. Escalate to PM if GitHub/SSH/local DB issues.
- **BE-B blocks on BE-A?** Pull `develop` to get the latest helpers. If signatures changed, ask in chat.
- **Both block on TP?** TP-A's pricing module isn't ready yet. Continue with stubs or mock data.

### **Testing Locally**
```bash
# BE-A after building db.php
mysql -u root -p < database/schema.sql
mysql -u root -p uiconf < database/seed.sql
php -r "require 'includes/db.php'; var_dump(db()->query('SELECT 1')->fetch());"

# BE-B after building response.php
php -r "require 'includes/response.php'; ok('Test', ['message' => 'Hello']);"

# Test endpoints
curl -X GET http://localhost/uiconf/api/event.php
curl -X POST http://localhost/uiconf/api/login.php -d '{"email":"admin@uiconf.test","password":"password"}'
```

---

## Error Handling (Critical)

Every endpoint must follow this order:

```php
<?php
// 1. Check HTTP method
require_method('POST');

// 2. Check auth/role (if needed)
require_login();
require_role('admin');

// 3. Read and validate input
$data = read_json();
$email = valid_email($data['email'] ?? null);
if (!$email) {
    fail('Invalid email', 400, [['field' => 'email', 'message' => 'Enter a valid email address']]);
}

// 4. Run database queries
try {
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    // Log the error, return friendly message
    error_log($e->getMessage());
    fail('Something went wrong', 500);
}

// 5. Return result
ok('Login successful', ['user' => $user], 200);
?>
```

**Never:**
- Expose raw database errors to the user
- Trust the browser for prices, totals, or auth
- Forget prepared statements (always use `?` or `:named` placeholders)

---

## Security Checklist

Before pushing any endpoint, confirm:

- [ ] `require_method()` called at the top
- [ ] `require_login()` or `require_role()` called for protected endpoints
- [ ] All input validated by `validate.php` helpers
- [ ] All SQL queries use prepared statements (no string concatenation)
- [ ] Passwords hashed with `password_hash()` (BE-A in register.php)
- [ ] Error messages don't expose database structure (use `error_log()` for details)
- [ ] Sessions use HttpOnly and SameSite cookies (set in `auth.php`)

---

## Key Handoffs & Dependencies

| Day | Handoff | From | To | What |
|-----|---------|------|----|----|
| 2 | Freeze | BE-A & BE-B | Everyone | `db.php`, `auth.php`, `response.php`, `validate.php` signatures |
| 5 | Auth endpoints live | BE-A | FE-A | Login/register work; FE-A can build login page |
| 7 | Event endpoints live | BE-B | FE-A | Event info loads; FE-A can build landing page |
| 7 | Admin helpers live | BE-B | FE-B | FE-B can start building admin dashboard UI |
| 9 | Ticketing endpoints live | TP-A & TP-B | FE-A & FE-B | Payment flow and ticket pages can be completed |
| 11 | System tests pass | QA | BE-A & BE-B | No critical bugs; ready for final integration |

---

## FAQ

**Q: Can we pair-program a complex endpoint?**  
A: Yes, for tricky transactions or edge cases. But one person still "owns" the file and pushes it.

**Q: What if I break something in my code?**  
A: That's normal. Fix it, test it, push to your branch, create a PR, get reviewed, merge.

**Q: What if I need to change the database schema?**  
A: Submit a PR to `schema.sql` with a comment explaining why. PM and BE-A review. Changes after Day 2 must be unanimous.

**Q: Should we write tests?**  
A: Yes, informally at least. Test each endpoint with Postman or curl. QA will test formally with a test plan.

**Q: Can BE-A build admin endpoints too?**  
A: No. BE-B owns them. BE-A focuses on auth; BE-B on queries and reporting.

---

## Summary Table

| Aspect | BE-A | BE-B |
|--------|------|------|
| **Files owned** | `db.php`, `auth.php` | `response.php`, `validate.php` |
| **Endpoints owned** | register, login, logout, me | event, categories, admin/* |
| **Unblocks** | Everyone (foundational) | All endpoints (they all use helpers) |
| **Depends on** | Nothing | `db.php`, `auth.php` |
| **Day 2 deliverable** | Two files with signatures | Two files with signatures |
| **Days 3–6 deliverable** | 4 endpoints (auth) | 6 endpoints (event + admin) |
| **Testing focus** | Session handling, role checks | Queries, pagination, aggregates |
| **Security focus** | Password hashing, session cookies | SQL injection, prepared statements |

---

## Next Steps (Action Items)

1. **Both review this document** and confirm the split makes sense
2. **BE-A:** Start designing `db.php` connection pooling (singleton pattern)
3. **BE-B:** Start designing `response.php` envelope and error handling
4. **Both:** Set up local environment (schema + seed imported, config.php filled in)
5. **Both:** Confirm you can run a simple PHP file that connects to the database
6. **PM:** Create the GitHub repo with folder structure and .gitignore
7. **Day 2 Freeze:** Both push your helper files with final signatures

---

**Ready to start?** Once you've confirmed the split, I can dive into the implementation of `db.php` with you (BE-A) or `response.php` (BE-B) — whichever part you're on.
