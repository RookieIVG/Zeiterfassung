<?php
// api.php
header("Content-Type: application/json; charset=UTF-8");

// 1. DATENBANK-ZUGANGSDATEN
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

// 2. VERARBEITUNG: DATEN SPEICHERN (POST-Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Keine Daten empfangen."]);
        exit;
    }

    // Pflichtfelder prüfen
    if (empty($data['auftrag_id'])) {
        echo json_encode(["status" => "error", "message" => "Kein Auftrag ausgewählt."]);
        exit;
    }

    try {
        $sql = "INSERT INTO zeiterfassung 
                (user_id, auftrag_id, datum_ze, zeit_von, zeit_bis, buchungsart, meldungsnummer, weiterleitung_an, taetigkeit, problembeschreibung_unz, anmerkungen) 
                VALUES (:user_id, :auftrag_id, :datum_ze, :zeit_von, :zeit_bis, :buchungsart, :meldungsnummer, :weiterleitung_an, :taetigkeit, :problembeschreibung_unz, :anmerkungen)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':user_id'                 => 1, // TODO: Echten User nach Login-System einbauen
            ':auftrag_id'              => $data['auftrag_id'], // FIX: Aus dem Formular übernehmen
            ':datum_ze'                => $data['datum_ze'],
            ':zeit_von'                => $data['zeit_von'],
            ':zeit_bis'                => $data['zeit_bis'],
            ':buchungsart'             => $data['buchungsart'],
            ':meldungsnummer'          => !empty($data['meldungsnummer']) ? $data['meldungsnummer'] : null,
            ':weiterleitung_an'        => !empty($data['weiterleitung_an']) ? $data['weiterleitung_an'] : null,
            ':taetigkeit'              => !empty($data['taetigkeit']) ? $data['taetigkeit'] : null,
            ':problembeschreibung_unz' => $data['problembeschreibung_unz'] ? 1 : 0,
            ':anmerkungen'             => !empty($data['anmerkungen']) ? $data['anmerkungen'] : null
        ]);

        echo json_encode(["status" => "success", "message" => "Eintrag erfolgreich gespeichert!"]);

    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Speichern: " . $e->getMessage()]);
    }
    exit;
}

// 3. VERARBEITUNG: DATEN LADEN (GET-Request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // FIX: JOIN auf auftraege, damit Code und Kürzel mitgeliefert werden
        $sql = "SELECT z.*, a.code AS auftrag_code, a.auftrag_kuerzel 
                FROM zeiterfassung z
                LEFT JOIN auftraege a ON z.auftrag_id = a.id
                ORDER BY z.datum_ze DESC, z.zeit_von DESC";

        $stmt = $pdo->query($sql);
        $eintraege = $stmt->fetchAll();

        echo json_encode($eintraege);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Laden: " . $e->getMessage()]);
    }
    exit;
}
