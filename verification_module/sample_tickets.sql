-- OPTIONAL: fake tickets so you can test verification before your partner's payment part is ready.
-- Works with the ticketing_db database. Run it once.
USE ticketing_db;

INSERT INTO orders (buyer_name, buyer_email, category_id, quantity, total_amount, status)
VALUES ('Test Buyer', 'test@example.com', 4, 3, 54000.00, 'paid');

SET @order_id = LAST_INSERT_ID();

INSERT INTO tickets (order_id, ticket_number, holder_name, status, used_at) VALUES
(@order_id, 'TEST-VALID-1', 'Test Buyer', 'valid', NULL),
(@order_id, 'TEST-VALID-2', 'Test Buyer', 'valid', NULL),
(@order_id, 'TEST-USED-1', 'Test Buyer', 'used', NOW());
