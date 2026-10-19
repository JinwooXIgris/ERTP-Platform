<?php
// Keeps a record of every ticket check at the gate
class VerificationLog {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function record($number, $result, $officerId) {
        $stmt = $this->conn->prepare(
            "INSERT INTO verification_log (ticket_number, result, officer_id) VALUES (?, ?, ?)"
        );
        $stmt->execute([substr($number, 0, 30), $result, $officerId]);
    }

    public function getRecent($limit) {
        $limit = (int)$limit;
        return $this->conn->query(
            "SELECT l.ticket_number, l.result, l.created_at, g.username
             FROM verification_log l
             LEFT JOIN gate_officers g ON l.officer_id = g.id
             ORDER BY l.id DESC
             LIMIT " . $limit
        )->fetchAll();
    }

    public function getStats() {
        $stats = ["valid" => 0, "used" => 0, "invalid" => 0, "checked_in" => 0];

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
