<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

final class Authenticator {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public function currentUser(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public function requireLogin(): array {
        $user = $this->currentUser();
        if ($user === null) {
            fail('Authentication required.', 401);
        }
        return $user;
    }

    public function requireRole(string ...$roles): array {
        $user = $this->requireLogin();
        if (!in_array($user['role'] ?? null, $roles, true)) {
            fail('You do not have permission to access this resource.', 403);
        }
        return $user;
    }

    public function login(array $userRow): void {
        session_regenerate_id(true);
        unset($userRow['password_hash']);
        $_SESSION['user'] = $userRow;
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}

function current_user(): ?array {
    return (new Authenticator())->currentUser();
}

function require_login(): array {
    return (new Authenticator())->requireLogin();
}

function require_role(string ...$roles): array {
    return (new Authenticator())->requireRole(...$roles);
}

function login_user(array $userRow): void {
    (new Authenticator())->login($userRow);
}

function logout_user(): void {
    (new Authenticator())->logout();
}

