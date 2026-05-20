<?php
// config.example.php
// Dies ist eine Vorlage. Kopiere diese Datei als "config.php"
// und trage deine echten Zugangsdaten ein.
// Die config.php selbst darf NIEMALS ins Repository!

$host     = "dein-datenbankserver";   // z.B. localhost oder mysqlsvr88.example.com
$db_name  = "dein_datenbankname";
$username = "dein_datenbank_user";
$password = "dein_datenbank_passwort";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Datenbankverbindung fehlgeschlagen."]);
    exit;
}
