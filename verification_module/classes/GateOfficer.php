<?php
// Gate officers are users whose role is 'verifier' (or 'admin')
class GateOfficer {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function login($email, $password) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE email = ? AND role IN ('verifier', 'admin')"
        );
        $stmt->execute([strtolower($email)]);
        $officer = $stmt->fetch();

        if ($officer && password_verify($password, $officer["password_hash"])) {
            return [
                "success" => true,
                "officer_id" => $officer["id"],
                "name" => $officer["full_name"]
            ];
        }
        return ["success" => false, "message" => "Wrong email or password, or this account is not a gate officer."];
    }
}
