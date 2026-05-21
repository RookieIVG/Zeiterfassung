<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 1800); // 30 Minuten

session_start();

// Session-Timeout prüfen (nur wenn NICHT "angemeldet bleiben")
if (!empty($_SESSION['user_id']) && empty($_SESSION['angemeldet_bleiben'])) {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
        session_unset();
        session_destroy();
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Session abgelaufen."]);
        exit;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

if (empty($_SESSION['user_id'])) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Nicht eingeloggt."]);
    exit;
}

// CSRF-Token generieren falls noch nicht vorhanden
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF-Prüfung für alle state-changing Requests
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Ungültiger CSRF-Token."]);
        exit;
    }
}
