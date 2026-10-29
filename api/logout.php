<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

final class LogoutEndpoint {
    public function handle(): void {
        require_method('POST');

        logout_user();

        ok('Logout successful.', []);
    }
}

(new LogoutEndpoint())->handle();