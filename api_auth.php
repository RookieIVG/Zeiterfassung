<?php
header("Content-Type: application/json; charset=UTF-8");

// Session-Einstellungen härten
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 1800);

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
        // Abgelaufene Remember Tokens global bereinigen (1% Chance um Performance zu schonen)
        if (rand(1, 100) === 1) {
            $pdo->query("DELETE FROM remember_tokens WHERE ablauf_am < NOW()");
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE benutzername = :b AND aktiv = 1");
        $stmt->execute([':b' => $data['benutzername']]);
        $user = $stmt->fetch();

        if ($user && password_verify($data['passwort'], $user['passwort_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']            = (int)$user['id'];
            $_SESSION['benutzername']       = $user['benutzername'];
            $_SESSION['anzeigename']        = $user['anzeigename'];
            $_SESSION['ist_admin']          = (bool)$user['ist_admin'];
            $_SESSION['LAST_ACTIVITY']      = time();

            // "Angemeldet bleiben": Token in DB speichern + Cookie setzen
            $angemeldet_bleiben = !empty($data['angemeldet_bleiben']);
            if ($angemeldet_bleiben) {
                $token    = bin2hex(random_bytes(32));
                $ablauf   = date('Y-m-d H:i:s', strtotime('+30 days'));
                $stmt2    = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, ablauf_am) VALUES (:uid, :token, :ablauf)");
                $stmt2->execute([':uid' => $user['id'], ':token' => $token, ':ablauf' => $ablauf]);
                setcookie('remember_token', $token, [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                $_SESSION['angemeldet_bleiben'] = true;
            }

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
    // Remember Token löschen
    if (!empty($_COOKIE['remember_token'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = :token");
            $stmt->execute([':token' => $_COOKIE['remember_token']]);
        } catch (PDOException $e) {}
        setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
    }
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
        exit;
    }

    // Kein Session – Remember Token prüfen
    if (!empty($_COOKIE['remember_token'])) {
        try {
            $stmt = $pdo->prepare("SELECT rt.user_id, u.benutzername, u.anzeigename, u.ist_admin
                                   FROM remember_tokens rt
                                   JOIN users u ON rt.user_id = u.id
                                   WHERE rt.token = :token AND rt.ablauf_am > NOW() AND u.aktiv = 1");
            $stmt->execute([':token' => $_COOKIE['remember_token']]);
            $row = $stmt->fetch();
            if ($row) {
                session_regenerate_id(true);
                $_SESSION['user_id']            = (int)$row['user_id'];
                $_SESSION['benutzername']       = $row['benutzername'];
                $_SESSION['anzeigename']        = $row['anzeigename'];
                $_SESSION['ist_admin']          = (bool)$row['ist_admin'];
                $_SESSION['angemeldet_bleiben'] = true;
                $_SESSION['LAST_ACTIVITY']      = time();

                // Token-Rotation: alten Token löschen, neuen ausstellen
                $neuerToken = bin2hex(random_bytes(32));
                $ablauf     = date('Y-m-d H:i:s', strtotime('+30 days'));
                $pdo->prepare("DELETE FROM remember_tokens WHERE token = :token")
                    ->execute([':token' => $_COOKIE['remember_token']]);
                $pdo->prepare("INSERT INTO remember_tokens (user_id, token, ablauf_am) VALUES (:uid, :token, :ablauf)")
                    ->execute([':uid' => $row['user_id'], ':token' => $neuerToken, ':ablauf' => $ablauf]);
                setcookie('remember_token', $neuerToken, [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                echo json_encode([
                    "status"      => "logged_in",
                    "anzeigename" => $row['anzeigename'],
                    "ist_admin"   => (bool)$row['ist_admin']
                ]);
                exit;
            }
        } catch (PDOException $e) {}
    }

    echo json_encode(["status" => "logged_out"]);
    exit;
}

// ============================================================
// GET: CSRF-Token abrufen
// ============================================================
if ($method === 'GET' && $action === 'csrf') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo json_encode(["csrf_token" => $_SESSION['csrf_token']]);
    exit;
}

// ============================================================
// POST: Passwort ändern
// ============================================================
if ($method === 'POST' && $action === 'change_password') {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Nicht eingeloggt."]);
        exit;
    }

    // CSRF-Prüfung
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Ungültiger CSRF-Token."]);
        exit;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['passwort_aktuell']) || empty($data['passwort_neu'])) {
        echo json_encode(["status" => "error", "message" => "Alle Felder erforderlich."]);
        exit;
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\da-zA-Z]).{12,}$/', $data['passwort_neu'])) {
        echo json_encode(["status" => "error", "message" => "Passwort muss mindestens 12 Zeichen lang sein und Groß-/Kleinbuchstaben, eine Zahl und ein Sonderzeichen enthalten."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT passwort_hash FROM users WHERE id = :id AND aktiv = 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['passwort_aktuell'], $user['passwort_hash'])) {
            echo json_encode(["status" => "error", "message" => "Aktuelles Passwort ist falsch."]);
            exit;
        }

        $neuerHash = password_hash($data['passwort_neu'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET passwort_hash = :h WHERE id = :id");
        $stmt->execute([':h' => $neuerHash, ':id' => $_SESSION['user_id']]);

        echo json_encode(["status" => "success", "message" => "Passwort erfolgreich geändert."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Ändern des Passworts."]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Ungültige Anfrage."]);
