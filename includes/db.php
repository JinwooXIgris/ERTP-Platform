<?php

date_default_timezone_set('Africa/Lagos');
function db(){
    static $pdo=null;
if ($pdo===null){
$host='127.0.0.1';
$dbname='uiconf';
$username='root';
$password='';

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
