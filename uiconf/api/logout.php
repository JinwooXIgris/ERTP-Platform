<?php

require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   fail('Method not allowed.',405);
}

logout_user();
ok('Logout successful.',[]);