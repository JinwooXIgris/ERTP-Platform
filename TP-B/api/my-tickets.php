<?php
// GET  logged-in user  ->  all own tickets, newest first

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ticketing.php';

require_method('GET');
$user = require_login();

try {
    $reader = new TicketReader(db());
    $tickets = $reader->getByUser($user['id']);
} catch (Exception $e) {
    error_log($e->getMessage());
    fail('Something went wrong', 500);
}

ok('Tickets loaded', ['tickets' => $tickets]);
