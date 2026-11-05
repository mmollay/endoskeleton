/* Admin-Weg (Vorlage aus dem SSI Endoskeleton): schickt Formulare mit data-admin-fetch per fetch an den Server und zeigt die Antwort.
   Kein Schlüssel im Browser — die Berechtigung ist die Sitzung (Cookie HttpOnly, für JavaScript unsichtbar) plus das CSRF-Token
   der Seite aus <meta name="csrf-token">. Ohne JavaScript bleiben es gewöhnliche POST-Formulare. */
(function () {
  'use strict';
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (!meta) { return; }
  document.querySelectorAll('form[data-admin-fetch]').forEach(function (formular) {
    const ausgabe = formular.querySelector('[data-admin-meldung]');
    const knopf = formular.querySelector('button[type="submit"]');
    const zeigen = function (text) { if (ausgabe) { ausgabe.textContent = text; } };
    formular.addEventListener('submit', function (ereignis) {
      ereignis.preventDefault();
      if (knopf) { knopf.disabled = true; }
      fetch(formular.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': meta.content, 'Accept': 'application/json' },
        body: new FormData(formular)
      })
        .then(function (antwort) {
          return antwort.json().catch(function () { return { ok: false, fehler: 'Unerwartete Antwort (' + antwort.status + ').' }; });
        })
        .then(function (daten) { zeigen(daten.ok ? (daten.meldung || 'Gespeichert.') : (daten.fehler || 'Fehler.')); })
        .catch(function () { zeigen('Keine Verbindung zum Server.'); })
        .then(function () { if (knopf) { knopf.disabled = false; } });
    });
  });
})();
