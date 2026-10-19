-- Verification module: extra tables for gate officers and the scan log.
-- Run this AFTER the ticketing database (tickets, orders, ticket_categories) exists.
-- If your group uses the main project database, change the next line to: USE event_ticket_db;
USE ticketing_db;

CREATE TABLE gate_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE verification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL,
    result ENUM('valid','used','invalid','checked_in') NOT NULL,
    officer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES gate_officers(id)
);

-- Officer login: username = gate1, password = password (change it later)
INSERT INTO gate_officers (username, password) VALUES
('gate1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
