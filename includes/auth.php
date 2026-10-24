<?php
require_once __DIR__ . '/db.php';

if(session_status()===PHP_SESSION_NONE){
    session_start();

// Configure session cookies for security
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 86400,        // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => false,          // Set to true in production (HTTPS)
        'httponly' => true,         // Prevent JavaScript access (XSS protection)
        'samesite' => 'Lax',        // CSRF protection
    ]);
}

function current_user(){
    return $_SESSION['user']??null;

}

function require_login(){
    if (current_user()===null){
        http_response_code(401);
        exit('Authentication required.');
    }
}

function require_role(...$roles){
    require_login();
    $user=current_user();

    if(!in_array($user['role'] ?? null,$roles,true)){
        http_response_code(403);
        exit('You do not have permission to this resource.');
    }
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
