<?php
header("Content-Type: application/json; charset=UTF-8");

// Session-Einstellungen härten
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================================================
// POST: Login
// ============================================================
if ($method === 'POST' && $action === 'login') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['benutzername']) || empty($data['passwort'])) {
        echo json_encode(["status" => "error", "message" => "Benutzername und Passwort erforderlich."]);
        exit;
    }

    // Brute-Force-Schutz: kurze Verzögerung
    sleep(1);

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE benutzername = :b AND aktiv = 1");
        $stmt->execute([':b' => $data['benutzername']]);
        $user = $stmt->fetch();

        if ($user && password_verify($data['passwort'], $user['passwort_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']      = (int)$user['id'];
            $_SESSION['benutzername'] = $user['benutzername'];
            $_SESSION['anzeigename']  = $user['anzeigename'];
            $_SESSION['ist_admin']    = (bool)$user['ist_admin'];

            echo json_encode([
                "status"      => "success",
                "anzeigename" => $user['anzeigename'],
                "ist_admin"   => (bool)$user['ist_admin']
            ]);
        } else {
            // Gleiche Fehlermeldung egal ob User nicht existiert oder Passwort falsch (kein User-Enumeration)
            echo json_encode(["status" => "error", "message" => "Benutzername oder Passwort falsch."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Login."]);
    }
    exit;
}

// ============================================================
// POST: Logout
// ============================================================
if ($method === 'POST' && $action === 'logout') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(["status" => "success"]);
    exit;
}

// ============================================================
// GET: Session prüfen
// ============================================================
if ($method === 'GET' && $action === 'check') {
    if (!empty($_SESSION['user_id'])) {
        echo json_encode([
            "status"      => "logged_in",
            "anzeigename" => $_SESSION['anzeigename'],
            "ist_admin"   => (bool)$_SESSION['ist_admin']
        ]);
    } else {
        echo json_encode(["status" => "logged_out"]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Ungültige Anfrage."]);
