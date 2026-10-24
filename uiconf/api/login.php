<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data.'
    ]);

    exit;
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);

    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT id, full_name, email, phone, password_hash, attendee_type, role
     FROM users
     WHERE email = ?'
);

$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password.'
    ]);

    exit;
}

login_user($user);

echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'user' => current_user()
]);