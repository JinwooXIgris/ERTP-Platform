<?php
// Included at the top of every API file
date_default_timezone_set("Africa/Lagos");
session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/../classes/Database.php";
require_once __DIR__ . "/../classes/GateOfficer.php";
require_once __DIR__ . "/../classes/TicketVerifier.php";
require_once __DIR__ . "/../classes/VerificationLog.php";

$database = new Database();
$conn = $database->connect();

// Send JSON back and stop
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Read the JSON sent by fetch()
function getInput() {
    $data = json_decode(file_get_contents("php://input"), true);
    return $data ? $data : [];
}

function requirePost() {
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        respond(["success" => false, "message" => "Use POST for this endpoint."], 405);
    }
}

function requireOfficer() {
    if (!isset($_SESSION["officer_id"])) {
        respond(["success" => false, "status" => "login_needed", "message" => "Gate officer login required."], 401);
    }
}
