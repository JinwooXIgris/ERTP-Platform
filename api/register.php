<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

require_method('POST');

$input = read_json();

$fullName = clean_string($input['full_name'] ?? $input['name'] ?? null, 2, 100);
$email = valid_email($input['email'] ?? null);
$phone = clean_string($input['phone'] ?? null, 7, 20);
$password = $input['password'] ?? '';
$attendeeType = $input['attendee_type'] ?? 'public';

$errors = [];

if (!$fullName) {
    $errors[] = ['field' => 'full_name', 'message' => 'Full name must be between 2 and 100 characters.'];
}

if (!$email) {
    $errors[] = ['field' => 'email', 'message' => 'Please enter a valid email address.'];
}

if (!$phone) {
    $errors[] = ['field' => 'phone', 'message' => 'Please enter a valid phone number.'];
}

if (!is_string($password) || strlen($password) < 8) {
    $errors[] = ['field' => 'password', 'message' => 'Password must be at least 8 characters.'];
}

$allowedTypes = ['student', 'staff', 'public', 'vip'];
if (!in_array($attendeeType, $allowedTypes, true)) {
    $errors[] = ['field' => 'attendee_type', 'message' => 'Invalid attendee type.'];
}

if (!empty($errors)) {
    fail('Please fix the highlighted fields.', 400, $errors);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    fail('This email is already registered.', 409, [
        ['field' => 'email', 'message' => 'This email is already registered. Please log in or use another email.']
    ]);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, email, phone, password_hash, attendee_type, role)
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

$userId = (int)$pdo->lastInsertId();

ok('Registration successful.', ['user_id' => $userId], 201);
