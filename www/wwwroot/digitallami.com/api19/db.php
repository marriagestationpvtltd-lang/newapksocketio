<?php

require_once __DIR__ . '/../config/db.php';
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db = DB_NAME;
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode([
        "status" => false,
        "message" => "Database connection failed"
    ]));
}

$conn->set_charset("utf8mb4");
