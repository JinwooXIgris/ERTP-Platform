<?php
// POST {"value": "..."}  verifier or admin  ->  data.result: valid | used | invalid

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ticketing.php';

require_method('POST');

$officer = require_role('verifier', 'admin');

$data = read_json();
$value = clean_string($data['value'] ?? null, 1, 100);

if ($value === null) {
    fail('Please fix the highlighted fields', 400, [
        ['field' => 'value', 'message' => 'Enter a ticket number or code (1 to 100 characters)']
    ]);
}

try {
    $pdo = db();

    $verifier = new TicketVerifier($pdo);
    $outcome = $verifier->verify($value, $officer['id']);

    $ticketId = $outcome['ticket'] ? $outcome['ticket']['id'] : null;
    $log = new VerificationLog($pdo);
    $log->record($ticketId, $value, $outcome['result'], $officer['id']);
} catch (Exception $e) {
    error_log($e->getMessage());
    fail('Something went wrong', 500);
}

// Only the fields the contract lists
$ticket = null;
if ($outcome['ticket']) {
    $ticket = [
        'ticket_number' => $outcome['ticket']['ticket_number'],
        'category'      => $outcome['ticket']['category'],
        'holder_name'   => $outcome['ticket']['holder_name'],
        'used_at'       => $outcome['ticket']['used_at']
    ];
}

ok($outcome['message'], ['result' => $outcome['result'], 'ticket' => $ticket]);
