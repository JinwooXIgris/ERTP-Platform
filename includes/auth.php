<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookies for security before session_start
    session_set_cookie_params([
        'lifetime' => 86400,        // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => false,          // Set to true in production (HTTPS)
        'httponly' => true,         // Prevent JavaScript access (XSS protection)
        'samesite' => 'Lax',        // CSRF protection
    ]);
    session_start();
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): array {
    $user = current_user();
    if ($user === null) {
        fail('Authentication required.', 401);
    }
    return $user;
}

function require_role(...$roles): array {
    $user = require_login();

    if (!in_array($user['role'] ?? null, $roles, true)) {
        fail('You do not have permission to access this resource.', 403);
    }
    return $user;
}

function login_user($userRow){
    session_regenerate_id(true);

    unset($userRow['password_hash']);
    $_SESSION['user']=$userRow;
}

function logout_user(){
    $_SESSION=[];

    if(ini_get('session.use_cookies')){
        $params=session_get_cookie_params();

        setcookie(
            session_name(),'',
            time()-42000,
            $params['path'],
             $params['domain'],
              $params['secure'],
               $params['httponly'],

        );
    }
    session_destroy();
}
