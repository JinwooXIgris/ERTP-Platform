<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

final class AdminUsersEndpoint
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

        $stmt = $this->pdo->prepare($query);
        $idx = 1;
        foreach ($params as $param) {
            $stmt->bindValue($idx++, $param, PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rawUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $users = array_map(function ($u) {
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

        $countQuery = 'SELECT COUNT(*) as total FROM users';
        if ($search) {
            $countQuery .= ' WHERE (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $countStmt = $this->pdo->prepare($countQuery);
            $countStmt->execute([$like, $like, $like]);
        } else {
            $countStmt = $this->pdo->query($countQuery);
        }
        $total = (int)$countStmt->fetchColumn();

        ok('', [
            'users' => $users,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total
        ], 200);
    }
}

(new AdminUsersEndpoint(db()))->handle();
