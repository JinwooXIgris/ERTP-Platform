<?php
// POST            -> login
// GET ?action=me     -> is an officer already logged in?
// GET ?action=logout -> log out
require_once "init.php";

$action = $_GET["action"] ?? "login";

if ($action == "logout") {
    unset($_SESSION["officer_id"]);
    unset($_SESSION["officer_name"]);
    respond(["success" => true]);
}

if ($action == "me") {
    if (isset($_SESSION["officer_id"])) {
        respond(["success" => true, "username" => $_SESSION["officer_name"]]);
    }
    respond(["success" => false]);
}

requirePost();
$input = getInput();
$officer = new GateOfficer($conn);
$result = $officer->login(trim($input["username"] ?? ""), $input["password"] ?? "");

if ($result["success"]) {
    $_SESSION["officer_id"] = $result["officer_id"];
    $_SESSION["officer_name"] = $result["username"];
}
respond($result);
