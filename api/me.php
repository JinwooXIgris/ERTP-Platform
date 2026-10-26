<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

ok('Current user successfully retreived.',['user =>current_user()']);