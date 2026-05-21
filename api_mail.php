<?php
header("Content-Type: application/json; charset=UTF-8");

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

// Nur eingeloggte Admins
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Nicht eingeloggt."]);
    exit;
}
if (empty($_SESSION['ist_admin'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Keine Berechtigung."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Ungültige Anfrage."]);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['email']) || empty($data['benutzername']) || empty($data['passwort'])) {
    echo json_encode(["status" => "error", "message" => "E-Mail, Benutzername und Passwort erforderlich."]);
    exit;
}

$empfaenger  = $data['email'];
$anzeigename = $data['anzeigename'] ?? $data['benutzername'];
$benutzername = $data['benutzername'];
$passwort    = $data['passwort'];

// Absender – bitte anpassen!
$absender_name  = "Zeiterfassung";
$absender_email = "noreply@" . ($_SERVER['HTTP_HOST'] ?? 'zeiterfassung.local');

$betreff = "Deine Zugangsdaten für die Zeiterfassung";

$nachricht = "Hallo {$anzeigename},\n\n";
$nachricht .= "Dein Account für die Zeiterfassung wurde angelegt.\n\n";
$nachricht .= "Deine Zugangsdaten:\n";
$nachricht .= "  Benutzername: {$benutzername}\n";
$nachricht .= "  Passwort:     {$passwort}\n\n";
$nachricht .= "Bitte ändere dein Passwort nach dem ersten Login.\n\n";
$nachricht .= "Mit freundlichen Grüßen\n";
$nachricht .= "Zeiterfassung\n";

$headers  = "From: {$absender_name} <{$absender_email}>\r\n";
$headers .= "Reply-To: {$absender_email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

if (mail($empfaenger, $betreff, $nachricht, $headers)) {
    echo json_encode(["status" => "success", "message" => "E-Mail erfolgreich gesendet."]);
} else {
    echo json_encode(["status" => "error", "message" => "E-Mail konnte nicht gesendet werden. Bitte Serverkonfiguration prüfen."]);
}
