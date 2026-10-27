<?php
require_once __DIR__ . '/config.php';

date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'Africa/Lagos');
function db(){
    static $pdo=null;
if ($pdo===null){
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbname = defined('DB_NAME') ? DB_NAME : 'uiconf';
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';

$dsn="mysql:host=$host;dbname=$dbname;charset=utf8mb4";
try{
    $pdo=new PDO($dsn, $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set MySQL timezone to Africa/Lagos (UTC+1)
    $pdo->exec("SET time_zone = '+01:00'");
    
    // Disable emulation of prepared statements for security
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}
catch(PDOException $e){
    error_log("Database connection failed: ".$e->getMessage());
    http_response_code(500);
    exit("A database error occurred.");

}
}
return $pdo;
}
