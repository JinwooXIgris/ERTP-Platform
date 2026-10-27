<?php

/**
 * =============================================================================
 * POST /api/admin/update-price.php
 * 
 * Purpose: Change the price of a ticket category
 * 
 * Auth: Admin only (require_role enforces this)
 * Method: POST only
 * 
 * Request body:
 *   {
 *     "category_id": 1,
 *     "price": "2500.00"
 *   }
 * 
 * Response (HTTP 200):
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "category": {
 *         "id": 1,
 *         "name": "Student",
 *         "price": "2500.00"
 *       }
 *     },
 *     "errors": []
 *   }
 * 
 * Error cases:
 *   HTTP 400: Invalid price format or out of range
 *   HTTP 401: Not logged in
 *   HTTP 403: Not admin
 *   HTTP 404: Category not found
 *   HTTP 500: Database error
 * 
 * Design Notes:
 *   - Protected: admin only
 *   - Price is DECIMAL(10,2), range 0.00 to 1000000.00
 *   - Updates ticket_categories.price only
 *   - Does NOT affect old orders (they have unit_price snapshot in order_items)
 *   - Returns updated category details
 *   - Validates category exists before updating
 * =============================================================================
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Check HTTP method (POST only)
require_method('POST');

// 2. Check auth: admin only
require_role('admin');

// 3. Read and validate input
$data = read_json();

$category_id = valid_int($data['category_id'] ?? null, 1, 999999);
$price = valid_money($data['price'] ?? null, '0.00', '1000000.00');

// Build error list
$errors = [];
if ($category_id === null) {
    $errors[] = ['field' => 'category_id', 'message' => 'Category ID must be a positive integer'];
}
if ($price === null) {
    $errors[] = ['field' => 'price', 'message' => 'Price must be a number between 0.00 and 1000000.00 with 2 decimals'];
}

if (!empty($errors)) {
    fail('Please fix the highlighted fields', 400, $errors);
}

try {
    // 4. Check if category exists
    $checkStmt = db()->prepare('SELECT id, name, price FROM ticket_categories WHERE id = ? AND event_id = 1');
    $checkStmt->execute([$category_id]);
    $category = $checkStmt->fetch();

    if (!$category) {
        fail('Category not found', 404);
    }

    // 5. Update the price
    $updateStmt = db()->prepare('UPDATE ticket_categories SET price = ? WHERE id = ?');
    $updateStmt->execute([$price, $category_id]);

    // 6. Return the updated category
    $updatedCategory = [
        'id' => (int)$category['id'],
        'name' => $category['name'],
        'price' => $price  // Return the new price
    ];

} catch (Exception $e) {
    error_log("Admin update-price failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

// 7. Return success
ok('', ['category' => $updatedCategory], 200);

?>
