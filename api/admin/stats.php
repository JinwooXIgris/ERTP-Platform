<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

final class AdminStatsEndpoint
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(): void
    {
        require_method('GET');
        require_role('admin');

        $orderStmt = $this->pdo->query('SELECT COUNT(*) as count FROM orders WHERE status = "paid"');
        $ordersPaid = (int)$orderStmt->fetchColumn();

        $ticketsStmt = $this->pdo->query('SELECT COUNT(*) as count FROM tickets WHERE status IN ("issued", "used")');
        $ticketsSold = (int)$ticketsStmt->fetchColumn();

        $usedStmt = $this->pdo->query('SELECT COUNT(*) as count FROM tickets WHERE status = "used"');
        $ticketsUsed = (int)$usedStmt->fetchColumn();

        $revenueStmt = $this->pdo->query('SELECT SUM(amount) as total FROM payments WHERE status = "success"');
        $revenueVal = $revenueStmt->fetchColumn();
        $revenue = $revenueVal !== null ? (string)$revenueVal : '0.00';

        $totals = [
            'orders_paid' => $ordersPaid,
            'tickets_sold' => $ticketsSold,
            'tickets_used' => $ticketsUsed,
            'revenue' => $revenue
        ];

        $catStmt = $this->pdo->query(
            'SELECT id, name, price, quota FROM ticket_categories WHERE event_id = 1 AND is_active = 1 ORDER BY price ASC'
        );
        $categories = $catStmt->fetchAll();

        $perCategory = [];
        $soldStmt = $this->pdo->prepare(
            'SELECT COUNT(*) as count FROM tickets WHERE category_id = ? AND status IN ("issued", "used")'
        );
        $usedCatStmt = $this->pdo->prepare(
            'SELECT COUNT(*) as count FROM tickets WHERE category_id = ? AND status = "used"'
        );
        $revStmt = $this->pdo->prepare(
            'SELECT SUM(oi.line_total) as total 
             FROM order_items oi
             JOIN orders o ON oi.order_id = o.id
             WHERE oi.category_id = ? AND o.status = "paid"'
        );

        foreach ($categories as $cat) {
            $catId = (int)$cat['id'];

            $soldStmt->execute([$catId]);
            $sold = (int)$soldStmt->fetchColumn();

            $usedCatStmt->execute([$catId]);
            $used = (int)$usedCatStmt->fetchColumn();

            $revStmt->execute([$catId]);
            $catRevenueVal = $revStmt->fetchColumn();
            $catRevenue = $catRevenueVal !== null ? (string)$catRevenueVal : '0.00';

            $perCategory[] = [
                'category_id' => $catId,
                'name' => $cat['name'],
                'price' => $cat['price'],
                'quota' => (int)$cat['quota'],
                'sold' => $sold,
                'used' => $used,
                'revenue' => $catRevenue
            ];
        }

        ok('', [
            'totals' => $totals,
            'per_category' => $perCategory
        ], 200);
    }
}

(new AdminStatsEndpoint(db()))->handle();
