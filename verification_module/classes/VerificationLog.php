<?php
// Keeps a record of every scan in the verification_log table
class VerificationLog {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function record($ticketId, $inputCode, $result, $officerId) {
        $stmt = $this->conn->prepare(
            "INSERT INTO verification_log (ticket_id, input_code, result, officer_id) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$ticketId, substr($inputCode, 0, 100), $result, $officerId]);
    }

    public function getRecent($limit) {
        $limit = (int)$limit;
        return $this->conn->query(
            "SELECT l.input_code, l.result, l.created_at, u.full_name AS officer
             FROM verification_log l
             LEFT JOIN users u ON l.officer_id = u.id
             ORDER BY l.id DESC
             LIMIT " . $limit
        )->fetchAll();
    }

    public function getStats() {
        $stats = ["valid" => 0, "used" => 0, "invalid" => 0];

        $rows = $this->conn->query(
            "SELECT result, COUNT(*) AS total FROM verification_log GROUP BY result"
        )->fetchAll();
        foreach ($rows as $row) {
            $stats[$row["result"]] = (int)$row["total"];
        }

        $stats["tickets_issued"] = (int)$this->conn->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
        $stats["tickets_used"] = (int)$this->conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'used'")->fetchColumn();
        return $stats;
    }
}
