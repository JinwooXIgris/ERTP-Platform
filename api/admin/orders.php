<?php

/**
 * =============================================================================
 * GET /api/admin/orders.php?page=1
 * 
 * Purpose: Return paginated list of orders with nested payment attempts
 * 
 * Auth: Admin only (require_role enforces this)
 * Method: GET only
 * Query params:
 *   page (optional, default 1): page number for pagination
 *   q (optional): search text (matches user name, email, or order id)
 * 
 * Response:
 *   HTTP 200 + orders array:
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "orders": [
 *         {
 *           "id": 45,
 *           "user_name": "Ada Obi",
 *           "email": "ada@example.com",
 *           "total_amount": "24000.00",
 *           "status": "paid",
 *           "created_at": "2026-11-02 14:05:31",
 *           "payments": [
 *             {
 *               "reference": "PAY-3F9A1C7E5B2D4A68",
 *               "status": "success",
 *               "amount": "24000.00",
 *               "created_at": "2026-11-02 14:06:12"
 *             }
 *           ]
 *         }
 *       ],
 *       "page": 1,
 *       "per_page": 20,
 *       "total": 45
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

    $query = 'SELECT o.id, u.full_name AS user_name, u.email, o.total_amount, o.status, o.created_at
              FROM orders o
              JOIN users u ON o.user_id = u.id';
    $params = [];

    if ($search) {
        $query .= ' WHERE (u.full_name LIKE ? OR u.email LIKE ? OR CAST(o.id AS CHAR) LIKE ?)';
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $query .= ' ORDER BY o.id DESC LIMIT ? OFFSET ?';

    $stmt = $pdo->prepare($query);
    $idx = 1;
    foreach ($params as $param) {
        $stmt->bindValue($idx++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($idx++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count total orders
    $countQuery = 'SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id';
    if ($search) {
        $countQuery .= ' WHERE (u.full_name LIKE ? OR u.email LIKE ? OR CAST(o.id AS CHAR) LIKE ?)';
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([$like, $like, $like]);
    } else {
        $countStmt = $pdo->query($countQuery);
    }
    $total = (int)$countStmt->fetchColumn();

    // Fetch nested payments for the retrieved orders
    if (!empty($orders)) {
        $orderIds = array_column($orders, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        $payStmt = $pdo->prepare(
            "SELECT order_id, reference, status, amount, created_at
             FROM payments
             WHERE order_id IN ($inPlaceholders)
             ORDER BY id ASC"
        );
        $payStmt->execute($orderIds);
        $allPayments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

        $paymentsByOrder = [];
        foreach ($allPayments as $p) {
            $orderId = (int)$p['order_id'];
            if (!isset($paymentsByOrder[$orderId])) {
                $paymentsByOrder[$orderId] = [];
            }
            $paymentsByOrder[$orderId][] = [
                'reference' => $p['reference'],
                'status' => $p['status'],
                'amount' => $p['amount'],
                'created_at' => $p['created_at']
            ];
        }

        foreach ($orders as &$order) {
            $order['id'] = (int)$order['id'];
            $orderId = $order['id'];
            $order['payments'] = $paymentsByOrder[$orderId] ?? [];
        }
        unset($order);
    }

} catch (Exception $e) {
    error_log("Admin orders query failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

ok('', [
    'orders' => $orders,
    'page' => $page,
    'per_page' => $per_page,
    'total' => $total
], 200);
