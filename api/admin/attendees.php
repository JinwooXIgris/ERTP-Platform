<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

final class AdminAttendeesEndpoint
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(): void
    {
        require_method('GET');
        require_role('admin');

        $searchText = isset($_GET['q']) ? clean_string($_GET['q'], 0, 50) : null;
        $page = isset($_GET['page']) ? valid_int($_GET['page'], 1, 999999) : 1;

        if ($page === null) {
            fail('Invalid page number', 400, [['field' => 'page', 'message' => 'Page must be a positive integer']]);
        }

        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $query = 'SELECT t.ticket_number, u.full_name as holder_name, u.email, u.phone, 
                  tc.name as category, t.status, t.issued_at, t.used_at
                  FROM tickets t
                  JOIN orders o ON t.order_id = o.id
                  JOIN users u ON o.user_id = u.id
                  JOIN ticket_categories tc ON t.category_id = tc.id';

        if ($searchText) {
            $query .= ' WHERE (u.full_name LIKE ? OR u.email LIKE ? OR t.ticket_number LIKE ?)';
        }

        $query .= ' ORDER BY t.issued_at DESC LIMIT ? OFFSET ?';

        $stmt = $this->pdo->prepare($query);

        if ($searchText) {
            $likeText = "%{$searchText}%";
            $stmt->bindValue(1, $likeText, PDO::PARAM_STR);
            $stmt->bindValue(2, $likeText, PDO::PARAM_STR);
            $stmt->bindValue(3, $likeText, PDO::PARAM_STR);
            $stmt->bindValue(4, $perPage, PDO::PARAM_INT);
            $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        $attendees = $stmt->fetchAll();

        $countQuery = 'SELECT COUNT(*) as total FROM tickets t
                       JOIN orders o ON t.order_id = o.id
                       JOIN users u ON o.user_id = u.id';

        if ($searchText) {
            $countQuery .= ' WHERE (u.full_name LIKE ? OR u.email LIKE ? OR t.ticket_number LIKE ?)';
        }

        $countStmt = $this->pdo->prepare($countQuery);
        if ($searchText) {
            $countStmt->bindValue(1, $likeText, PDO::PARAM_STR);
            $countStmt->bindValue(2, $likeText, PDO::PARAM_STR);
            $countStmt->bindValue(3, $likeText, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        ok('', [
            'attendees' => $attendees,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total
        ], 200);
    }
}

(new AdminAttendeesEndpoint(db()))->handle();
