<?php
// GET ?order_id=  owner only  ->  tickets of one paid order

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ticketing.php';

require_method('GET');
$user = require_login();

$orderId = valid_int($_GET['order_id'] ?? null, 1, 2147483647);
if ($orderId === null) {
    fail('Please fix the highlighted fields', 400, [
        ['field' => 'order_id', 'message' => 'Enter a valid order number']
    ]);
}

try {
    $reader = new TicketReader(db());

    if (!$reader->orderIsPaidAndOwned($orderId, $user['id'])) {
        fail('Order not found', 404);
    }
    $tickets = $reader->getByOrder($orderId);
} catch (Exception $e) {
    error_log($e->getMessage());
    fail('Something went wrong', 500);
}

ok('Tickets loaded', ['tickets' => $tickets]);
