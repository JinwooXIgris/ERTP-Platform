<?php

/**
 * =============================================================================
 * GET /api/admin/users.php?page=1
 * 
 * Purpose: Return paginated list of registered users
 * 
 * Auth: Admin only (require_role enforces this)
 * Method: GET only
 * Query params:
 *   page (optional, default 1): page number for pagination
 *   q (optional): search text (matches full name, email, or phone)
 * 
 * Response:
 *   HTTP 200 + users array:
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "users": [
 *         {
 *           "id": 12,
 *           "full_name": "Ada Obi",
 *           "email": "ada@example.com",
 *           "phone": "08012345678",
 *           "attendee_type": "student",
 *           "role": "attendee",
 *           "created_at": "2026-11-01 10:20:00"
 *         }
 *       ],
 *       "page": 1,
 *       "per_page": 20,
 *       "total": 12
 *     },
 *     "errors": []
 *   }
 * =============================================================================
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

require_method('GET');
require_role('admin');

$page = isset($_GET['page']) ? valid_int($_GET['page'], 1, 999999) : 1;
if ($page === null) {
    fail('Invalid page number', 400, [['field' => 'page', 'message' => 'Page must be a positive integer']]);
}

$search = isset($_GET['q']) ? clean_string($_GET['q'], 0, 50) : null;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $pdo = db();

    $query = 'SELECT id, full_name, email, phone, attendee_type, role, created_at
              FROM users';
    $params = [];

    if ($search) {
        $query .= ' WHERE (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $query .= ' ORDER BY id ASC LIMIT ? OFFSET ?';

    $stmt = $pdo->prepare($query);
    $idx = 1;
    foreach ($params as $param) {
        $stmt->bindValue($idx++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($idx++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rawUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $users = array_map(function($u) {
        return [
            'id' => (int)$u['id'],
            'full_name' => $u['full_name'],
            'email' => $u['email'],
            'phone' => $u['phone'],
            'attendee_type' => $u['attendee_type'],
            'role' => $u['role'],
            'created_at' => $u['created_at']
        ];
    }, $rawUsers);

    // Count total users
    $countQuery = 'SELECT COUNT(*) as total FROM users';
    if ($search) {
        $countQuery .= ' WHERE (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([$like, $like, $like]);
    } else {
        $countStmt = $pdo->query($countQuery);
    }
    $total = (int)$countStmt->fetchColumn();

} catch (Exception $e) {
    error_log("Admin users query failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

ok('', [
    'users' => $users,
    'page' => $page,
    'per_page' => $per_page,
    'total' => $total
], 200);
