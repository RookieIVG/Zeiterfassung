document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zeiterfassung-form');
    const datumInput = document.getElementById('datum_ze');
    const btnAbbrechen = document.getElementById('btn-abbrechen');

// Aufträge dynamisch aus der Datenbank in das Formular-Dropdown laden
    fetch('api_auftraege.php')
        .then(res => res.json())
        .then(auftraege => {
            const select = document.getElementById('auftrag_id');
            // Zuerst alle alten statischen Optionen entfernen außer der ersten
            select.innerHTML = '<option value="">-- Bitte wählen --</option>';
            
            auftraege.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id; // Die interne Datenbank-ID für die Verknüpfung
                opt.textContent = `${a.code} | ${a.auftrag_kuerzel} (${a.bezeichnung || ''})`;
                select.appendChild(opt);
            });
        })
        .catch(err => console.error('Fehler beim Laden der Auftragsliste:', err));

    // 1. Heute als Standarddatum setzen
    const heute = new Date().toISOString().split('T')[0];
    datumInput.value = heute;

// ========================================================
    // DOPPELKLICK-FUNKTION FÜR DIE UHRZEIT (KORRIGIERT)
    // ========================================================
    const zeitVonInput = document.getElementById('zeit_von');
    const zeitBisInput = document.getElementById('zeit_bis');

    // Funktion, die die aktuelle Uhrzeit im Format HH:MM zurückgibt
    function getAktuelleUhrzeit() {
        const jetzt = new Date();
        const stunden = String(jetzt.getHours()).padStart(2, '0');
        const minuten = String(jetzt.getMinutes()).padStart(2, '0');
        return `${stunden}:${minuten}`;
    }

    // Event-Listener für das "Von"-Feld
    zeitVonInput.addEventListener('dblclick', (e) => {
        e.preventDefault(); // Verhindert die standardmäßige Textauswahl im Zeitfeld
        zeitVonInput.value = getAktuelleUhrzeit();
    });

    // Event-Listener für das "Bis"-Feld
    zeitBisInput.addEventListener('dblclick', (e) => {
        e.preventDefault(); // Verhindert die standardmäßige Textauswahl im Zeitfeld
        zeitBisInput.value = getAktuelleUhrzeit();
    });

    // 2. Bestehende Daten direkt beim Laden der Seite aus der Datenbank holen
    ladeGebuchteZeiten();

    // 3. Event-Listener für das Absenden des Formulars (Speichern)
    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const buchung = Object.fromEntries(formData.entries());
        buchung.problembeschreibung_unz = document.getElementById('problembeschreibung_unz').checked;

        // DATEN AN DAS BACKEND SENDEN
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(buchung)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                alert(result.message);
                form.reset();
                datumInput.value = heute;
                ladeGebuchteZeiten(); // Tabelle aktualisieren
            } else {
                alert('Fehler: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Fehler beim Senden:', error);
            alert('Netzwerkfehler beim Speichern.');
        });
    });
});

// Funktion, um die Daten per GET aus der Datenbank zu laden
function ladeGebuchteZeiten() {
    fetch('api.php')
        .then(response => response.json())
        .then(daten => {
            if (daten.status === 'error') {
                console.error(daten.message);
                return;
            }
            renderTabelle(daten);
        })
        .catch(error => console.error('Fehler beim Laden der Daten:', error));
}

// Hilfsfunktion zur Berechnung der Dauer (hh:mm)
function berechneDauer(von, bis) {
    const [vonH, vonM] = von.split(':').map(Number);
    const [bisH, bisM] = bis.split(':').map(Number);
    let diff = (bisH * 60 + bisM) - (vonH * 60 + vonM);
    if (diff < 0) diff += 24 * 60;
    return {
        string: `${String(Math.floor(diff / 60)).padStart(2, '0')}:${String(diff % 60).padStart(2, '0')}`,
        minuten: diff
    };
}

// Tabelle auf Basis der echten Datenbank-Einträge aufbauen
function renderTabelle(daten) {
    const tableBody = document.getElementById('zeit-eintraege-body');
    const totalHoursDisplay = document.getElementById('total-hours');
    
    tableBody.innerHTML = '';
    let gesamtMinutenArbeit = 0;

    daten.forEach(eintrag => {
        // Falls die Zeit aus der DB Sekunden enthält (z.B. 08:00:00), kürzen wir sie auf hh:mm
        const von = eintrag.zeit_von.substring(0, 5);
        const bis = eintrag.zeit_bis.substring(0, 5);
        
        const dauer = berechneDauer(von, bis);
        
        if (eintrag.buchungsart !== 'Pause') {
            gesamtMinutenArbeit += dauer.minuten;
        }

        const rowClass = eintrag.buchungsart === 'Pause' ? 'class="row-pause"' : '';
        const deutschesDatum = new Date(eintrag.datum_ze).toLocaleDateString('de-DE');

        const row = `
            <tr ${rowClass}>
                <td>${deutschesDatum}</td>
                <td><strong>${eintrag.buchungsart}</strong></td>
                <td>${von} - ${bis}</td>
                <td>${dauer.string} Std.</td>
                <td>${eintrag.auftrag_id}</td> <td>
                    <strong>${eintrag.taetigkeit || '-'}</strong>
                    ${eintrag.anmerkungen ? `<br><small style="color: #718096">${eintrag.anmerkungen}</small>` : ''}
                </td>
                <td>
                    <button class="action-btn" title="Bearbeiten">✏️</button>
                    <button class="action-btn" title="Löschen">❌</button>
                </td>
            </tr>
        `;
        tableBody.insertAdjacentHTML('beforeend', row);
    });

    const stunden = Math.floor(gesamtMinutenArbeit / 60);
    const minuten = gesamtMinutenArbeit % 60;
    totalHoursDisplay.innerText = `${String(stunden).padStart(2, '0')}:${String(minuten).padStart(2, '0')} Std.`;
}