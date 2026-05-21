// icons.js – Lucide Icons Hilfsfunktionen

// Nach dynamischen DOM-Änderungen Icons neu rendern
function refreshIcons() {
    if (window.lucide) {
        lucide.createIcons();
    } else {
        setTimeout(refreshIcons, 50);
    }
}
