<?php
header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php';
require_once 'auth_check.php';

// ── Validierungsfunktionen ──
function valDatum($val) {
    $d = DateTime::createFromFormat('Y-m-d', $val);
    return $d && $d->format('Y-m-d') === $val;
}
function valZeit($val) {
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $val);
}
function valBuchungsart($val) {
    return in_array($val, ['Arbeitszeit', 'Pause', 'Dienstreise']);
}

$current_user_id = (int) $_SESSION['user_id'];
$ist_admin       = !empty($_SESSION['ist_admin']);
$method          = $_SERVER['REQUEST_METHOD'];
$typ             = $_GET['typ'] ?? '';
$id              = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ============================================================
// GET
// ============================================================
if ($method === 'GET') {

    // Leistungsarten
    if ($typ === 'leistungsarten') {
        try {
            $stmt = $pdo->query("SELECT id, kuerzel, bezeichnung FROM leistungsarten ORDER BY kuerzel");
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Fehler beim Laden."]);
        }
        exit;
    }

    // Aufträge
    if ($typ === 'auftraege') {
        try {
            $sql = "SELECT a.*, l.kuerzel AS leistungsart_kuerzel 
                    FROM auftraege a 
                    LEFT JOIN leistungsarten l ON a.leistungsart_id = l.id 
                    ORDER BY a.code, a.auftrag_kuerzel";
            $stmt = $pdo->query($sql);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Fehler beim Laden."]);
        }
        exit;
    }

    // Verfügbare Monate/Jahre für Auswertung (immer nur eigene)
    if ($typ === 'monate') {
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT YEAR(datum_ze) AS jahr, MONTH(datum_ze) AS monat
                                   FROM zeiterfassung WHERE buchungsart != 'Pause' AND user_id = :uid
                                   ORDER BY jahr DESC, monat DESC");
            $stmt->execute([':uid' => $current_user_id]);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Fehler beim Laden."]);
        }
        exit;
    }

    // Zeitbuchungen laden (immer nur eigene)
    try {
        $stmt = $pdo->prepare("SELECT z.*, a.code AS auftrag_code, a.auftrag_kuerzel, l.kuerzel AS leistungsart_kuerzel
                               FROM zeiterfassung z
                               LEFT JOIN auftraege a ON z.auftrag_id = a.id
                               LEFT JOIN leistungsarten l ON a.leistungsart_id = l.id
                               WHERE z.user_id = :uid
                               ORDER BY z.datum_ze ASC, z.zeit_von ASC");
        $stmt->execute([':uid' => $current_user_id]);
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Laden."]);
    }
    exit;
}

// ============================================================
// POST
// ============================================================
if ($method === 'POST') {

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Keine Daten empfangen."]);
        exit;
    }

    // Auftrag anlegen (nur Admins)
    if ($typ === 'auftraege') {
        if (!$ist_admin) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Keine Berechtigung."]);
            exit;
        }
        try {
            $sql = "INSERT INTO auftraege 
                    (code, auftrag_kuerzel, bezeichnung, gueltig_von, gueltig_bis, export_taetigkeit, leistungsart_id) 
                    VALUES (:code, :auftrag_kuerzel, :bezeichnung, :gueltig_von, :gueltig_bis, :export_taetigkeit, :leistungsart_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':code'              => htmlspecialchars(trim($data['code'])),
                ':auftrag_kuerzel'   => htmlspecialchars(trim($data['auftrag_kuerzel'])),
                ':bezeichnung'       => htmlspecialchars(trim($data['bezeichnung'])),
                ':gueltig_von'       => $data['gueltig_von'],
                ':gueltig_bis'       => $data['gueltig_bis'],
                ':export_taetigkeit' => $data['export_taetigkeit'] ? 1 : 0,
                ':leistungsart_id'   => !empty($data['leistungsart_id']) ? (int)$data['leistungsart_id'] : null
            ]);
            echo json_encode(["status" => "success", "message" => "Auftrag erfolgreich angelegt!"]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(["status" => "error", "message" => "Diese Kombination aus Code und CATS-Code existiert bereits."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Fehler beim Speichern."]);
            }
        }
        exit;
    }

    // Zeitbuchung speichern – Validierung
    if (!valBuchungsart($data['buchungsart'] ?? '')) {
        echo json_encode(["status" => "error", "message" => "Ungültige Buchungsart."]);
        exit;
    }
    if (!valDatum($data['datum_ze'] ?? '')) {
        echo json_encode(["status" => "error", "message" => "Ungültiges Datum."]);
        exit;
    }
    if (!valZeit($data['zeit_von'] ?? '') || !valZeit($data['zeit_bis'] ?? '')) {
        echo json_encode(["status" => "error", "message" => "Ungültiges Zeitformat."]);
        exit;
    }
    if (empty($data['auftrag_id']) && $data['buchungsart'] !== 'Pause') {
        echo json_encode(["status" => "error", "message" => "Kein Auftrag ausgewählt."]);
        exit;
    }

    try {
        $sql = "INSERT INTO zeiterfassung 
                (user_id, auftrag_id, datum_ze, zeit_von, zeit_bis, buchungsart, meldungsnummer, weiterleitung_an, taetigkeit, anmerkungen) 
                VALUES (:user_id, :auftrag_id, :datum_ze, :zeit_von, :zeit_bis, :buchungsart, :meldungsnummer, :weiterleitung_an, :taetigkeit, :anmerkungen)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id'          => $current_user_id,
            ':auftrag_id'       => !empty($data['auftrag_id']) ? (int)$data['auftrag_id'] : null,
            ':datum_ze'         => $data['datum_ze'],
            ':zeit_von'         => $data['zeit_von'],
            ':zeit_bis'         => $data['zeit_bis'],
            ':buchungsart'      => $data['buchungsart'],
            ':meldungsnummer'   => !empty($data['meldungsnummer']) ? htmlspecialchars(trim($data['meldungsnummer'])) : null,
            ':weiterleitung_an' => !empty($data['weiterleitung_an']) ? htmlspecialchars(trim($data['weiterleitung_an'])) : null,
            ':taetigkeit'       => !empty($data['taetigkeit']) ? htmlspecialchars(trim($data['taetigkeit'])) : null,
            ':anmerkungen'      => !empty($data['anmerkungen']) ? htmlspecialchars(trim($data['anmerkungen'])) : null
        ]);
        echo json_encode(["status" => "success", "message" => "Eintrag erfolgreich gespeichert!"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Speichern."]);
    }
    exit;
}

