/* SSI Endoskeleton — Besucherzähler für Webseiten (3.65.0). Zählt anonym beim Panel: einen Aufruf je Seite, bei jedem Verlassen die sichtbare Zeit und die Scrolltiefe,
   nach längerer Abwesenheit ein „da“. Ohne Cookies, ohne Web-Speicher, ohne Kennung des Besuchers — gesendet werden nur Verweiser-Host, UTM, Fensterbreite und Seitenpfad.
   Einbau, eine Zeile unmittelbar vor </body> (Domain klein und ohne www., wie im Panel unter „Meine Webseite“ eingetragen):
     <script src="statistik.js" data-quelle="web:beispiel.at" defer></script>
   Gesendet wird nur auf der Domain selbst (oder www.<Domain>): Vorschau, lokale Kopien und Sicherungen zählen nie mit. Do Not Track, Global Privacy
   Control und data-statistik="aus" am <body> schalten den Zähler ab; jeder Fehler wird verschluckt, die Seite bleibt ohne dieses Skript voll nutzbar.
   Vertrag v2 (Teil 1) wie bei den Landingpages (lp/lp.js), nur ohne Formular: art aufruf | verlassen | da. aufruf einmal je Seitenaufruf, sobald die Seite sichtbar ist.
   verlassen bei jedem Verstecken des Tabs und beim Verlassen der Seite, nie zweimal ohne Rückkehr dazwischen — dauer: ganze Sekunden sichtbar seit dem letzten Senden
   (mindestens 1, alle zusammen höchstens 3600); tiefe: bisher weitester unterer Fensterrand in Prozent der Seitenhöhe, auf 0|25|50|75|100 gerundet. da: der Tab ist nach
   über 60 Sekunden wieder sichtbar, ohne dauer und tiefe. Das Panel antwortet immer 204 und verwirft Bots und „Nicht verfolgen“ selbst; ein Panel, das nur v1 kennt, verwirft v2. */
(function () {
  'use strict';
  try {
    var skript = document.currentScript;
    var quelle = /^web:((?=.*\.)(?!.*\.\.)[a-z0-9][a-z0-9.-]{0,54}[a-z0-9])$/.exec(skript ? skript.dataset.quelle || '' : '');
    var domain = quelle && quelle[1], host = location.hostname;
    if (!domain || (host !== domain && host !== 'www.' + domain) || !navigator.sendBeacon || navigator.doNotTrack === '1'
      || navigator.globalPrivacyControl === true || !document.body || document.body.dataset.statistik === 'aus') { return; }

    var suche = new URLSearchParams(location.search), aufgerufen = false, seit = null, weg = 0, summe = 0, unten = 0;
    var utm = function (name) { return (suche.get('utm_' + name) || '').replace(/[^A-Za-z0-9 _.-]/g, '').slice(0, 60); };
    var melden = function (art, dauer, tiefe) {
      try {
        var verweiser = document.referrer ? new URL(document.referrer).hostname : '';
        navigator.sendBeacon('https://panel.ssi.at/lp/treffer', new Blob([JSON.stringify({
          v: 2, liste: quelle[0], art: art, pfad: location.pathname.slice(0, 120),
          ref: verweiser.replace(/^www\./, '') === domain ? '' : verweiser.slice(0, 100),
          utm: { quelle: utm('source'), medium: utm('medium'), kampagne: utm('campaign') }, breite: window.innerWidth,
          dauer: dauer, tiefe: tiefe
        })], { type: 'text/plain;charset=UTF-8' }));
      } catch (e) { /* der Zähler darf die Seite nie stören */ }
    };

    // Sichtbar: die Uhr läuft. Das erste Mal ist der Aufruf (ein vorab geladener Tab zählt nicht), später nach über 60 s Abwesenheit ein „da“.
    var sichtbar = function () {
      if (document.visibilityState !== 'visible' || seit !== null) { return; }
      seit = performance.now();
      if (!aufgerufen) { aufgerufen = true; melden('aufruf'); } else if (seit - weg > 60000) { melden('da'); }
    };
    document.addEventListener('visibilitychange', sichtbar);
    sichtbar();

    // Verlassen: bei pagehide und Verstecken, solange die Uhr läuft; danach steht sie bis zur Rückkehr (beide hintereinander senden einmal).
    var tiefer = function () { unten = Math.max(unten, window.scrollY + window.innerHeight); };
    var gehen = function () {
      try {
        if (seit === null) { return; }
        tiefer();
        var tiefe = Math.round(Math.min(1, unten / Math.max(1, document.documentElement.scrollHeight)) * 4) * 25, jetzt = performance.now();
        var dauer = Math.min(3600 - summe, Math.max(1, Math.round((jetzt - seit) / 1000)));
        summe += dauer; seit = null; weg = jetzt;
        melden('verlassen', dauer, tiefe);
      } catch (e) { /* der Zähler darf das Verlassen nie stören */ }
    };
    window.addEventListener('scroll', tiefer, { passive: true });
    window.addEventListener('pagehide', gehen);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { gehen(); } });
  } catch (e) { /* der Zähler darf die Seite nie stören */ }
})();
