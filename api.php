<?php
// api.php
header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php';

// ========================================================
// POST: Neuen Eintrag speichern
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Keine Daten empfangen."]);
        exit;
    }

    if (empty($data['auftrag_id'])) {
        echo json_encode(["status" => "error", "message" => "Kein Auftrag ausgewählt."]);
        exit;
    }

    try {
        $sql = "INSERT INTO zeiterfassung 
                (user_id, auftrag_id, datum_ze, zeit_von, zeit_bis, buchungsart, meldungsnummer, weiterleitung_an, taetigkeit, anmerkungen) 
                VALUES (:user_id, :auftrag_id, :datum_ze, :zeit_von, :zeit_bis, :buchungsart, :meldungsnummer, :weiterleitung_an, :taetigkeit, :anmerkungen)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id'          => 1,
            ':auftrag_id'       => $data['auftrag_id'],
            ':datum_ze'         => $data['datum_ze'],
            ':zeit_von'         => $data['zeit_von'],
            ':zeit_bis'         => $data['zeit_bis'],
            ':buchungsart'      => $data['buchungsart'],
            ':meldungsnummer'   => !empty($data['meldungsnummer']) ? $data['meldungsnummer'] : null,
            ':weiterleitung_an' => !empty($data['weiterleitung_an']) ? $data['weiterleitung_an'] : null,
            ':taetigkeit'       => !empty($data['taetigkeit']) ? $data['taetigkeit'] : null,
            ':anmerkungen'      => !empty($data['anmerkungen']) ? $data['anmerkungen'] : null
        ]);

        echo json_encode(["status" => "success", "message" => "Eintrag erfolgreich gespeichert!"]);

    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Speichern: " . $e->getMessage()]);
    }
    exit;
}

// ========================================================
// GET: Einträge laden
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "SELECT z.*, a.code AS auftrag_code, a.auftrag_kuerzel 
                FROM zeiterfassung z
                LEFT JOIN auftraege a ON z.auftrag_id = a.id
                ORDER BY z.datum_ze DESC, z.zeit_von DESC";

        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Laden: " . $e->getMessage()]);
    }
    exit;
}

// ========================================================
// PUT: Eintrag bearbeiten
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    if (empty($_GET['id'])) {
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
        $sql = "UPDATE zeiterfassung SET
                    auftrag_id       = :auftrag_id,
                    datum_ze         = :datum_ze,
                    zeit_von         = :zeit_von,
                    zeit_bis         = :zeit_bis,
                    buchungsart      = :buchungsart,
                    meldungsnummer   = :meldungsnummer,
                    weiterleitung_an = :weiterleitung_an,
                    taetigkeit       = :taetigkeit,
                    anmerkungen      = :anmerkungen
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'               => $_GET['id'],
            ':auftrag_id'       => $data['auftrag_id'],
            ':datum_ze'         => $data['datum_ze'],
            ':zeit_von'         => $data['zeit_von'],
            ':zeit_bis'         => $data['zeit_bis'],
            ':buchungsart'      => $data['buchungsart'],
            ':meldungsnummer'   => !empty($data['meldungsnummer']) ? $data['meldungsnummer'] : null,
            ':weiterleitung_an' => !empty($data['weiterleitung_an']) ? $data['weiterleitung_an'] : null,
            ':taetigkeit'       => !empty($data['taetigkeit']) ? $data['taetigkeit'] : null,
            ':anmerkungen'      => !empty($data['anmerkungen']) ? $data['anmerkungen'] : null
        ]);

        echo json_encode(["status" => "success", "message" => "Eintrag erfolgreich aktualisiert!"]);

    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Aktualisieren: " . $e->getMessage()]);
    }
    exit;
}

// ========================================================
// DELETE: Eintrag löschen
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    if (empty($_GET['id'])) {
        echo json_encode(["status" => "error", "message" => "Keine ID angegeben."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM zeiterfassung WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        echo json_encode(["status" => "success", "message" => "Eintrag erfolgreich gelöscht."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Löschen: " . $e->getMessage()]);
    }
    exit;
}