// ============================================================
// PUT
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

    // Auftrag bearbeiten (nur Admins)
    if ($typ === 'auftraege') {
        if (!$ist_admin) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Keine Berechtigung."]);
            exit;
        }
        try {
            $sql = "UPDATE auftraege SET
                        code              = :code,
                        auftrag_kuerzel   = :auftrag_kuerzel,
                        bezeichnung       = :bezeichnung,
                        gueltig_von       = :gueltig_von,
                        gueltig_bis       = :gueltig_bis,
                        export_taetigkeit = :export_taetigkeit,
                        leistungsart_id   = :leistungsart_id
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id'                => $id,
                ':code'              => htmlspecialchars(trim($data['code'])),
                ':auftrag_kuerzel'   => htmlspecialchars(trim($data['auftrag_kuerzel'])),
                ':bezeichnung'       => htmlspecialchars(trim($data['bezeichnung'])),
                ':gueltig_von'       => $data['gueltig_von'],
                ':gueltig_bis'       => $data['gueltig_bis'],
                ':export_taetigkeit' => $data['export_taetigkeit'] ? 1 : 0,
                ':leistungsart_id'   => !empty($data['leistungsart_id']) ? (int)$data['leistungsart_id'] : null
            ]);
            echo json_encode(["status" => "success", "message" => "Auftrag erfolgreich aktualisiert!"]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Fehler beim Aktualisieren."]);
        }
        exit;
    }

    // Zeitbuchung bearbeiten – nur eigene (außer Admin)
    if (!$ist_admin) {
        $check = $pdo->prepare("SELECT id FROM zeiterfassung WHERE id = :id AND user_id = :uid");
        $check->execute([':id' => $id, ':uid' => $current_user_id]);
        if (!$check->fetch()) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Keine Berechtigung."]);
            exit;
        }
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
            ':id'               => $id,
            ':auftrag_id'       => !empty($data['auftrag_id']) ? (int)$data['auftrag_id'] : null,
            ':datum_ze'         => $data['datum_ze'],
            ':zeit_von'         => $data['zeit_von'],
            ':zeit_bis'         => $data['zeit_bis'],
            ':buchungsart'      => $data['buchungsart'],
            ':meldungsnummer'   => !empty($data['meldungsnummer']) ? htmlspecialchars(trim($data['meldungsnummer'])) : null,
            ':weiterleitung_an' => !empty($data['weiterleitung_an']) ? htmlspecialchars(trim($data['weiterleitung_an'])) : null,
            ':taetigkeit'       => !empty($data['taetigkeit']) ? htmlspecialchars(trim($data['taetigkeit'])) : null,
            ':anmerkungen'      => !empty($data['anmerkungen']) ? htmlspecialchars(trim($data['anmerkungen'])) : null
        ]);
        echo json_encode(["status" => "success", "message" => "Eintrag erfolgreich aktualisiert!"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Aktualisieren."]);
    }
    exit;
}

// ============================================================
// DELETE
// ============================================================
if ($method === 'DELETE') {

    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Keine ID angegeben."]);
        exit;
    }

    // Auftrag löschen (nur Admins)
    if ($typ === 'auftraege') {
        if (!$ist_admin) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Keine Berechtigung."]);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM auftraege WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(["status" => "success", "message" => "Auftrag gelöscht."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Löschen nicht möglich: Es sind bereits Zeiten auf diesen Auftrag gebucht."]);
        }
        exit;
    }

    // Zeitbuchung löschen – nur eigene (außer Admin)
    if (!$ist_admin) {
        $check = $pdo->prepare("SELECT id FROM zeiterfassung WHERE id = :id AND user_id = :uid");
        $check->execute([':id' => $id, ':uid' => $current_user_id]);
        if (!$check->fetch()) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Keine Berechtigung."]);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM zeiterfassung WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(["status" => "success", "message" => "Eintrag erfolgreich gelöscht."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Fehler beim Löschen."]);
    }
    exit;
}
