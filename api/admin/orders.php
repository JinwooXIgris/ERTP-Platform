<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

final class AdminOrdersEndpoint
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(): void
    {
        require_method('GET');
        require_role('admin');

        $page = isset($_GET['page']) ? valid_int($_GET['page'], 1, 999999) : 1;
        if ($page === null) {
            fail('Invalid page number', 400, [['field' => 'page', 'message' => 'Page must be a positive integer']]);
        }

        $search = isset($_GET['q']) ? clean_string($_GET['q'], 0, 50) : null;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

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

        $stmt = $this->pdo->prepare($query);
        $idx = 1;
        foreach ($params as $param) {
            $stmt->bindValue($idx++, $param, PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countQuery = 'SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id';
        if ($search) {
            $countQuery .= ' WHERE (u.full_name LIKE ? OR u.email LIKE ? OR CAST(o.id AS CHAR) LIKE ?)';
            $countStmt = $this->pdo->prepare($countQuery);
            $countStmt->execute([$like, $like, $like]);
        } else {
            $countStmt = $this->pdo->query($countQuery);
        }
        $total = (int)$countStmt->fetchColumn();

        if (!empty($orders)) {
            $orderIds = array_column($orders, 'id');
            $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
            
            $payStmt = $this->pdo->prepare(
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

        ok('', [
            'orders' => $orders,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total
        ], 200);
    }
}

(new AdminOrdersEndpoint(db()))->handle();
