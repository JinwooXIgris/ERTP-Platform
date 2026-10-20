-- OPTIONAL test data for the verification module.
-- Run schema.sql and seed.sql (the group's files) FIRST, then run this file ONCE.
-- It adds one paid order and three test tickets, owned by the seed account attendee@uiconf.test.
-- Gate officer to log in with (already created by seed.sql): verifier@uiconf.test / password
USE uiconf;

-- One paid order: 3 Student tickets (3 x 2000)
INSERT INTO orders (user_id, total_amount, status)
SELECT id, 6000.00, 'paid' FROM users WHERE email = 'attendee@uiconf.test';

SET @order_id = LAST_INSERT_ID();
SET @category_id = (SELECT id FROM ticket_categories WHERE name = 'Student' LIMIT 1);

-- Three tickets: two fresh ones and one already used
-- (the long verification code is made from the ticket number, only for testing)
INSERT INTO tickets (order_id, category_id, ticket_number, verification_code, status, used_at) VALUES
(@order_id, @category_id, 'UIT26-TEST1', MD5('UIT26-TEST1'), 'issued', NULL),
(@order_id, @category_id, 'UIT26-TEST2', MD5('UIT26-TEST2'), 'issued', NULL),
(@order_id, @category_id, 'UIT26-USED1', MD5('UIT26-USED1'), 'used', NOW());
