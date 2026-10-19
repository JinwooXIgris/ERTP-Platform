<?php
// Checks tickets and marks them as used.
// Results (status): valid, used, invalid, checked_in
class TicketVerifier {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Capital letters and no spaces, so "ticket-1 " and "TICKET-1" are the same
    public function cleanNumber($input) {
        return strtoupper(trim($input));
    }

    // Only letters, numbers and dashes, 3 to 30 characters
    public function hasValidFormat($number) {
        return preg_match('/^[A-Z0-9-]{3,30}$/', $number) == 1;
    }

    public function findTicket($number) {
        $stmt = $this->conn->prepare(
            "SELECT t.*, c.name AS category_name
             FROM tickets t
             LEFT JOIN orders o ON t.order_id = o.id
             LEFT JOIN ticket_categories c ON o.category_id = c.id
             WHERE t.ticket_number = ?"
        );
        $stmt->execute([$number]);
        return $stmt->fetch();
    }

    // Looks at a ticket but does NOT change it
    public function verify($number) {
        if (!$this->hasValidFormat($number)) {
            return [
                "success" => false,
                "status" => "invalid",
                "message" => "Invalid ticket number. Use only letters, numbers and dashes."
            ];
        }

        $ticket = $this->findTicket($number);

        if (!$ticket) {
            return [
                "success" => false,
                "status" => "invalid",
                "message" => "Invalid ticket. This ticket number does not exist."
            ];
        }
        if ($ticket["status"] == "used") {
            return [
                "success" => false,
                "status" => "used",
                "message" => "Ticket already used. Do not allow entry.",
                "ticket" => $ticket
            ];
        }
        return [
            "success" => true,
            "status" => "valid",
            "message" => "Valid ticket. Allow entry.",
            "ticket" => $ticket
        ];
    }

    // Verifies the ticket and then marks it as used
    public function checkIn($number) {
        $result = $this->verify($number);
        if ($result["status"] != "valid") {
            return $result;   // invalid or already used, nothing to check in
        }

        // "AND status = 'valid'" means only ONE officer can check a ticket in,
        // even if two officers press the button at the same time
        $stmt = $this->conn->prepare(
            "UPDATE tickets SET status = 'used', used_at = NOW()
             WHERE ticket_number = ? AND status = 'valid'"
        );
        $stmt->execute([$number]);

        if ($stmt->rowCount() == 0) {
            $result["success"] = false;
            $result["status"] = "used";
            $result["message"] = "Ticket already used. Do not allow entry.";
            $result["ticket"] = $this->findTicket($number);
            return $result;
        }

        $result["status"] = "checked_in";
        $result["message"] = "Ticket checked in. Guest admitted.";
        $result["ticket"] = $this->findTicket($number);
        return $result;
    }
}
