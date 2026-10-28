/* SSI Endoskeleton — Landingpage-Baukasten: Video erst nach Klick, vergangene Termine ausblenden, Formular und Rückmeldung. */
(function () {
  'use strict';

  // Video: bis zum Klick keine Anfrage an den Anbieter; danach youtube-nocookie im selben Rahmen.
  document.querySelectorAll('.lp-video-start').forEach(function (knopf) {
    knopf.addEventListener('click', function () {
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(knopf.dataset.lpKennung) + '?autoplay=1';
      f.title = knopf.dataset.lpTitel;
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.allowFullscreen = true;
      knopf.replaceWith(f);
      f.focus();
    });
  });

  // Termine: vergangene ausblenden; ist keiner mehr offen, den Ersatztext zeigen.
  var jetzt = Date.now();
  document.querySelectorAll('.lp-termin').forEach(function (block) {
    var offen = 0;
    block.querySelectorAll('[data-lp-ende]').forEach(function (t) {
      if (Date.parse(t.dataset.lpEnde) < jetzt) { t.hidden = true; } else { offen++; }
    });
    if (offen === 0) {
      block.querySelectorAll('.lp-termin-liste, .lp-termin-ort, .lp-termin-preis, .lp-knopf').forEach(function (e) { e.hidden = true; });
      block.querySelector('.lp-termin-leer').hidden = false;
    }
  });

  // Titel: Schriftstufe nach Textmenge (3.59.0). Die Schwellen setzt der Bau in data-lp-stufen (Kontext::TITEL_STUFEN, die einzige Liste), die Stufe
  // von Anfang an data-lp-groesse; hier läuft dieselbe Regel nur nach, wenn sich der Text ändert (Tippen im Bild, Escape). Gezählt wird in Zeichen
  // (Array.from, wie mb_strlen), Leerraum am Rand zählt nicht.
  document.querySelectorAll('[data-lp-stufen]').forEach(function (titel) {
    var grenzen = titel.dataset.lpStufen.split(' ').map(Number);
    new MutationObserver(function () {
      var n = Array.from(titel.textContent.trim()).length;
      titel.dataset.lpGroesse = grenzen.filter(function (g) { return n > g; }).length;
    }).observe(titel, { childList: true, characterData: true, subtree: true });
  });

  // Rückmeldung der Weiterleitungsstelle (?anmeldung=ok|fehler|voll#anmeldung): Meldung zeigen und fokussieren, bei ok und voll ohne
  // Felder; andere Werte wirken nicht. „geschlossen“ (3.54.5): Ist die Anmeldefrist der Liste abgelaufen, kommt ?anmeldung=fehler&grund=geschlossen;
  // dann steht statt des Fehlertexts „Die Anmeldung ist im Moment geschlossen“ da, ohne Felder — die Angaben des Besuchers waren ja in Ordnung.
  // Nur dieser eine Wert von grund wirkt; fehlt der Absatz (Seite aus einer älteren Marke), bleibt es beim bisherigen Fehlertext. „bereits“ gilt als ok (Abwehr in der Tiefe, falls eine Weiterleitungsstelle es
  // noch schickt): Die Seite verrät nicht, ob eine Adresse schon auf der Liste steht. „voll“ (3.52.0) hängt nicht an der
  // Adresse, nur an der Platzgrenze der Liste. Parameter und Sprungmarke verschwinden aus der Adresse — sonst nimmt Chrome
  // beim Sprung zu #anmeldung den Fokus wieder weg, und eine geteilte Adresse zeigte die Meldung statt des Formulars. Damit
  // springt der Browser nicht mehr zur Sprungmarke: Ins Bild bringt die Meldung allein focus() (ohne preventScroll).
  var meldungen = { ok: '.lp-anmeldung-danke', bereits: '.lp-anmeldung-danke', fehler: '.lp-anmeldung-fehler', voll: '.lp-anmeldung-voll' };
  var adresse = new URL(location.href), zustand = adresse.searchParams.get('anmeldung');
  var rueckmeldung = Object.prototype.hasOwnProperty.call(meldungen, zustand);
  var geschlossen = zustand === 'fehler' && adresse.searchParams.get('grund') === 'geschlossen' && document.querySelector('.lp-anmeldung-geschlossen') !== null;

  // Formular: Pflichtfelder prüfen, Fehler am Feld zeigen, das erste Fehlerfeld fokussieren.
  // Ohne JavaScript prüft der Browser selbst (required); mit JavaScript übernehmen die eigenen Meldungen.
  document.querySelectorAll('form.lp-formular').forEach(function (form) {
    var meldung = rueckmeldung && form.querySelector(geschlossen ? '.lp-anmeldung-geschlossen' : meldungen[zustand]);
    if (meldung) {
      meldung.hidden = false;
      if (zustand !== 'fehler' || geschlossen) { form.querySelector('fieldset').hidden = true; }
      adresse.searchParams.delete('anmeldung'); adresse.searchParams.delete('grund'); adresse.hash = '';
      try { history.replaceState(history.state, '', adresse.href); } catch (e) { /* ohne Verlauf nur ohne Fokus */ }
      meldung.focus();
    }
    form.noValidate = true;
    form.addEventListener('submit', function (ereignis) {
      var erstes = null;
      form.querySelectorAll('[required]').forEach(function (feld) {
        var ok = feld.type === 'checkbox' ? feld.checked : feld.value.trim() !== '' && feld.checkValidity();
        feld.setAttribute('aria-invalid', ok ? 'false' : 'true');
        var fehler = document.getElementById(feld.id + '-fehler');
        if (fehler) { fehler.hidden = ok; }
        if (ok) { feld.removeAttribute('aria-describedby'); } else if (fehler) { feld.setAttribute('aria-describedby', fehler.id); }
        if (!ok && !erstes) { erstes = feld; }
      });
      if (erstes) { ereignis.preventDefault(); erstes.focus(); }
    });
  });

  // Plätze (3.52.0): Ist die Anmeldung begrenzt, fragt die Seite den Stand beim Panel ab — nur Zahlen, ohne Cookies. Der Host
  // kommt aus dem Formularziel (data-lp-plaetze-quelle), nie fest aus diesem Skript. Bei „voll“ verschwinden die Felder;
  // scheitert die Abfrage (Netz, Drosselung, Sperre), bleibt der feste Text „Begrenzt auf N Plätze.“ stehen. Nach einer
  // Rückmeldung ohne Felder (ok, bereits, voll, geschlossen) ist die Zahl überholt: keine Abfrage, keine Anzeige — /lp/plaetze ist im
  // Panel bis 60 s zwischengespeichert, /lp/anmelden zählt live, und sonst stünde „Noch 1 … frei“ neben „ausgebucht“.
  // Nach „fehler“ wird gefragt; ist die Liste voll, ersetzt die Meldung „ausgebucht“ die Aufforderung, es noch einmal zu versuchen.
  document.querySelectorAll('.lp-anmeldung-plaetze[data-lp-plaetze-quelle]').forEach(function (anzeige) {
    if (rueckmeldung && (zustand !== 'fehler' || geschlossen)) { anzeige.hidden = true; return; }
    if (!window.fetch) { return; }
    var form = anzeige.closest('form');
    fetch(anzeige.dataset.lpPlaetzeQuelle + '?liste=' + encodeURIComponent(anzeige.dataset.lpPlaetzeListe), { credentials: 'omit' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || typeof d.frei !== 'number' || typeof d.gesamt !== 'number') { return; }
        if (d.voll === true) {
          var felder = form && form.querySelector('fieldset');
          if (felder) { felder.hidden = true; }
          var fehler = form && form.querySelector('.lp-anmeldung-fehler'), voll = form && form.querySelector('.lp-anmeldung-voll');
          if (fehler && voll && !fehler.hidden) {
            fehler.hidden = true; anzeige.hidden = true; voll.hidden = false; voll.focus();
            return;
          }
          anzeige.textContent = 'Ausgebucht';
          return;
        }
        anzeige.textContent = 'Noch ' + d.frei + ' von ' + d.gesamt + (d.gesamt === 1 ? ' Platz' : ' Plätzen') + ' frei';
      })
      .catch(function () { /* der feste Text bleibt stehen */ });
  });

  // Statistik (3.56.0): Zählt anonym Aufruf, Formularbeginn und Absenden beim Panel — ohne Cookies, ohne Web-Speicher (die
  // Einmal-Sperren sind Variablen dieser Seite) und ohne Kennung des Besuchers: nur Verweiser-Host, UTM und Breite. Die Adresse
  // kommt aus dem Formularziel (…/lp/anmelden → …/lp/treffer); eine Seite ohne Anmeldeformular sendet nichts. Do Not Track,
  // Global Privacy Control und data-lp-statistik="aus" am body schalten die Messung ab. Jeder Fehler wird verschluckt. Das
  // Absenden zählt erst, wenn die Prüfung oben es durchgelassen hat (dieser Block steht nach ihr); die Rückkehrseite einer
  // Anmeldung (?anmeldung=…) ist schon gezählt. Verlassen (3.57.0): Beim ersten pagehide oder Verstecken des Tabs geht einmal
  // „verlassen“ mit dauer (ganze Sekunden sichtbarer Zeit, 0–3600) und tiefe (weitester unterer Fensterrand in Prozent der
  // Seitenhöhe, auf 0|25|50|75|100 gerundet) hinaus; ohne gezählten aufruf (nie sichtbar, Rückkehrseite) nichts.
  try {
    var form = document.querySelector('form.lp-formular'), liste = form && form.querySelector('input[name="liste"]');
    var aktion = form && form.getAttribute('action'), ziel = aktion && new URL(aktion, location.href);
    if (liste && ziel && /\/anmelden$/.test(ziel.pathname) && navigator.sendBeacon && navigator.doNotTrack !== '1'
      && navigator.globalPrivacyControl !== true && document.body.dataset.lpStatistik !== 'aus') {
      ziel.pathname = ziel.pathname.replace(/anmelden$/, 'treffer'); ziel.search = ''; ziel.hash = '';
      var suche = new URLSearchParams(location.search), gesendet = {};
      var utm = function (name) { return (suche.get('utm_' + name) || '').replace(/[^A-Za-z0-9 _.-]/g, '').slice(0, 60); };
      var melden = function (art, dauer, tiefe) {
        if (gesendet[art]) { return; }
        gesendet[art] = true;
        try {
          var verweiser = document.referrer ? new URL(document.referrer).hostname : '';
          navigator.sendBeacon(ziel.href, new Blob([JSON.stringify({
            v: 1, liste: liste.value.slice(0, 60), art: art, pfad: location.pathname.slice(0, 120),
            ref: verweiser === location.hostname ? '' : verweiser.slice(0, 100),
            utm: { quelle: utm('source'), medium: utm('medium'), kampagne: utm('campaign') }, breite: window.innerWidth,
            dauer: dauer, tiefe: tiefe
          })], { type: 'text/plain;charset=UTF-8' }));
        } catch (e) { /* die Statistik darf die Anmeldung nie stören */ }
      };
      var seit = null, unten = 0;
      var sichtbar = function () {
        if (document.visibilityState === 'visible') { document.removeEventListener('visibilitychange', sichtbar); seit = performance.now(); melden('aufruf'); }
      };
      if (!rueckmeldung) { document.addEventListener('visibilitychange', sichtbar); sichtbar(); }
      form.addEventListener('focusin', function (ereignis) { if (ereignis.target.closest && !ereignis.target.closest('.form-hp')) { melden('formular'); } });
      form.addEventListener('submit', function (ereignis) { if (!ereignis.defaultPrevented) { melden('absenden'); } });
      // Scroll-Ereignisse kommen höchstens einmal je Bildwechsel; gelesen wird nur die Position, die Seitenhöhe erst beim Verlassen.
      var tiefer = function () { unten = Math.max(unten, window.scrollY + window.innerHeight); };
      var gehen = function () {
        try {
          if (seit === null) { return; }
          tiefer();
          melden('verlassen', Math.min(3600, Math.round((performance.now() - seit) / 1000)),
            Math.round(Math.min(1, unten / Math.max(1, document.documentElement.scrollHeight)) * 4) * 25);
        } catch (e) { /* die Statistik darf das Verlassen nie stören */ }
      };
      window.addEventListener('scroll', tiefer, { passive: true });
      window.addEventListener('pagehide', gehen);
      document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { gehen(); } });
    }
  } catch (e) { /* die Statistik darf die Seite nie stören */ }
})();
