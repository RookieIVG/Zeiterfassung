// Aktuell gewählter Monat/Jahr
const _jetzt = new Date();
let aktuellerMonat = _jetzt.getMonth(); // 0-basiert
let aktuellesJahr  = _jetzt.getFullYear();

// Speichert den aktuell bearbeiteten Eintrag (null = neuer Eintrag)
let bearbeitungId = null;

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zeiterfassung-form');
    const datumInput = document.getElementById('datum_ze');
    const submitBtn = form.querySelector('button[type="submit"]');

    // Abbrechen-Button
    document.getElementById('btn-abbrechen').addEventListener('click', () => {
        form.reset();
        datumInput.value = new Date().toISOString().split('T')[0];
        document.getElementById('auftrag_search').value = '';
        bearbeitungId = null;
        submitBtn.textContent = 'Zeit buchen';
    });

    // ── Auftrag-Combobox ──
    let alleAuftraege = [];

    fetch('api.php?typ=auftraege')
        .then(res => res.json())
        .then(auftraege => {
            const heute = new Date();
            heute.setHours(0, 0, 0, 0);
            alleAuftraege = auftraege.filter(a =>
                !a.gueltig_bis || new Date(a.gueltig_bis) >= heute
            );
        })
        .catch(err => console.error('Fehler beim Laden der Auftragsliste:', err));

    const auftragSearch   = document.getElementById('auftrag_search');
    const auftragIdInput  = document.getElementById('auftrag_id');
    const auftragDropdown = document.getElementById('auftrag_dropdown');

    function zeigeDropdown(liste) {
        auftragDropdown.innerHTML = '';
        if (liste.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'combobox-option-empty';
            empty.textContent = 'Keine Treffer';
            auftragDropdown.appendChild(empty);
        } else {
            liste.forEach(a => {
                const div = document.createElement('div');
                div.className = 'combobox-option';
                // XSS-Fix: textContent statt innerHTML
                const spanCode = document.createElement('span');
                spanCode.className = 'option-code';
                spanCode.textContent = `${a.code} | ${a.auftrag_kuerzel}`;
                const spanSub = document.createElement('span');
                spanSub.className = 'option-sub';
                spanSub.textContent = a.bezeichnung || '';
                div.appendChild(spanCode);
                div.appendChild(spanSub);
                div.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    auftragSearch.value  = `${a.code} | ${a.auftrag_kuerzel}`;
                    auftragIdInput.value = a.id;
                    auftragDropdown.classList.remove('open');
                });
                auftragDropdown.appendChild(div);
            });
        }
        auftragDropdown.classList.add('open');
    }

    auftragSearch.addEventListener('input', () => {
        const q = auftragSearch.value.toLowerCase();
        auftragIdInput.value = '';
        if (q.length === 0) {
            zeigeDropdown(alleAuftraege);
        } else {
            const gefiltert = alleAuftraege.filter(a =>
                a.code.toLowerCase().includes(q) ||
                a.auftrag_kuerzel.toLowerCase().includes(q) ||
                (a.bezeichnung || '').toLowerCase().includes(q)
            );
            zeigeDropdown(gefiltert);
        }
    });

    auftragSearch.addEventListener('focus', () => {
        const q = auftragSearch.value.toLowerCase();
        const gefiltert = q ? alleAuftraege.filter(a =>
            a.code.toLowerCase().includes(q) ||
            a.auftrag_kuerzel.toLowerCase().includes(q) ||
            (a.bezeichnung || '').toLowerCase().includes(q)
        ) : alleAuftraege;
        zeigeDropdown(gefiltert);
    });

    auftragSearch.addEventListener('blur', () => {
        setTimeout(() => auftragDropdown.classList.remove('open'), 150);
    });

    // Heutiges Datum setzen
    datumInput.value = new Date().toISOString().split('T')[0];


    // Auftrag (Code): kein Pflichtfeld bei Pause
    const buchungsartSelect = document.getElementById('buchungsart');
    const auftragLabel = document.querySelector('label[for="auftrag_id"]');

    function aktualisiereAuftragPflicht() {
        const isPause = buchungsartSelect.value === 'Pause';
        auftragLabel.textContent = isPause ? 'Auftrag (Code):' : 'Auftrag (Code) *:';
        document.getElementById('auftrag_search').style.opacity = isPause ? '0.6' : '1';
    }

    buchungsartSelect.addEventListener('change', aktualisiereAuftragPflicht);
    aktualisiereAuftragPflicht(); // beim Laden einmal ausführen
    // Doppelklick für aktuelle Uhrzeit
    const zeitVonInput = document.getElementById('zeit_von');
    const zeitBisInput = document.getElementById('zeit_bis');

    function getAktuelleUhrzeit() {
        const jetzt = new Date();
        return `${String(jetzt.getHours()).padStart(2, '0')}:${String(jetzt.getMinutes()).padStart(2, '0')}`;
    }

    zeitVonInput.addEventListener('dblclick', (e) => { e.preventDefault(); zeitVonInput.value = getAktuelleUhrzeit(); });
    zeitBisInput.addEventListener('dblclick', (e) => { e.preventDefault(); zeitBisInput.value = getAktuelleUhrzeit(); });

    // ========================================================
    // WOCHENFILTER
    // ========================================================
    const monatNamen = ['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

    function aktualisiereMonatsAnzeige() {
        const label = `${monatNamen[aktuellerMonat]} ${aktuellesJahr}`;
        document.getElementById('current-period-display').textContent = label;
        ladeGebuchteZeiten();
    }

    document.getElementById('btn-prev-week').addEventListener('click', () => {
        aktuellerMonat--;
        if (aktuellerMonat < 0) { aktuellerMonat = 11; aktuellesJahr--; }
        aktualisiereMonatsAnzeige();
    });
    document.getElementById('btn-next-week').addEventListener('click', () => {
        aktuellerMonat++;
        if (aktuellerMonat > 11) { aktuellerMonat = 0; aktuellesJahr++; }
        aktualisiereMonatsAnzeige();
    });
    document.getElementById('filter-buchungsart').addEventListener('change', () => ladeGebuchteZeiten());

    aktualisiereMonatsAnzeige();

    // ========================================================
    // FORMULAR ABSENDEN (Neu anlegen oder Bearbeiten)
    // ========================================================
    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const buchung = Object.fromEntries(formData.entries());

        const isEdit = bearbeitungId !== null;
        const url = isEdit ? `api.php?id=${bearbeitungId}` : 'api.php';
        const method = isEdit ? 'PUT' : 'POST';

        apiFetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(buchung)
        })
        .then(result => {
            if (result.status === 'success') {
                // Bis-Zeit merken für nächste Von-Zeit
                const zeitBisWert = document.getElementById('zeit_bis').value;
                const warNeueBuchung = bearbeitungId === null;

                form.reset();
                datumInput.value = new Date().toISOString().split('T')[0];
                document.getElementById('auftrag_search').value = '';
                bearbeitungId = null;
                submitBtn.textContent = 'Zeit buchen';

                // Nur bei neuen Buchungen (nicht beim Bearbeiten) die Von-Zeit vorbelegen
                if (warNeueBuchung && zeitBisWert) {
                    document.getElementById('zeit_von').value = zeitBisWert;
                }

                aktualisiereAuftragPflicht();
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

// ========================================================
// DATEN LADEN
// ========================================================
function ladeGebuchteZeiten() {
    fetch('api.php')
        .then(response => {
            if (response.status === 401) { window.location.href = 'login.html'; return null; }
            return response.json();
        })
        .then(daten => {
            if (!daten || !Array.isArray(daten)) {
                if (daten && daten.message) console.error(daten.message);
                return;
            }

            // Monatsfilter (clientseitig, nutzt globale Variable)
            const gefiltertNachWoche = daten.filter(e => {
                const d = new Date(e.datum_ze);
                return d.getMonth() === aktuellerMonat && d.getFullYear() === aktuellesJahr;
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

// ========================================================
// BEARBEITEN
// ========================================================
function bearbeiten(id, eintrag) {
    bearbeitungId = id;

    // Formular mit den Daten befüllen
    document.getElementById('datum_ze').value = eintrag.datum_ze;
    document.getElementById('zeit_von').value = eintrag.zeit_von.substring(0, 5);
    document.getElementById('zeit_bis').value = eintrag.zeit_bis.substring(0, 5);
    document.getElementById('buchungsart').value = eintrag.buchungsart;
    document.getElementById('auftrag_id').value = eintrag.auftrag_id;
    document.getElementById('taetigkeit').value = eintrag.taetigkeit || '';
    document.getElementById('meldungsnummer').value = eintrag.meldungsnummer || '';
    document.getElementById('weiterleitung_an').value = eintrag.weiterleitung_an || '';
    document.getElementById('anmerkungen').value = eintrag.anmerkungen || '';

    // Button-Text ändern und nach oben scrollen
    document.querySelector('button[type="submit"]').textContent = 'Änderung speichern';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ========================================================
// SCHNELLKOPIEREN (Doppelklick: Auftrag, Tätigkeit, Anmerkungen übernehmen)
// ========================================================
function schnellkopieren(eintrag) {
    document.getElementById('auftrag_id').value = eintrag.auftrag_id;
    document.getElementById('auftrag_search').value = eintrag.auftrag_code
        ? `${eintrag.auftrag_code} | ${eintrag.auftrag_kuerzel || ''}`
        : '';
    document.getElementById('taetigkeit').value = eintrag.taetigkeit || '';
    document.getElementById('anmerkungen').value = eintrag.anmerkungen || '';
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Kurz aufleuchten lassen als visuelles Feedback
    const auftragInput = document.getElementById('auftrag_search');
    auftragInput.style.transition = 'background-color 0.3s';
    auftragInput.style.backgroundColor = '#d4edda';
    setTimeout(() => auftragInput.style.backgroundColor = '', 800);
}

// ========================================================
// LÖSCHEN
// ========================================================
function loeschen(id) {
    if (!confirm('Diesen Eintrag wirklich löschen?')) return;

    apiFetch(`api.php?id=${id}`, { method: 'DELETE' })
        .then(result => {
            if (result.status === 'success') ladeGebuchteZeiten();
            else alert('Fehler: ' + result.message);
        })
        .catch(error => {
            console.error('Fehler beim Löschen:', error);
            alert('Netzwerkfehler beim Löschen.');
        });
}

// ========================================================
// HILFSFUNKTIONEN
// ========================================================
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

function getKWNummer(datum) {
    const d = new Date(Date.UTC(datum.getFullYear(), datum.getMonth(), datum.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
}

function minZuString(min) {
    return `${String(Math.floor(min / 60)).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;
}

function summenZeile(label, minuten, cssClass) {
    return `
        <tr class="${cssClass}">
            <td colspan="3" style="text-align:right; font-style:italic; padding-right:12px;">${label}</td>
            <td><strong>${minZuString(minuten)}</strong></td>
            <td colspan="4"></td>
        </tr>`;
}

function renderTabelle(daten) {
    const tableBody = document.getElementById('zeit-eintraege-body');
    const totalHoursDisplay = document.getElementById('total-hours');

    tableBody.innerHTML = '';
    let gesamtMinutenArbeit = 0;

    // Einträge nach Woche und Tag gruppieren
    const gruppen = [];
    let aktuelleWocheKey = null;
    let aktuellerTagKey = null;

    daten.forEach(eintrag => {
        const aktDatum = new Date(eintrag.datum_ze);
        const tagKey = eintrag.datum_ze.substring(0, 10);
        const kwKey  = `${aktDatum.getFullYear()}-${getKWNummer(aktDatum)}`;

        if (kwKey !== aktuelleWocheKey) {
            aktuelleWocheKey = kwKey;
            aktuellerTagKey = null;
            gruppen.push({ kw: getKWNummer(aktDatum), tage: [] });
        }

        const aktWoche = gruppen[gruppen.length - 1];

        if (tagKey !== aktuellerTagKey) {
            aktuellerTagKey = tagKey;
            aktWoche.tage.push({ tagKey, datum: aktDatum, eintraege: [] });
        }

        aktWoche.tage[aktWoche.tage.length - 1].eintraege.push(eintrag);
    });

    // Rendern
    gruppen.forEach((woche, wi) => {
        let wochenMinuten = 0;

        woche.tage.forEach(tag => {
            let tagesMinuten = 0;

            tag.eintraege.forEach(eintrag => {
                const von = eintrag.zeit_von.substring(0, 5);
                const bis = eintrag.zeit_bis.substring(0, 5);
                const dauer = berechneDauer(von, bis);
                const istPause = eintrag.buchungsart === 'Pause';

                if (!istPause) {
                    tagesMinuten    += dauer.minuten;
                    wochenMinuten   += dauer.minuten;
                    gesamtMinutenArbeit += dauer.minuten;
                }

                const rowClass = istPause ? 'class="row-pause"' : '';
                const deutschesDatum = tag.datum.toLocaleDateString('de-DE');
                const auftragText = eintrag.auftrag_code
                    ? `${eintrag.auftrag_code} | ${eintrag.auftrag_kuerzel || ''}`
                    : '-';

                tableBody.insertAdjacentHTML('beforeend', `
                    <tr ${rowClass} ondblclick='schnellkopieren(${JSON.stringify(eintrag)})' style="cursor:default;">
                        <td>${deutschesDatum}</td>
                        <td><strong>${eintrag.buchungsart}</strong></td>
                        <td>${von} - ${bis}</td>
                        <td>${dauer.string}</td>
                        <td>${auftragText}</td>
                        <td>${eintrag.meldungsnummer || '-'}</td>
                        <td>
                            <strong>${eintrag.taetigkeit || '-'}</strong>
                            ${eintrag.anmerkungen ? `<br><small style="color:#718096">${eintrag.anmerkungen}</small>` : ''}
                        </td>
                        <td>
                            <button class="action-btn" title="Bearbeiten" onclick='bearbeiten(${eintrag.id}, ${JSON.stringify(eintrag)})'><i data-lucide="pencil" class="lucide-icon"></i></button>
                            <button class="action-btn" title="Löschen" onclick="loeschen(${eintrag.id})"><i data-lucide="x" class="lucide-icon"></i></button>
                        </td>
                    </tr>
                `);
            });

            // Tagessumme nach jedem Tag
            tableBody.insertAdjacentHTML('beforeend', summenZeile(
                `Σ ${tag.datum.toLocaleDateString('de-DE')}`,
                tagesMinuten,
                'row-day-sum'
            ));
        });

        // Wochensumme nach jeder Woche
        tableBody.insertAdjacentHTML('beforeend', summenZeile(
            `Σ KW ${woche.kw}`,
            wochenMinuten,
            'row-week-sum'
        ));

        // Trennlinie zwischen Wochen
        if (wi < gruppen.length - 1) {
            tableBody.insertAdjacentHTML('beforeend', '<tr class="row-week-separator"><td colspan="8"></td></tr>');
        }
    });

    totalHoursDisplay.innerText = minZuString(gesamtMinutenArbeit);
    refreshIcons();
}
