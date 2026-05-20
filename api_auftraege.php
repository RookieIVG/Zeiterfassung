<?php
// api_auftraege.php
header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php'; // DB-Verbindung ($pdo) wird hier hergestellt

$method = $_SERVER['REQUEST_METHOD'];

// 1. GET: Daten abfragen
if ($method === 'GET') {
    if (isset($_GET['typ']) && $_GET['typ'] === 'leistungsarten') {
        try {
            $stmt = $pdo->query("SELECT id, kuerzel, bezeichnung FROM leistungsarten ORDER BY kuerzel");
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }

    try {
        $sql = "SELECT a.*, l.kuerzel AS leistungsart_kuerzel 
                FROM auftraege a 
                LEFT JOIN leistungsarten l ON a.leistungsart_id = l.id 
                ORDER BY a.code, a.auftrag_kuerzel";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// 2. POST: Neuen Auftrag speichern
if ($method === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Keine Daten empfangen."]);
        exit;
    }

    try {
        $sql = "INSERT INTO auftraege 
                (code, auftrag_kuerzel, bezeichnung, gueltig_von, gueltig_bis, export_taetigkeit, leistungsart_id) 
                VALUES (:code, :auftrag_kuerzel, :bezeichnung, :gueltig_von, :gueltig_bis, :export_taetigkeit, :leistungsart_id)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':code'              => $data['code'],
            ':auftrag_kuerzel'   => $data['auftrag_kuerzel'],
            ':bezeichnung'       => !empty($data['bezeichnung']) ? $data['bezeichnung'] : null,
            ':gueltig_von'       => !empty($data['gueltig_von']) ? $data['gueltig_von'] : null,
            ':gueltig_bis'       => !empty($data['gueltig_bis']) ? $data['gueltig_bis'] : null,
            ':export_taetigkeit' => $data['export_taetigkeit'] ? 1 : 0,
            ':leistungsart_id'   => !empty($data['leistungsart_id']) ? $data['leistungsart_id'] : null
        ]);

        echo json_encode(["status" => "success", "message" => "Auftrag erfolgreich angelegt!"]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(["status" => "error", "message" => "Diese Kombination aus Code und Auftrag-Kürzel existiert bereits."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Fehler: " . $e->getMessage()]);
        }
    }
    exit;
}

// 3. DELETE: Auftrag löschen
if ($method === 'DELETE') {
    if (isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM auftraege WHERE id = :id");
            $stmt->execute([':id' => $_GET['id']]);
            echo json_encode(["status" => "success", "message" => "Auftrag gelöscht."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Löschen nicht möglich: Es sind bereits Zeiten auf diesen Auftrag gebucht."]);
        }
    }
    exit;
}
