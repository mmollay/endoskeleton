/* SSI Endoskeleton — Besucherzähler für Webseiten (3.63.0). Zählt anonym beim Panel: einen Aufruf je Seite und beim Verlassen die sichtbare Zeit und die
   Scrolltiefe. Ohne Cookies, ohne Web-Speicher, ohne Kennung des Besuchers — gesendet werden nur Verweiser-Host, UTM, Fensterbreite und Seitenpfad.
   Einbau, eine Zeile unmittelbar vor </body> (Domain klein und ohne www., wie im Panel unter „Meine Webseite“ eingetragen):
     <script src="statistik.js" data-quelle="web:beispiel.at" defer></script>
   Gesendet wird nur auf der Domain selbst (oder www.<Domain>): Vorschau, lokale Kopien und Sicherungsordner zählen nie mit. Do Not Track, Global Privacy
   Control und data-statistik="aus" am <body> schalten den Zähler ab; jeder Fehler wird verschluckt, die Seite bleibt ohne dieses Skript voll nutzbar.
   Vertrag v1 wie bei den Landingpages (lp/lp.js), nur ohne Formular: art aufruf | verlassen — dauer: ganze Sekunden sichtbar (0–3600); tiefe: weitester
   unterer Fensterrand in Prozent der Seitenhöhe, auf 0|25|50|75|100 gerundet. Das Panel antwortet immer 204 und verwirft Bots und „Nicht verfolgen“ selbst. */
(function () {
  'use strict';
  try {
    var skript = document.currentScript;
    var quelle = /^web:((?=.*\.)(?!.*\.\.)[a-z0-9][a-z0-9.-]{0,54}[a-z0-9])$/.exec(skript ? skript.dataset.quelle || '' : '');
    var domain = quelle && quelle[1], host = location.hostname;
    if (!domain || (host !== domain && host !== 'www.' + domain) || !navigator.sendBeacon || navigator.doNotTrack === '1'
      || navigator.globalPrivacyControl === true || !document.body || document.body.dataset.statistik === 'aus') { return; }

    var suche = new URLSearchParams(location.search), gesendet = {}, seit = null, unten = 0;
    var utm = function (name) { return (suche.get('utm_' + name) || '').replace(/[^A-Za-z0-9 _.-]/g, '').slice(0, 60); };
    var melden = function (art, dauer, tiefe) {
      if (gesendet[art]) { return; }
      gesendet[art] = true;
      try {
        var verweiser = document.referrer ? new URL(document.referrer).hostname : '';
        navigator.sendBeacon('https://panel.ssi.at/lp/treffer', new Blob([JSON.stringify({
          v: 1, liste: quelle[0], art: art, pfad: location.pathname.slice(0, 120),
          ref: verweiser.replace(/^www\./, '') === domain ? '' : verweiser.slice(0, 100),
          utm: { quelle: utm('source'), medium: utm('medium'), kampagne: utm('campaign') }, breite: window.innerWidth,
          dauer: dauer, tiefe: tiefe
        })], { type: 'text/plain;charset=UTF-8' }));
      } catch (e) { /* der Zähler darf die Seite nie stören */ }
    };

    // Aufruf erst, wenn die Seite sichtbar ist (ein vorab geladener Tab zählt nicht); ab dann läuft die sichtbare Zeit.
    var sichtbar = function () {
      if (document.visibilityState === 'visible') { document.removeEventListener('visibilitychange', sichtbar); seit = performance.now(); melden('aufruf'); }
    };
    document.addEventListener('visibilitychange', sichtbar);
    sichtbar();

    // Verlassen: beim ersten pagehide oder Verstecken des Tabs einmal; ohne gezählten Aufruf nichts. Gelesen wird die Seitenhöhe erst jetzt.
    var tiefer = function () { unten = Math.max(unten, window.scrollY + window.innerHeight); };
    var gehen = function () {
      try {
        if (seit === null) { return; }
        tiefer();
        melden('verlassen', Math.min(3600, Math.round((performance.now() - seit) / 1000)),
          Math.round(Math.min(1, unten / Math.max(1, document.documentElement.scrollHeight)) * 4) * 25);
      } catch (e) { /* der Zähler darf das Verlassen nie stören */ }
    };
    window.addEventListener('scroll', tiefer, { passive: true });
    window.addEventListener('pagehide', gehen);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { gehen(); } });
  } catch (e) { /* der Zähler darf die Seite nie stören */ }
})();
