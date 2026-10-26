<?php

/**
 * =============================================================================
 * GET /api/admin/attendees.php?q=search&page=1
 * 
 * Purpose: Return paginated list of attendees (one row per ticket) with search
 * 
 * Auth: Admin only (require_role enforces this)
 * Method: GET only
 * Query params:
 *   q (optional): search text; matches name, email, or ticket number (LIKE)
 *   page (optional, default 1): page number for pagination
 * 
 * Response:
 *   HTTP 200 + attendees array:
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "attendees": [
 *         {
 *           "ticket_number": "UIT26-7K4Q9",
 *           "holder_name": "Ada Obi",
 *           "email": "ada@example.com",
 *           "phone": "08012345678",
 *           "category": "Student",
 *           "status": "issued",
 *           "issued_at": "2026-11-02 14:05:31",
 *           "used_at": null
 *         },
 *         ...
 *       ],
 *       "page": 1,
 *       "per_page": 20,
 *       "total": 87
 *     },
 *     "errors": []
 *   }
 * 
 * Design Notes:
 *   - Protected: 401 if not logged in, 403 if not admin
 *   - One row per ticket (not per user)
 *   - Search uses LIKE with wildcards on name, email, ticket_number
 *   - Prepared statement with bound parameters prevents SQL injection
 *   - Pagination: fixed per_page = 20, page >= 1
 *   - Sorted by issued_at DESC (newest first)
 *   - Works with empty database (returns empty array)
 * =============================================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth.php';

// 1. Check HTTP method (GET only)
require_method('GET');

// 2. Check auth: admin only
require_role('admin');

// 3. Parse and validate input
$search_text = isset($_GET['q']) ? clean_string($_GET['q'], 0, 50) : null;
$page = isset($_GET['page']) ? valid_int($_GET['page'], 1, 999999) : 1;

// If validation failed, return error
if ($page === null) {
    fail('Invalid page number', 400, [['field' => 'page', 'message' => 'Page must be a positive integer']]);
}

$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    // Build query: base SELECT
    $query = 'SELECT t.ticket_number, u.full_name as holder_name, u.email, u.phone, 
              tc.name as category, t.status, t.issued_at, t.used_at
              FROM tickets t
              JOIN orders o ON t.order_id = o.id
              JOIN users u ON o.user_id = u.id
              JOIN ticket_categories tc ON t.category_id = tc.id';

    // Add WHERE clause if search text provided
    if ($search_text) {
        $query .= ' WHERE (u.full_name LIKE ? OR u.email LIKE ? OR t.ticket_number LIKE ?)';
    }

    // Add ORDER BY and LIMIT/OFFSET
    $query .= ' ORDER BY t.issued_at DESC LIMIT ? OFFSET ?';

    // Prepare and execute
    $stmt = db()->prepare($query);
    
    // Bind parameters
    if ($search_text) {
        $like_text = "%{$search_text}%";
        $stmt->bindValue(1, $like_text, PDO::PARAM_STR);
        $stmt->bindValue(2, $like_text, PDO::PARAM_STR);
        $stmt->bindValue(3, $like_text, PDO::PARAM_STR);
        $stmt->bindValue(4, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    $attendees = $stmt->fetchAll();

    // Get total count (for pagination)
    $countQuery = 'SELECT COUNT(*) as total FROM tickets t
                   JOIN orders o ON t.order_id = o.id
                   JOIN users u ON o.user_id = u.id';
    
    if ($search_text) {
        $countQuery .= ' WHERE (u.full_name LIKE ? OR u.email LIKE ? OR t.ticket_number LIKE ?)';
    }

    $countStmt = db()->prepare($countQuery);
    if ($search_text) {
        $countStmt->bindValue(1, $like_text, PDO::PARAM_STR);
        $countStmt->bindValue(2, $like_text, PDO::PARAM_STR);
        $countStmt->bindValue(3, $like_text, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $countRow = $countStmt->fetch();
    $total = (int)$countRow['total'];

} catch (Exception $e) {
    error_log("Admin attendees query failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

// 4. Return success with pagination info
ok('', [
    'attendees' => $attendees,
    'page' => $page,
    'per_page' => $per_page,
    'total' => $total
], 200);

?>
