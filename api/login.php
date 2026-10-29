<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

final class LoginEndpoint {
    public function __construct(private PDO $pdo) {}

    public function handle(): void {
        require_method('POST');

        $input = read_json();

        $email = valid_email($input['email'] ?? null);
        $password = $input['password'] ?? '';

        if (!$email || empty($password)) {
            fail('Email and password required', 400, [
                ['field' => 'email', 'message' => 'Valid email is required.'],
                ['field' => 'password', 'message' => 'Password is required.']
            ]);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, phone, password_hash, attendee_type, role
             FROM users
             WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            fail('Invalid email or password.', 401);
        }

        login_user($user);

        ok('Login successful.', ['user' => current_user()]);
    }
}

(new LoginEndpoint(db()))->handle();