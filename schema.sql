-- =============================================================================
-- Event Registration & Ticket Payment Platform — Database Schema
-- University of Ibadan Technology Conference 2026
-- 
-- Web Development Group 5 Project · SRS Addendum v1.1
-- 
-- INSTRUCTIONS:
-- 1. Run this file first: mysql -u root -p < schema.sql
-- 2. Then run seed.sql to populate event, categories, and test accounts
-- 3. Database name: uiconf
-- 4. Engine: InnoDB (transactions + foreign keys required)
-- 5. Charset: utf8mb4 (emoji-safe, full Unicode)
-- 
-- DESIGN NOTES:
-- - One event per deployment (extension: future multi-event)
-- - Prices snapshot into order_items so edits don't alter past orders
-- - Payments table records every attempt (success or failed)
-- - Tickets table: one row per ticket unit issued
-- - Verification is atomic: one UPDATE checks and marks as used
-- - All times: Africa/Lagos timezone (UTC+1)
-- - Money: DECIMAL(10,2) in DB, sent as strings in JSON
-- =============================================================================

CREATE DATABASE IF NOT EXISTS uiconf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uiconf;

-- =============================================================================
-- TABLE: events
-- Purpose: Conference details; one record per deployment
-- 
-- Fields:
--   id           : Auto-increment primary key
--   title        : Conference name (e.g., "University of Ibadan Technology Conference 2026")
--   event_date   : Date and time (DATETIME, Africa/Lagos timezone)
--   venue        : Physical location or address
--   description  : Long-form event description (Rich text OK, escaped on display)
--   schedule     : Agenda and timeline (e.g., "09:00 Registration | 10:00 Keynote")
--   speakers     : Speaker list (names, titles, or rich text)
--   updated_at   : Timestamp of last edit (auto-updated on admin change)
-- =============================================================================

CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    event_date DATETIME NOT NULL,
    venue VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    schedule TEXT NOT NULL,
    speakers TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB;

-- =============================================================================
-- TABLE: users
-- Purpose: All people in the system (attendees, admins, gate officers)
-- 
-- Fields:
--   id           : Auto-increment primary key
--   full_name    : Full name (2–100 chars, trimmed)
--   email        : Unique email; stored lowercase
--   phone        : Phone number (10–15 digits; spaces/dashes removed for storage)
--   password_hash: bcrypt hash (never plain text, 255 chars for bcrypt output)
--   attendee_type: ENUM student|staff|public|vip (informational; does NOT restrict category access)
--   role         : ENUM attendee|admin|verifier (controls access to endpoints)
--   created_at   : Account creation timestamp
-- 
-- Constraints:
--   email is UNIQUE (no duplicate registrations)
--   password_hash never exposed to frontend
-- 
-- Note:
--   attendee_type lets the event organizer categorize who registered,
--   but does NOT prevent a student from buying VIP tickets.
--   Category access is open; payment is the only gate.
-- =============================================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    attendee_type ENUM('student', 'staff', 'public', 'vip') NOT NULL DEFAULT 'public',
    role ENUM('attendee', 'admin', 'verifier') NOT NULL DEFAULT 'attendee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- =============================================================================
-- TABLE: ticket_categories
-- Purpose: Ticket types and pricing; controls quotas
-- 
-- Fields:
--   id        : Auto-increment primary key
--   event_id  : Foreign key to events; allows future multi-event
--   name      : Category name (e.g., "Student", "VIP")
--   price     : Unit price in NGN; DECIMAL(10,2) for exactness
--   quota     : Total number of tickets available for this category
--   is_active : 1 = active, 0 = deactivated (soft delete, category hidden from buyers)
-- 
-- Business Logic:
--   remaining = quota - (COUNT of issued + used tickets for this category)
--   If remaining < quantity requested, return 409 (sold out)
-- 
-- Note:
--   Four fixed categories: Student (₦2,000), Staff (₦5,000), 
--   General Public (₦7,500), VIP (₦20,000)
--   Prices and quotas are mutable by admin but snapshot into order_items
-- =============================================================================

