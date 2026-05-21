<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

if (empty($_SESSION['user_id'])) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Nicht eingeloggt."]);
    exit;
}
