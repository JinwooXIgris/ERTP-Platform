<?php
// POST { "ticket_number": "...", "check_in": false }  -> only check the ticket
// POST { "ticket_number": "...", "check_in": true }   -> check it AND mark it as used
require_once "init.php";
requirePost();
requireOfficer();

$input = getInput();
$verifier = new TicketVerifier($conn);
$number = $verifier->cleanNumber($input["ticket_number"] ?? "");

if ($number == "") {
    respond(["success" => false, "status" => "invalid", "message" => "Enter or paste a ticket number."], 400);
}

if (!empty($input["check_in"])) {
    $result = $verifier->checkIn($number);
} else {
    $result = $verifier->verify($number);
}

// Save every check in the log
$log = new VerificationLog($conn);
$log->record($number, $result["status"], $_SESSION["officer_id"]);

respond($result);
