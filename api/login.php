<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed.',405);

}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    fail('Invalid request data.',400);
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
   fail('Email and password required',400);
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
   fail('Invalid email or password.',401);
}

login_user($user);

ok('Login succesful.',['user =>4user']);