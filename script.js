document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zeiterfassung-form');
    const datumInput = document.getElementById('datum_ze');

    // FIX: btn-abbrechen wird jetzt tatsächlich verwendet
    const btnAbbrechen = document.getElementById('btn-abbrechen');
    btnAbbrechen.addEventListener('click', () => {
        form.reset();
        datumInput.value = new Date().toISOString().split('T')[0];
    });

    // Aufträge dynamisch aus der Datenbank laden
    fetch('api_auftraege.php')
        .then(res => res.json())
        .then(auftraege => {
            const select = document.getElementById('auftrag_id');
            select.innerHTML = '<option value="">-- Bitte wählen --</option>';
            auftraege.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = `${a.code} | ${a.auftrag_kuerzel} (${a.bezeichnung || ''})`;
                select.appendChild(opt);
            });
        })
        .catch(err => console.error('Fehler beim Laden der Auftragsliste:', err));

    // FIX: Datum immer aktuell setzen
    datumInput.value = new Date().toISOString().split('T')[0];

    // Doppelklick-Funktion für Uhrzeit
    const zeitVonInput = document.getElementById('zeit_von');
    const zeitBisInput = document.getElementById('zeit_bis');

    function getAktuelleUhrzeit() {
        const jetzt = new Date();
        return `${String(jetzt.getHours()).padStart(2, '0')}:${String(jetzt.getMinutes()).padStart(2, '0')}`;
    }

    zeitVonInput.addEventListener('dblclick', (e) => {
        e.preventDefault();
        zeitVonInput.value = getAktuelleUhrzeit();
    });

    zeitBisInput.addEventListener('dblclick', (e) => {
        e.preventDefault();
        zeitBisInput.value = getAktuelleUhrzeit();
    });

    // ========================================================
    // WOCHENFILTER
    // ========================================================
    let aktuelleWochenOffset = 0; // 0 = aktuelle Woche, -1 = letzte Woche, usw.

    function getWochenBereich(offset) {
        const heute = new Date();
        const tag = heute.getDay(); // 0=So, 1=Mo, ...
        const diffZuMontag = (tag === 0 ? -6 : 1 - tag);
        const montag = new Date(heute);
        montag.setDate(heute.getDate() + diffZuMontag + offset * 7);
        montag.setHours(0, 0, 0, 0);
        const sonntag = new Date(montag);
        sonntag.setDate(montag.getDate() + 6);
        sonntag.setHours(23, 59, 59, 999);
        return { montag, sonntag };
    }

    function getKW(datum) {
        const d = new Date(Date.UTC(datum.getFullYear(), datum.getMonth(), datum.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }

    function aktualisiereWochenAnzeige() {
        const { montag } = getWochenBereich(aktuelleWochenOffset);
        const kw = getKW(montag);
        const label = aktuelleWochenOffset === 0 ? '(Aktuelle Woche)' : '';
        document.getElementById('current-period-display').textContent = `KW ${kw} ${label}`.trim();
        ladeGebuchteZeiten();
    }

    document.getElementById('btn-prev-week').addEventListener('click', () => {
        aktuelleWochenOffset--;
        aktualisiereWochenAnzeige();
    });

    document.getElementById('btn-next-week').addEventListener('click', () => {
        aktuelleWochenOffset++;
        aktualisiereWochenAnzeige();
    });

    document.getElementById('filter-buchungsart').addEventListener('change', () => {
        ladeGebuchteZeiten();
    });

    // Beim Laden direkt die aktuelle Woche anzeigen
    aktualisiereWochenAnzeige();

    // Formular absenden
    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const buchung = Object.fromEntries(formData.entries());
        buchung.problembeschreibung_unz = document.getElementById('problembeschreibung_unz').checked;

        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(buchung)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                alert(result.message);
                form.reset();
                // FIX: Datum immer frisch berechnen
                datumInput.value = new Date().toISOString().split('T')[0];
                ladeGebuchteZeiten();
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

// Daten per GET laden (mit Wochenfilter)
function ladeGebuchteZeiten() {
    fetch('api.php')
        .then(response => response.json())
        .then(daten => {
            if (daten.status === 'error') {
                console.error(daten.message);
                return;
            }

            // Wochenfilter anwenden (clientseitig)
            const offset = window._wochenOffset ?? 0;
            const heute = new Date();
            const tag = heute.getDay();
            const diffZuMontag = (tag === 0 ? -6 : 1 - tag);
            const montag = new Date(heute);
            montag.setDate(heute.getDate() + diffZuMontag + offset * 7);
            montag.setHours(0, 0, 0, 0);
            const sonntag = new Date(montag);
            sonntag.setDate(montag.getDate() + 6);
            sonntag.setHours(23, 59, 59, 999);

            const gefiltertNachWoche = daten.filter(e => {
                const d = new Date(e.datum_ze);
                return d >= montag && d <= sonntag;
            });

            // Buchungsart-Filter
            const filterWert = document.getElementById('filter-buchungsart')?.value || 'alle';
            const gefiltert = filterWert === 'alle'
                ? gefiltertNachWoche
                : gefiltertNachWoche.filter(e => e.buchungsart === filterWert);

            renderTabelle(gefiltert);
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

// Tabelle rendern
function renderTabelle(daten) {
    const tableBody = document.getElementById('zeit-eintraege-body');
    const totalHoursDisplay = document.getElementById('total-hours');

    tableBody.innerHTML = '';
    let gesamtMinutenArbeit = 0;

    daten.forEach(eintrag => {
        const von = eintrag.zeit_von.substring(0, 5);
        const bis = eintrag.zeit_bis.substring(0, 5);
        const dauer = berechneDauer(von, bis);

        if (eintrag.buchungsart !== 'Pause') {
            gesamtMinutenArbeit += dauer.minuten;
        }

        const rowClass = eintrag.buchungsart === 'Pause' ? 'class="row-pause"' : '';
        const deutschesDatum = new Date(eintrag.datum_ze).toLocaleDateString('de-DE');

        // FIX: Auftragsbezeichnung statt roher ID anzeigen
        const auftragText = eintrag.auftrag_code
            ? `${eintrag.auftrag_code} | ${eintrag.auftrag_kuerzel || ''}`
            : eintrag.auftrag_id;

        const row = `
            <tr ${rowClass}>
                <td>${deutschesDatum}</td>
                <td><strong>${eintrag.buchungsart}</strong></td>
                <td>${von} - ${bis}</td>
                <td>${dauer.string} Std.</td>
                <td>${auftragText}</td>
                <td>
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
