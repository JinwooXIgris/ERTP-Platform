<?php
require_once __DIR__ . '/config.php';

// Never begins or commits a transaction. payment.php does that.
class TicketIssuer {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Format UIT26-7K4Q9. Letters skip 0, O, 1, I.
    public function makeTicketNumber() {
        $letters = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $prefix = defined('TICKET_PREFIX') ? TICKET_PREFIX : 'UIT26';

        for ($try = 0; $try < 5; $try++) {
            $random = "";
            for ($i = 0; $i < 5; $i++) {
                $random .= $letters[random_int(0, strlen($letters) - 1)];
            }
            $number = $prefix . "-" . $random;

            $stmt = $this->pdo->prepare("SELECT id FROM tickets WHERE ticket_number = ?");
            $stmt->execute([$number]);
            if (!$stmt->fetch()) {
                return $number;
            }
        }
        throw new Exception("Could not make a unique ticket number");
    }

    public static function makeVerificationCode() {
        return bin2hex(random_bytes(16));
    }

    public function getTicketsOfOrder($orderId) {
        $stmt = $this->pdo->prepare(
            "SELECT id, order_id, category_id, ticket_number, verification_code, status
             FROM tickets
             WHERE order_id = ?
             ORDER BY id"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function issueForOrder($orderId) {
        // Already issued: return them, make no more
        $existing = $this->getTicketsOfOrder($orderId);
        if (count($existing) > 0) {
            return $existing;
        }

        $stmt = $this->pdo->prepare("SELECT category_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            for ($i = 0; $i < $item["quantity"]; $i++) {
                $this->insertTicket($orderId, $item["category_id"]);
            }
        }
        return $this->getTicketsOfOrder($orderId);
    }

    private function insertTicket($orderId, $categoryId) {
        for ($try = 0; $try < 5; $try++) {
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO tickets (order_id, category_id, ticket_number, verification_code)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $orderId,
                    $categoryId,
                    $this->makeTicketNumber(),
                    self::makeVerificationCode()
                ]);
                return;
            } catch (PDOException $e) {
                // 1062 = duplicate key: retry
                if (!isset($e->errorInfo[1]) || $e->errorInfo[1] != 1062) {
                    throw $e;
                }
            }
        }
        throw new Exception("Could not insert the ticket");
    }
}

class TicketReader {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function orderIsPaidAndOwned($orderId, $userId) {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'paid'"
        );
        $stmt->execute([$orderId, $userId]);
        return $stmt->fetch() ? true : false;
    }

    public function getByOrder($orderId) {
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.ticket_number, t.verification_code, c.name AS category,
                    t.status, u.full_name AS holder_name, e.title AS event_title, e.event_date
             FROM tickets t
             JOIN ticket_categories c ON t.category_id = c.id
             JOIN events e ON c.event_id = e.id
             JOIN orders o ON t.order_id = o.id
             JOIN users u ON o.user_id = u.id
             WHERE t.order_id = ?
             ORDER BY t.id"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function getByUser($userId) {
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.ticket_number, t.verification_code, c.name AS category,
                    t.status, u.full_name AS holder_name, e.title AS event_title, e.event_date,
                    t.order_id, t.issued_at
             FROM tickets t
             JOIN ticket_categories c ON t.category_id = c.id
             JOIN events e ON c.event_id = e.id
             JOIN orders o ON t.order_id = o.id
             JOIN users u ON o.user_id = u.id
             WHERE o.user_id = ? AND o.status = 'paid'
             ORDER BY t.id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}

// Finds a ticket by QR code or ticket number. Result: valid, used or invalid.
class TicketVerifier {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findTicket($value) {
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.ticket_number, t.status, t.used_at,
                    c.name AS category, u.full_name AS holder_name
             FROM tickets t
             JOIN ticket_categories c ON t.category_id = c.id
             JOIN orders o ON t.order_id = o.id
             JOIN users u ON o.user_id = u.id
             WHERE t.verification_code = :v1 OR t.ticket_number = :v2
             LIMIT 1"
        );
        // Same value twice: a named placeholder cannot repeat
        $stmt->execute([":v1" => $value, ":v2" => $value]);
        $ticket = $stmt->fetch();
        return $ticket ? $ticket : null;
    }

    public function verify($value, $officerId) {
        // One UPDATE: only an "issued" ticket becomes "used"
        $stmt = $this->pdo->prepare(
            "UPDATE tickets
             SET status = 'used', used_at = NOW(), verified_by = :officer
             WHERE (verification_code = :v1 OR ticket_number = :v2) AND status = 'issued'"
        );
        $stmt->execute([":officer" => $officerId, ":v1" => $value, ":v2" => $value]);

        $ticket = $this->findTicket($value);

        if ($stmt->rowCount() == 1) {
            return ["result" => "valid", "message" => "Valid ticket. Admit.", "ticket" => $ticket];
        }
        if ($ticket) {
            return ["result" => "used", "message" => "Already used", "ticket" => $ticket];
        }
        return ["result" => "invalid", "message" => "Ticket not found", "ticket" => null];
    }
}

// One row per scan, for all three results
class VerificationLog {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function record($ticketId, $inputCode, $result, $officerId) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO verification_log (ticket_id, input_code, result, officer_id)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$ticketId, $inputCode, $result, $officerId]);
    }
}

// Frozen signatures (SRS Addendum section 7)
function generate_ticket_number(PDO $pdo): string {
    $issuer = new TicketIssuer($pdo);
    return $issuer->makeTicketNumber();
}

function generate_verification_code(): string {
    return TicketIssuer::makeVerificationCode();
}

// Call only inside the caller's transaction
function issue_tickets(PDO $pdo, int $orderId): array {
    $issuer = new TicketIssuer($pdo);
    return $issuer->issueForOrder($orderId);
}