CREATE TABLE ticket_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quota INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    
    FOREIGN KEY (event_id) REFERENCES events(id),
    INDEX idx_event_id (event_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- =============================================================================
-- TABLE: orders
-- Purpose: Shopping cart; represents one purchase session
-- 
-- Fields:
--   id           : Auto-increment primary key
--   user_id      : Foreign key to users; the buyer
--   total_amount : Total order price (sum of line_totals); DECIMAL(10,2)
--   status       : ENUM pending|paid|cancelled
--     - pending  : Order created, awaiting payment
--     - paid     : Payment successful, tickets issued
--     - cancelled: User or admin cancelled
--   created_at   : Order creation timestamp
-- 
-- Business Logic:
--   - Order is created with status pending when POST /api/order.php succeeds
--   - Status moves to paid ONLY inside a transaction after payment success
--   - If payment fails, order stays pending; buyer can retry with a new reference
--   - Tickets exist ONLY if status = paid
-- 
-- Note:
--   total_amount is recalculated and locked at payment time (server-side).
--   Browser total is estimated and not trusted.
-- =============================================================================

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =============================================================================
-- TABLE: order_items
-- Purpose: Line-by-line breakdown of an order
-- 
-- Fields:
--   id           : Auto-increment primary key
--   order_id     : Foreign key to orders; groups items by order
--   category_id  : Foreign key to ticket_categories
--   quantity     : Number of tickets of this category (1–10)
--   unit_price   : Price PER ticket, snapshotted from ticket_categories.price AT ORDER TIME
--   line_total   : quantity * unit_price (calculated at order time, immutable)
-- 
-- Constraints:
--   UNIQUE KEY (order_id, category_id): One category per order (prevent duplicates)
-- 
-- Business Logic:
--   line_total = quantity * unit_price
--   order.total_amount = SUM(all line_totals in this order)
--   
--   unit_price is snapshotted so that if admin changes the category price later,
--   old orders still display what the customer actually paid.
--   Comparisons, reports, and tickets are based on this snapshot.
-- 
-- Note:
--   Snapshot pattern is critical for accounting and audit trails.
-- =============================================================================

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,
    
    UNIQUE KEY uq_order_category (order_id, category_id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (category_id) REFERENCES ticket_categories(id),
    INDEX idx_order_id (order_id),
    INDEX idx_category_id (category_id)
) ENGINE=InnoDB;

-- =============================================================================
-- TABLE: payments
-- Purpose: Payment transaction log; records every attempt
-- 
-- Fields:
--   id               : Auto-increment primary key
--   order_id         : Foreign key to orders; the order being paid for
--   reference        : Unique payment reference (e.g., "PAY-3F9A1C7E5B2D4A68")
--     - Generated once by browser (random hex string)
--     - UNIQUE constraint prevents duplicate charges
--   amount           : Amount paid (should match order.total_amount); DECIMAL(10,2)
--   method           : Payment method (default: "simulated"; future: "stripe", "paystack", etc.)
--   status           : ENUM success|failed
--     - success: Payment confirmed, tickets issued
--     - failed : Payment declined or internal error, order stays pending
--   provider_response: Response body from payment provider (or error details)
--   created_at       : Payment attempt timestamp
-- 
-- Business Logic:
--   Idempotency: If the same reference arrives twice, return the stored result.
--   
--   On success:
--     1. INSERT payments (status = success)
--     2. UPDATE orders SET status = paid
--     3. INSERT tickets (one per order_item.quantity)
--     (All in one transaction; if any fails, all rollback)
--   
--   On failure:
--     1. INSERT payments (status = failed)
--     2. order stays pending
--     3. No tickets are issued
--     4. Buyer can retry with a new reference
-- 
-- Note:
--   Failed attempts are logged for admin reporting and debugging.
--   Amount field allows refund tracking in future versions.
-- =============================================================================

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    reference VARCHAR(40) NOT NULL UNIQUE,
    amount DECIMAL(10, 2) NOT NULL,
    method VARCHAR(20) NOT NULL DEFAULT 'simulated',
    status ENUM('success', 'failed') NOT NULL,
    provider_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX idx_order_id (order_id),
    INDEX idx_reference (reference),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =============================================================================
-- TABLE: tickets
-- Purpose: Individual admission rights; one per ticket unit purchased
-- 
-- Fields:
--   id               : Auto-increment primary key
--   order_id         : Foreign key to orders; the purchase that created this ticket
--   category_id      : Foreign key to ticket_categories; the category of ticket
--   ticket_number    : Human-readable ID (e.g., "UIT26-7K4Q9"); UNIQUE
--     - Format: TICKET_PREFIX (UIT26) + hyphen + 5 random alphanumeric
--     - Chars: A–Z, 2–9 (no 0, O, 1, I to avoid confusion)
--     - For manual lookup at the gate if QR scanner fails
--   verification_code: Long random secret (32 hex chars); UNIQUE
--     - Generated by: bin2hex(random_bytes(16))
--     - What the QR code encodes
--     - Never displayed to user (only in QR image)
--   status           : ENUM issued|used
--     - issued: Fresh ticket, not yet scanned at gate
--     - used  : Scanned and admitted at gate
--   issued_at        : Timestamp when ticket was created (upon payment success)
--   used_at          : Timestamp when ticket was scanned at gate (NULL if not used)
--   verified_by      : Foreign key to users; which verifier/admin scanned the ticket
-- 
-- Business Logic (Reuse Prevention):
--   Gate officer scans ticket (via QR or typed number).
--   One atomic UPDATE runs:
--   
--     UPDATE tickets 
--       SET status = 'used', used_at = NOW(), verified_by = :officer
--       WHERE (verification_code = :code OR ticket_number = :number) 
--         AND status = 'issued'
--   
--   If exactly 1 row updated: valid (mark as used, admit)
--   If 0 rows updated: SELECT to check if found and already used → used|invalid
--   
--   Why atomic? Two simultaneous gate scans cannot both succeed.
--   The check (WHERE status = 'issued') and change (SET status = 'used')
--   are one indivisible statement, so race conditions are impossible.
-- 
-- Note:
--   Tickets created ONLY inside a transaction after payment success.
--   No payment → no ticket (enforced at application level).
-- =============================================================================

CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    verification_code CHAR(32) NOT NULL UNIQUE,
    status ENUM('issued', 'used') NOT NULL DEFAULT 'issued',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    verified_by INT UNSIGNED NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (category_id) REFERENCES ticket_categories(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_order_id (order_id),
    INDEX idx_ticket_number (ticket_number),
    INDEX idx_verification_code (verification_code),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =============================================================================
-- TABLE: verification_log
-- Purpose: Audit trail of all gate scans
-- 
-- Fields:
--   id         : Auto-increment primary key
--   ticket_id  : Foreign key to tickets; NULL if code/number not found
--   input_code : The value scanned (ticket number or verification code); up to 100 chars
--   result     : ENUM valid|used|invalid
--     - valid  : Fresh ticket, marked as used
--     - used   : Already scanned
--     - invalid: Not found
--   officer_id : Foreign key to users; which admin/verifier performed the scan
--   created_at : Timestamp of the scan attempt
-- 
-- Purpose:
--   Provides an audit trail for dispute resolution.
--   Helps demo evidence (shows who scanned what, when, with what result).
--   Useful for identifying gate officer activity and edge cases.
-- 
-- Note:
--   One row per scan attempt, regardless of outcome.
--   Can be queried to see all failed scans, identify patterns, etc.
-- =============================================================================

CREATE TABLE verification_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NULL,
    input_code VARCHAR(100) NOT NULL,
    result ENUM('valid', 'used', 'invalid') NOT NULL,
    officer_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (officer_id) REFERENCES users(id),
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_officer_id (officer_id),
    INDEX idx_result (result),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =============================================================================
-- FINAL NOTES
-- =============================================================================
-- 
-- Relationships at a Glance:
--   events (1) ──→ (∞) ticket_categories ──→ (∞) tickets
--   users (1) ──→ (∞) orders ──→ (∞) order_items
--   users (1) ──→ (∞) orders ──→ (∞) payments
--   orders (1) ──→ (∞) tickets
--   users (1) ──→ (∞) verification_log
-- 
-- Transaction Safety:
--   All payment and ticketing operations use InnoDB transactions.
--   Row locking (SELECT ... FOR UPDATE) prevents overselling.
--   Atomic updates prevent ticket reuse.
-- 
-- Time Zone:
--   All DATETIME columns are in Africa/Lagos (UTC+1).
--   Set in both PHP and MySQL:
--     PHP: date_default_timezone_set('Africa/Lagos');
--     MySQL: SET time_zone = '+01:00';
-- 
-- Charset:
--   utf8mb4 supports emoji, accents, and all Unicode.
--   Display: All user text must be HTML-escaped before rendering.
-- 
-- Next Step:
--   After schema creation, run seed.sql to populate event, categories, and test accounts.
-- =============================================================================
