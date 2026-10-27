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

  // Rückmeldung der Weiterleitungsstelle (?anmeldung=ok|fehler|voll#anmeldung): Meldung zeigen und fokussieren, bei ok und voll ohne
  // Felder; andere Werte wirken nicht. „bereits“ gilt als ok (Abwehr in der Tiefe, falls eine Weiterleitungsstelle es
  // noch schickt): Die Seite verrät nicht, ob eine Adresse schon auf der Liste steht. „voll“ (3.52.0) hängt nicht an der
  // Adresse, nur an der Platzgrenze der Liste. Parameter und Sprungmarke verschwinden aus der Adresse — sonst nimmt Chrome
  // beim Sprung zu #anmeldung den Fokus wieder weg, und eine geteilte Adresse zeigte die Meldung statt des Formulars. Damit
  // springt der Browser nicht mehr zur Sprungmarke: Ins Bild bringt die Meldung allein focus() (ohne preventScroll).
  var meldungen = { ok: '.lp-anmeldung-danke', bereits: '.lp-anmeldung-danke', fehler: '.lp-anmeldung-fehler', voll: '.lp-anmeldung-voll' };
  var adresse = new URL(location.href), zustand = adresse.searchParams.get('anmeldung');
  var rueckmeldung = Object.prototype.hasOwnProperty.call(meldungen, zustand);

  // Formular: Pflichtfelder prüfen, Fehler am Feld zeigen, das erste Fehlerfeld fokussieren.
  // Ohne JavaScript prüft der Browser selbst (required); mit JavaScript übernehmen die eigenen Meldungen.
  document.querySelectorAll('form.lp-formular').forEach(function (form) {
    var meldung = rueckmeldung && form.querySelector(meldungen[zustand]);
    if (meldung) {
      meldung.hidden = false;
      if (zustand !== 'fehler') { form.querySelector('fieldset').hidden = true; }
      adresse.searchParams.delete('anmeldung'); adresse.hash = '';
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
  // Rückmeldung ohne Felder (ok, bereits, voll) ist die Zahl überholt: keine Abfrage, keine Anzeige — /lp/plaetze ist im
  // Panel bis 60 s zwischengespeichert, /lp/anmelden zählt live, und sonst stünde „Noch 1 … frei“ neben „ausgebucht“.
  // Nach „fehler“ wird gefragt; ist die Liste voll, ersetzt die Meldung „ausgebucht“ die Aufforderung, es noch einmal zu versuchen.
  document.querySelectorAll('.lp-anmeldung-plaetze[data-lp-plaetze-quelle]').forEach(function (anzeige) {
    if (rueckmeldung && zustand !== 'fehler') { anzeige.hidden = true; return; }
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
  // Anmeldung (?anmeldung=…) ist schon gezählt.
  try {
    var form = document.querySelector('form.lp-formular'), liste = form && form.querySelector('input[name="liste"]');
    var aktion = form && form.getAttribute('action'), ziel = aktion && new URL(aktion, location.href);
    if (liste && ziel && /\/anmelden$/.test(ziel.pathname) && navigator.sendBeacon && navigator.doNotTrack !== '1'
      && navigator.globalPrivacyControl !== true && document.body.dataset.lpStatistik !== 'aus') {
      ziel.pathname = ziel.pathname.replace(/anmelden$/, 'treffer'); ziel.search = ''; ziel.hash = '';
      var suche = new URLSearchParams(location.search), gesendet = {};
      var utm = function (name) { return (suche.get('utm_' + name) || '').replace(/[^A-Za-z0-9 _.-]/g, '').slice(0, 60); };
      var melden = function (art) {
        if (gesendet[art]) { return; }
        gesendet[art] = true;
        try {
          var verweiser = document.referrer ? new URL(document.referrer).hostname : '';
          navigator.sendBeacon(ziel.href, new Blob([JSON.stringify({
            v: 1, liste: liste.value.slice(0, 60), art: art, pfad: location.pathname.slice(0, 120),
            ref: verweiser === location.hostname ? '' : verweiser.slice(0, 100),
            utm: { quelle: utm('source'), medium: utm('medium'), kampagne: utm('campaign') }, breite: window.innerWidth
          })], { type: 'text/plain;charset=UTF-8' }));
        } catch (e) { /* die Statistik darf die Anmeldung nie stören */ }
      };
      var sichtbar = function () {
        if (document.visibilityState === 'visible') { document.removeEventListener('visibilitychange', sichtbar); melden('aufruf'); }
      };
      if (!rueckmeldung) { document.addEventListener('visibilitychange', sichtbar); sichtbar(); }
      form.addEventListener('focusin', function (ereignis) { if (ereignis.target.closest && !ereignis.target.closest('.form-hp')) { melden('formular'); } });
      form.addEventListener('submit', function (ereignis) { if (!ereignis.defaultPrevented) { melden('absenden'); } });
    }
  } catch (e) { /* die Statistik darf die Seite nie stören */ }
})();
