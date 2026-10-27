<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

require_method('GET');

$user = current_user();

ok('Current user successfully retrieved.', ['user' => $user]);