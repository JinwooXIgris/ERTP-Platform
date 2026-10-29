<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

final class CategoriesEndpoint {
    public function __construct(private PDO $pdo) {}

    public function handle(): void {
        require_method('GET');

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, name, price, quota FROM ticket_categories 
                 WHERE event_id = 1 AND is_active = 1 
                 ORDER BY price ASC'
            );
            $stmt->execute();
            $categories = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Categories query failed: ' . $e->getMessage());
            fail('Something went wrong', 500);
        }

        $result = [];
        foreach ($categories as $category) {
            try {
                $countStmt = $this->pdo->prepare(
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

            $remaining = (int)$category['quota'] - $ticketsSold;
            $remaining = max(0, $remaining);

            $result[] = [
                'id'        => (int)$category['id'],
                'name'      => $category['name'],
                'price'     => $category['price'],
                'quota'     => (int)$category['quota'],
                'remaining' => $remaining,
                'sold_out'  => $remaining <= 0
            ];
        }

        ok('', ['categories' => $result], 200);
    }
}

(new CategoriesEndpoint(db()))->handle();

