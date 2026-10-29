<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

final class AdminUpdatePriceEndpoint
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(): void
    {
        require_method('POST');
        require_role('admin');

        $data = read_json();

        $categoryId = valid_int($data['category_id'] ?? null, 1, 999999);
        $price = valid_money($data['price'] ?? null, '0.00', '1000000.00');

        $errors = [];
        if ($categoryId === null) {
            $errors[] = ['field' => 'category_id', 'message' => 'Category ID must be a positive integer'];
        }
        if ($price === null) {
            $errors[] = ['field' => 'price', 'message' => 'Price must be a number between 0.00 and 1000000.00 with 2 decimals'];
        }

        if (!empty($errors)) {
            fail('Please fix the highlighted fields', 400, $errors);
        }

        $checkStmt = $this->pdo->prepare('SELECT id, name, price FROM ticket_categories WHERE id = ? AND event_id = 1');
        $checkStmt->execute([$categoryId]);
        $category = $checkStmt->fetch();

        if (!$category) {
            fail('Category not found', 404);
        }

        $updateStmt = $this->pdo->prepare('UPDATE ticket_categories SET price = ? WHERE id = ?');
        $updateStmt->execute([$price, $categoryId]);

        $updatedCategory = [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'price' => $price
        ];

        ok('', ['category' => $updatedCategory], 200);
    }
}

(new AdminUpdatePriceEndpoint(db()))->handle();
