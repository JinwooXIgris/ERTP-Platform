<?php
// Gate officers are the only people allowed to verify tickets
class GateOfficer {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM gate_officers WHERE username = ?");
        $stmt->execute([$username]);
        $officer = $stmt->fetch();

        if ($officer && password_verify($password, $officer["password"])) {
            return [
                "success" => true,
                "officer_id" => $officer["id"],
                "username" => $officer["username"]
            ];
        }
        return ["success" => false, "message" => "Wrong username or password."];
    }
}
