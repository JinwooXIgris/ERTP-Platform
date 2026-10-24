<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD']!=='POST'){
    http_response_code(405);
    echo json_encode([
        'success'=>false,
        'message'=>'Method not allowed.'
    ]);
    
    exit;
}
    $input=json_decode(file_get_contents('php://input'),true);
    if(!is_array($input)){
        http_response_code(400);
        echo json_encode(['success'=>false,
        'message'=>'Invalid request data.'
        ]);
       
    
     exit;
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
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Please fill in all required fields.'
    ]);

    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);

    exit;
}


$allowedTypes = ['student', 'staff', 'public', 'vip'];

if (!in_array($attendeeType, $allowedTypes, true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid attendee type.'
    ]);

    exit;
}


if (strlen($password) < 8) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters long.'
    ]);

    exit;
}



$pdo = db();

$stmt = $pdo->prepare(
    'SELECT id FROM users WHERE email = ?'
);

$stmt->execute([$email]);

if ($stmt->fetch()) {
    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' => 'This email address is already registered.'
    ]);

    exit;
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

http_response_code(201);

echo json_encode([
    'success' => true,
    'message' => 'Registration successful.'
]);



