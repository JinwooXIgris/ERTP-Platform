<?php
// POST { "code": "UIT26-7K4Q9" }  (a ticket number or the long verification code)
// Checks the ticket and marks it as used, in one step.
require_once "init.php";
requirePost();
requireOfficer();

$input = getInput();
$verifier = new TicketVerifier($conn);
$code = $verifier->cleanInput($input["code"] ?? ($input["ticket_number"] ?? ""));

if ($code == "") {
    respond(["success" => false, "status" => "invalid", "message" => "Enter or paste a ticket number."], 400);
}

$result = $verifier->verify($code, $_SESSION["officer_id"]);

// Save every scan in verification_log
$ticketId = isset($result["ticket"]) ? $result["ticket"]["id"] : null;
$log = new VerificationLog($conn);
$log->record($ticketId, $code, $result["status"], $_SESSION["officer_id"]);

respond($result);
