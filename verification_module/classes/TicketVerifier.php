<?php
// Checks a ticket at the gate.
// The ticket can be typed as the ticket number (e.g. UIT26-7K4Q9)
// or as the long verification code (what the QR code holds).
// Results: valid, used, invalid
class TicketVerifier {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function cleanInput($input) {
        return trim($input);
    }

    // Only letters, numbers and dashes, 3 to 100 characters
    public function hasValidFormat($input) {
        return preg_match('/^[A-Za-z0-9-]{3,100}$/', $input) == 1;
    }

    // Finds a ticket and the holder's name and category (the verification code is never sent out)
    public function findTicket($input) {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.ticket_number, t.status, t.used_at,
                    c.name AS category_name, u.full_name AS holder_name
             FROM tickets t
             JOIN ticket_categories c ON t.category_id = c.id
             JOIN orders o ON t.order_id = o.id
             JOIN users u ON o.user_id = u.id
             WHERE t.verification_code = ? OR t.ticket_number = ?
             LIMIT 1"
        );
        $stmt->execute([$input, $input]);
        return $stmt->fetch();
    }

    // Checks the ticket AND marks it as used in one step
    public function verify($input, $officerId) {
        $input = $this->cleanInput($input);

        if (!$this->hasValidFormat($input)) {
            return [
                "success" => false,
                "status" => "invalid",
                "message" => "Invalid ticket. Use only letters, numbers and dashes."
            ];
        }

        // ONE update that checks "status = issued" and changes it to "used".
        // If two officers scan the same ticket together, only one of them gets a row changed.
        $stmt = $this->conn->prepare(
            "UPDATE tickets
             SET status = 'used', used_at = NOW(), verified_by = ?
             WHERE (verification_code = ? OR ticket_number = ?) AND status = 'issued'"
        );
        $stmt->execute([$officerId, $input, $input]);
        $ticket = $this->findTicket($input);

        if ($stmt->rowCount() == 1) {
            return [
                "success" => true,
                "status" => "valid",
                "message" => "Valid ticket. Guest admitted.",
                "ticket" => $ticket
            ];
        }
        if ($ticket) {
            return [
                "success" => false,
                "status" => "used",
                "message" => "Ticket already used. Do not allow entry.",
                "ticket" => $ticket
            ];
        }
        return [
            "success" => false,
            "status" => "invalid",
            "message" => "Invalid ticket. This ticket does not exist."
        ];
    }
}
