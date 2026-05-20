<?php
// config.php
// WICHTIG: Diese Datei niemals ins Repository committen!
// Sie ist in .gitignore eingetragen.

$host     = "mysqlsvr88.world4you.com";
$db_name  = "7850162db1";
$username = "sql1477474";
$password = "i@eb4+3c";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Datenbankverbindung fehlgeschlagen."]);
    exit;
}
