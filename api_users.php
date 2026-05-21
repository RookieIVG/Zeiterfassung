<?php
header("Content-Type: application/json; charset=UTF-8");

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
require_once 'config.php';

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

$current_user_id = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ============================================================
// GET: Alle User laden
// ============================================================
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, benutzername, anzeigename, email, ist_admin, aktiv, erstellt_am FROM users ORDER BY anzeigename");
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Laden."]);
    }
    exit;
}

// ============================================================
// POST: Neuen User anlegen
// ============================================================
if ($method === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['benutzername']) || empty($data['passwort']) || empty($data['anzeigename'])) {
        echo json_encode(["status" => "error", "message" => "Benutzername, Anzeigename und Passwort erforderlich."]);
        exit;
    }

    if (strlen($data['passwort']) < 8) {
        echo json_encode(["status" => "error", "message" => "Passwort muss mindestens 8 Zeichen lang sein."]);
        exit;
    }

    try {
        $hash = password_hash($data['passwort'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (benutzername, passwort_hash, anzeigename, email, ist_admin) VALUES (:b, :h, :a, :email, :admin)");
        $stmt->execute([
            ':b'     => htmlspecialchars(trim($data['benutzername'])),
            ':h'     => $hash,
            ':a'     => htmlspecialchars(trim($data['anzeigename'])),
            ':email' => !empty($data['email']) ? htmlspecialchars(trim($data['email'])) : null,
            ':admin' => !empty($data['ist_admin']) ? 1 : 0
        ]);
        echo json_encode(["status" => "success", "message" => "User erfolgreich angelegt."]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(["status" => "error", "message" => "Benutzername bereits vergeben."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Fehler beim Anlegen."]);
        }
    }
    exit;
}

// ============================================================
// PUT: User bearbeiten
// ============================================================
if ($method === 'PUT') {
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Keine ID angegeben."]);
        exit;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Keine Daten empfangen."]);
        exit;
    }

    try {
        if (!empty($data['passwort'])) {
            if (strlen($data['passwort']) < 8) {
                echo json_encode(["status" => "error", "message" => "Passwort muss mindestens 8 Zeichen lang sein."]);
                exit;
            }
            $hash = password_hash($data['passwort'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET benutzername=:b, anzeigename=:a, email=:email, ist_admin=:admin, aktiv=:aktiv, passwort_hash=:h WHERE id=:id");
            $stmt->execute([
                ':b'     => htmlspecialchars(trim($data['benutzername'])),
                ':a'     => htmlspecialchars(trim($data['anzeigename'])),
                ':email' => !empty($data['email']) ? htmlspecialchars(trim($data['email'])) : null,
                ':admin' => !empty($data['ist_admin']) ? 1 : 0,
                ':aktiv' => !empty($data['aktiv']) ? 1 : 0,
                ':h'     => $hash,
                ':id'    => $id
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET benutzername=:b, anzeigename=:a, email=:email, ist_admin=:admin, aktiv=:aktiv WHERE id=:id");
            $stmt->execute([
                ':b'     => htmlspecialchars(trim($data['benutzername'] ?? '')),
                ':a'     => htmlspecialchars(trim($data['anzeigename'] ?? '')),
                ':email' => !empty($data['email']) ? htmlspecialchars(trim($data['email'])) : null,
                ':admin' => !empty($data['ist_admin']) ? 1 : 0,
                ':aktiv' => !empty($data['aktiv']) ? 1 : 0,
                ':id'    => $id
            ]);
        }
        echo json_encode(["status" => "success", "message" => "User aktualisiert."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Aktualisieren."]);
    }
    exit;
}

// ============================================================
// DELETE: User deaktivieren oder löschen
// ============================================================
if ($method === 'DELETE') {
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Keine ID angegeben."]);
        exit;
    }

    if ($id === $current_user_id) {
        echo json_encode(["status" => "error", "message" => "Du kannst deinen eigenen Account nicht deaktivieren oder löschen."]);
        exit;
    }

    $action = $_GET['action'] ?? 'deactivate';

    // Echtes Löschen – nur wenn keine Buchungen vorhanden
    if ($action === 'delete') {
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM zeiterfassung WHERE user_id = :id");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(["status" => "error", "message" => "User kann nicht gelöscht werden: Es sind bereits Buchungen vorhanden."]);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(["status" => "success", "message" => "User gelöscht."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Fehler beim Löschen."]);
        }
        exit;
    }

    // Deaktivieren
    try {
        $stmt = $pdo->prepare("UPDATE users SET aktiv = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(["status" => "success", "message" => "User deaktiviert."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Deaktivieren."]);
    }
    exit;
}
