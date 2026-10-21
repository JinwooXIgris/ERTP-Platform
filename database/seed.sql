-- =============================================================================
-- Event Registration & Ticket Payment Platform — Seed Data
-- University of Ibadan Technology Conference 2026
-- 
-- Web Development Group 5 Project · SRS Addendum v1.1
-- 
-- INSTRUCTIONS:
-- 1. Run schema.sql FIRST
-- 2. Then run this file: mysql -u root -p uiconf < seed.sql
-- 
-- WHAT THIS DOES:
-- - Creates the event record (University of Ibadan Technology Conference 2026)
-- - Creates four ticket categories (Student, Staff, General Public, VIP)
-- - Creates three test user accounts (admin, verifier, attendee) with bcrypt-hashed passwords
-- 
-- IMPORTANT NOTES:
-- - The password hash below is for "password" (local development only)
-- - All three test accounts can be used immediately for testing
-- - The hash is bcrypt-compatible and verified by PHP's password_verify()
-- - In production, use proper credentials and never commit test accounts
-- 
-- TIMEZONE:
-- - Event date/time: 2026-11-20 09:00:00 (Africa/Lagos)
-- - Make sure MySQL timezone is set: SET time_zone = '+01:00';
-- =============================================================================

USE uiconf;

-- Generate a password hash for local testing
-- Hash is for the word 'password' using bcrypt (PHP's PASSWORD_DEFAULT)
-- To generate a fresh hash: php -r 'echo password_hash("password", PASSWORD_DEFAULT);'
SET @password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- =============================================================================
-- Event Record
-- =============================================================================
-- 
-- This is the MAIN conference event.
-- In future, multiple events could exist in the same database.
-- For this prototype, there is one event per deployment.
-- 
-- Fields:
--   id          : Will be 1 (auto-increment)
--   title       : Official event name
--   event_date  : November 20, 2026, 9:00 AM Lagos time
--   venue       : Physical location (update as needed)
--   description : Marketing copy and details
--   schedule    : Agenda; pipe-separated for easy parsing
--   speakers    : Speaker lineup; names and affiliations
-- 
-- CUSTOMIZE THIS:
-- - Update venue, description, schedule, speakers with actual conference details
-- - Confirm event_date with the organizing committee
-- - Replace PLACEHOLDER values with real information

INSERT INTO events (
    title,
    event_date,
    venue,
    description,
    schedule,
    speakers
) VALUES (
    'University of Ibadan Technology Conference 2026',
    '2026-11-20 09:00:00',
    'Kunle Torimiro Centre, University of Ibadan, Ibadan, Oyo State, Nigeria',
    'A premier technology conference bringing together students, industry professionals, and academic leaders to discuss emerging trends in AI, cloud computing, fintech, and software development. Join 300+ attendees for keynotes, panel discussions, workshops, and networking opportunities.',
    '09:00 - 10:00 Registration & Breakfast | 10:00 - 11:00 Keynote Address: "AI and the Future of Work" | 11:15 - 12:30 Panel Discussion: "Building Tech Startups in Nigeria" | 12:30 - 13:30 Lunch Break | 13:30 - 14:45 Workshop Track A: Cloud Architecture | 13:30 - 14:45 Workshop Track B: Mobile App Development | 15:00 - 16:00 Workshop Track C: Data Science & ML | 16:15 - 17:00 Closing Ceremony & Prize Giveaway',
    'Dr. Adebayo Okafor (Computer Science Dept., UI) | Chinedu Okoro (CTO, Paystack) | Zainab Al-Rashid (AI Research Lead, Google Africa) | Prof. Olayinka Abayomi (Department of Statistics, UI) | Yusuf Mohammed (Founder, TechNaija Hub)'
);

-- =============================================================================
-- Ticket Categories
-- =============================================================================
-- 
-- Four categories: Student, Staff, General Public, VIP
-- Prices in NGN; quotas represent maximum tickets per category
-- 
-- PRICE BREAKDOWN:
--   Student        : ₦2,000   (200 quota)  ← Verified students with ID
--   Staff          : ₦5,000   (100 quota)  ← University and college staff
--   General Public : ₦7,500   (150 quota)  ← Non-students, non-staff
--   VIP            : ₦20,000  (50 quota)   ← Industry leaders, speakers, sponsors
-- 
-- CUSTOMIZE THIS:
-- - Confirm prices with the event organizer
-- - Adjust quotas based on expected attendance and venue capacity
-- - is_active = 1 means category is visible to buyers
-- 
-- Note: attendee_type (student, staff, public, vip) is informational only.
-- A student can buy a VIP ticket if they pay the price.
-- Category access is open; price is the only gate.

INSERT INTO ticket_categories (event_id, name, price, quota, is_active) VALUES
(1, 'Student', 2000.00, 200, 1),
(1, 'Staff', 5000.00, 100, 1),
(1, 'General Public', 7500.00, 150, 1),
(1, 'VIP', 20000.00, 50, 1);

-- =============================================================================
-- Test User Accounts
-- =============================================================================
-- 
-- Three test accounts are created for immediate testing.
-- Password for all three: 'password'
-- Hash: bcrypt ($2y$10$...)
-- 
-- THESE ARE FOR LOCAL DEVELOPMENT ONLY.
-- In production, never seed plain test accounts. Use proper authentication.
-- 
-- Account 1: ADMIN
--   Email: admin@uiconf.test
--   Role: admin (full access to dashboard, price edits, user management)
--   Type: staff (informational)
-- 
-- Account 2: GATE OFFICER / VERIFIER
--   Email: verifier@uiconf.test
--   Role: verifier (can only verify tickets at the gate)
--   Type: staff (informational)
-- 
-- Account 3: TEST ATTENDEE
--   Email: attendee@uiconf.test
--   Role: attendee (regular user, can buy tickets)
--   Type: student (informational)
-- 
-- LOGIN CREDENTIALS:
--   Email: admin@uiconf.test       | Password: password | Role: admin
--   Email: verifier@uiconf.test    | Password: password | Role: verifier
--   Email: attendee@uiconf.test    | Password: password | Role: attendee
-- 
-- HOW TO GENERATE YOUR OWN HASH:
--   php -r 'echo password_hash("your_password", PASSWORD_DEFAULT);'
-- 
-- TO CHANGE A PASSWORD IN THE DATABASE:
--   UPDATE users SET password_hash = '<new_hash>' WHERE id = 1;

INSERT INTO users (full_name, email, phone, password_hash, attendee_type, role) VALUES
(
    'Admin User',
    'admin@uiconf.test',
    '08000000001',
    @password_hash,
    'staff',
    'admin'
),
(
    'Gate Officer (Verifier)',
    'verifier@uiconf.test',
    '08000000002',
    @password_hash,
    'staff',
    'verifier'
),
(
    'Test Attendee',
    'attendee@uiconf.test',
    '08000000003',
    @password_hash,
    'student',
    'attendee'
);

-- =============================================================================
-- VERIFICATION
-- =============================================================================
-- 
-- After running this file, verify the data:
-- 
--   SELECT COUNT(*) FROM events;              -- Should be 1
--   SELECT COUNT(*) FROM ticket_categories;   -- Should be 4
--   SELECT COUNT(*) FROM users;               -- Should be 3
--   SELECT * FROM ticket_categories;          -- Verify prices and quotas
--   SELECT * FROM users \G                    -- Verify roles (admin, verifier, attendee)
-- 
-- Test a login:
--   Go to http://localhost/uiconf/public/login.html
--   Enter: admin@uiconf.test / password
--   You should see a welcome message and access the admin dashboard
-- 
-- =============================================================================

-- Final checkpoint: Display the seeded data
SELECT '=== EVENT ===' AS section;
SELECT id, title, DATE_FORMAT(event_date, '%Y-%m-%d %H:%i') AS event_date, venue FROM events;

SELECT '=== TICKET CATEGORIES ===' AS section;
SELECT id, name, CONCAT('₦', FORMAT(price, 2)) AS price, quota, IF(is_active, 'Yes', 'No') AS active FROM ticket_categories;

SELECT '=== TEST USERS ===' AS section;
SELECT id, full_name, email, role FROM users;

-- =============================================================================
-- NOTES FOR THE TEAM
-- =============================================================================
--
-- 1. TIMEZONE:
--    All DATETIME values are in Africa/Lagos (UTC+1).
--    Ensure your MySQL is configured:
--      SET time_zone = '+01:00';
--    And your PHP is configured:
--      date_default_timezone_set('Africa/Lagos');
--
-- 2. CURRENCY:
--    All prices are in NGN (Nigerian Naira).
--    Display symbol: ₦
--    In JSON: sent as strings with two decimals, e.g., "2000.00"
--
-- 3. PASSWORD HASHING:
--    Always use PHP's password_hash() and password_verify().
--    Never store plain-text passwords.
--    Bcrypt is automatically salted and time-resistant.
--
-- 4. TEST DATA:
--    These accounts exist only for development.
--    Before launch, delete these test users or use production credentials.
--
-- 5. QUOTAS:
--    Total capacity: 200 + 100 + 150 + 50 = 500 tickets
--    Adjust quotas as needed (admin can edit via dashboard).
--    Soft quota check at order creation; hard check at payment (with row lock).
--
-- 6. NEXT STEPS:
--    a) Import this seed.sql file
--    b) Test the event.php endpoint: http://localhost/uiconf/api/event.php
--    c) Log in with admin@uiconf.test / password
--    d) Begin building registration and payment flows
--
-- =============================================================================
