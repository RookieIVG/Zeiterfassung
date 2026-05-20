<?php
// api.php
header("Content-Type: application/json; charset=UTF-8");

// 1. DATENBANK-ZUGANGSDATEN (Bitte anpassen!)
$host     = ""; // Meistens 'localhost' oder eine IP-Adresse
$db_name  = "";
$username = "";
$password = "";

try {
    // Verbindung über PDO (sicher vor SQL-Injections)
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Datenbankverbindung fehlgeschlagen."]);
    exit;
}

// 2. VERARBEITUNG: DATEN SPECHERN (POST-Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // JSON-Daten aus dem JavaScript-Fetch entgegennehmen
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Keine Daten empfangen."]);
        exit;
    }

    try {
        // SQL-Befehl vorbereiten (Prepared Statement)
        // HINWEIS: Da wir noch kein Login-System haben, setzen wir 'user_id' testweise fest auf 1.
        // Ebenso nehmen wir an, dass die auftrag_id der Einfachheit halber erst einmal 1 ist (musst du anpassen).
        $sql = "INSERT INTO zeiterfassung 
                (user_id, auftrag_id, datum_ze, zeit_von, zeit_bis, buchungsart, meldungsnummer, weiterleitung_an, taetigkeit, problembeschreibung_unz, anmerkungen) 
                VALUES (:user_id, :auftrag_id, :datum_ze, :zeit_von, :zeit_bis, :buchungsart, :meldungsnummer, :weiterleitung_an, :taetigkeit, :problembeschreibung_unz, :anmerkungen)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':user_id'                 => 1, // Temporärer Test-User (muss in Tabelle 'users' existieren!)
            ':auftrag_id'              => 1, // Temporärer Test-Auftrag (muss in Tabelle 'auftraege' existieren!)
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
        // Holt alle Einträge (später filtern wir hier nach Woche/Monat)
        $stmt = $pdo->query("SELECT * FROM zeiterfassung ORDER BY datum_ze DESC, zeit_von DESC");
        $eintraege = $stmt->fetchAll();
        
        echo json_encode($eintraege);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Laden: " . $e->getMessage()]);
    }
    exit;
}
