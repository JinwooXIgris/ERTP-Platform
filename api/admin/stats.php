<?php

/**
 * =============================================================================
 * GET /api/admin/stats.php
 * 
 * Purpose: Return sales and attendance statistics for admin dashboard
 * 
 * Auth: Admin only (require_role enforces this)
 * Method: GET only
 * 
 * Response:
 *   HTTP 200 + stats object:
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "totals": {
 *         "orders_paid": 42,
 *         "tickets_sold": 87,
 *         "tickets_used": 54,
 *         "revenue": "435500.00"
 *       },
 *       "per_category": [
 *         {
 *           "category_id": 1,
 *           "name": "Student",
 *           "price": "2000.00",
 *           "quota": 200,
 *           "sold": 45,
 *           "used": 30,
 *           "revenue": "90000.00"
 *         },
 *         ...
 *       ]
 *     },
 *     "errors": []
 *   }
 * 
 * Design Notes:
 *   - Protected: 401 if not logged in, 403 if not admin
 *   - All queries aggregate from live database
 *   - "Sold" = issued + used tickets (every paid order creates tickets)
 *   - "Used" = tickets scanned at gate (status = 'used')
 *   - "Revenue" = SUM of paid orders only (status = 'paid')
 *   - Works with empty database (returns zeros)
 *   - Fast: ~3-4 queries, all indexed on order.status and ticket.status
 * =============================================================================
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Check HTTP method (GET only)
require_method('GET');

// 2. Check auth: admin only
require_role('admin');

// 3. No input to validate

try {
    // Query 1: Overall totals
    // Orders: count paid orders
    $orderStmt = db()->prepare('SELECT COUNT(*) as count FROM orders WHERE status = "paid"');
    $orderStmt->execute();
    $orderRow = $orderStmt->fetch();
    $orders_paid = (int)$orderRow['count'];

    // Tickets: count issued + used (all paid orders create tickets)
    $ticketsStmt = db()->prepare('SELECT COUNT(*) as count FROM tickets WHERE status IN ("issued", "used")');
    $ticketsStmt->execute();
    $ticketsRow = $ticketsStmt->fetch();
    $tickets_sold = (int)$ticketsRow['count'];

    // Tickets used: count only "used" status
    $usedStmt = db()->prepare('SELECT COUNT(*) as count FROM tickets WHERE status = "used"');
    $usedStmt->execute();
    $usedRow = $usedStmt->fetch();
    $tickets_used = (int)$usedRow['count'];

    // Revenue: SUM of successful payments
    // (Could also use SUM(total_amount) FROM orders WHERE status = "paid", same result)
    $revenueStmt = db()->prepare(
        'SELECT SUM(amount) as total FROM payments WHERE status = "success"'
    );
    $revenueStmt->execute();
    $revenueRow = $revenueStmt->fetch();
    $revenue = $revenueRow['total'] ?? '0.00';

    $totals = [
        'orders_paid' => $orders_paid,
        'tickets_sold' => $tickets_sold,
        'tickets_used' => $tickets_used,
        'revenue' => $revenue
    ];

    // Query 2: Per-category breakdown
    // For each active category, get: quota, sold count, used count, revenue
    $catStmt = db()->prepare(
        'SELECT id, name, price, quota FROM ticket_categories WHERE event_id = 1 AND is_active = 1 ORDER BY price ASC'
    );
    $catStmt->execute();
    $categories = $catStmt->fetchAll();

    $per_category = [];
    foreach ($categories as $cat) {
        // Count sold tickets for this category (issued + used)
        $soldStmt = db()->prepare(
            'SELECT COUNT(*) as count FROM tickets WHERE category_id = ? AND status IN ("issued", "used")'
        );
        $soldStmt->execute([$cat['id']]);
        $soldRow = $soldStmt->fetch();
        $sold = (int)$soldRow['count'];

        // Count used tickets for this category
        $usedCatStmt = db()->prepare(
            'SELECT COUNT(*) as count FROM tickets WHERE category_id = ? AND status = "used"'
        );
        $usedCatStmt->execute([$cat['id']]);
        $usedCatRow = $usedCatStmt->fetch();
        $used = (int)$usedCatRow['count'];

        // Revenue for this category
        // Join: order_items.category_id -> payments.order_id (only successful payments)
        $revStmt = db()->prepare(
            'SELECT SUM(oi.line_total) as total 
             FROM order_items oi
             JOIN orders o ON oi.order_id = o.id
             WHERE oi.category_id = ? AND o.status = "paid"'
        );
        $revStmt->execute([$cat['id']]);
        $revRow = $revStmt->fetch();
        $cat_revenue = $revRow['total'] ?? '0.00';

        $per_category[] = [
            'category_id' => (int)$cat['id'],
            'name' => $cat['name'],
            'price' => $cat['price'],
            'quota' => (int)$cat['quota'],
            'sold' => $sold,
            'used' => $used,
            'revenue' => $cat_revenue
        ];
    }

} catch (Exception $e) {
    error_log("Admin stats query failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

// 4. Return success
ok('', [
    'totals' => $totals,
    'per_category' => $per_category
], 200);

?>
