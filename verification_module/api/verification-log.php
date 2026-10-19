<?php
// Gate summary numbers and the 10 most recent checks
require_once "init.php";
requireOfficer();

$log = new VerificationLog($conn);
respond([
    "success" => true,
    "stats" => $log->getStats(),
    "recent" => $log->getRecent(10)
]);
