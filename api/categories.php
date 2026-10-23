<?php

/**
 * =============================================================================
 * GET /api/categories.php
 * 
 * Purpose: Return active ticket categories with prices and remaining quota
 * 
 * Auth: None (public endpoint)
 * Method: GET only
 * 
 * Response:
 *   HTTP 200 + categories array:
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "categories": [
 *         {
 *           "id": 1,
 *           "name": "Student",
 *           "price": "2000.00",
 *           "quota": 200,
 *           "remaining": 173,
 *           "sold_out": false
 *         },
 *         {
 *           "id": 4,
 *           "name": "VIP",
 *           "price": "20000.00",
 *           "quota": 50,
 *           "remaining": 0,
 *           "sold_out": true
 *         },
 *         ...
 *       ]
 *     },
 *     "errors": []
 *   }
 * 
 * Design Notes:
 *   - Public endpoint; no authentication required
 *   - Only returns active categories (is_active = 1)
 *   - Calculates remaining tickets per category:
 *     remaining = quota - (issued tickets + used tickets)
 *   - Sets sold_out flag if remaining <= 0
 *   - Frontend displays categories and checks sold_out before allowing purchase
 *   - Prices sent as strings to avoid float precision loss in JSON
 *   - Event ID = 1 (only one event per deployment)
 * =============================================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

// 1. Check HTTP method (GET only)
require_method('GET');

// 2. Query active categories for the event
try {
    $stmt = db()->prepare(
        'SELECT id, name, price, quota FROM ticket_categories 
         WHERE event_id = 1 AND is_active = 1 
         ORDER BY price ASC'
    );
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Categories query failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

// 3. For each category, calculate remaining quota
$result = [];
foreach ($categories as $category) {
    // Count issued + used tickets for this category
    try {
        $countStmt = db()->prepare(
            'SELECT COUNT(*) as ticket_count FROM tickets 
             WHERE category_id = ? AND status IN ("issued", "used")'
        );
        $countStmt->execute([$category['id']]);
        $countRow = $countStmt->fetch();
        $ticketsSold = (int)$countRow['ticket_count'];
    } catch (Exception $e) {
        error_log("Ticket count for category {$category['id']} failed: " . $e->getMessage());
        fail('Something went wrong', 500);
    }

    // Calculate remaining
    $remaining = (int)$category['quota'] - $ticketsSold;
    $remaining = max(0, $remaining); // Don't go negative

    // Build category response
    $result[] = [
        'id' => (int)$category['id'],
        'name' => $category['name'],
        'price' => $category['price'],           // Already DECIMAL(10,2) from DB
        'quota' => (int)$category['quota'],
        'remaining' => $remaining,
        'sold_out' => $remaining <= 0
    ];
}

// 4. Return success with categories
ok('', ['categories' => $result], 200);

?>
