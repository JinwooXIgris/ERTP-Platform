<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

require_method('POST');

logout_user();
ok('Logout successful.', []);