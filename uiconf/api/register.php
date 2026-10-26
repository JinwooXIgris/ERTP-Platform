<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/response.php';

require_once __DIR__ . '/../includes/validate.php';


if($_SERVER['REQUEST_METHOD']!=='POST'){
     fail('Method not allowed.',405);
}
    $input=json_decode(file_get_contents('php://input'),true);
    if(!is_array($input)){
        fail('Invalid request data.',400);
    }

$fullName = trim($input['full_name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';
$attendeeType = $input['attendee_type'] ?? 'public';


if (
    $fullName === '' ||
    $email === '' ||
    $phone === '' ||
    $password === ''
) {
    fail('Please fill in all required fields.',400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
     fail('Please enter a valid email address',422);
}


$allowedTypes = ['student', 'staff', 'public', 'vip'];

if (!in_array($attendeeType, $allowedTypes, true)) {
    fail('Invalid attendee type.',422);
}


if (strlen($password) < 8) {
    fail('Password must be at least eight characters.',422);
}



$pdo = db();

$stmt = $pdo->prepare(
    'SELECT id FROM users WHERE email = ?'
);

$stmt->execute([$email]);

if ($stmt->fetch()) {
   fail('This email is already registered.', 409);
}


$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO users
    (full_name, email, phone, password_hash, attendee_type, role)
    VALUES (?, ?, ?, ?, ?, ?)'
);

$stmt->execute([
    $fullName,
    $email,
    $phone,
    $passwordHash,
    $attendeeType,
    'attendee'
]);

ok('Registration succesful.', [],201);


