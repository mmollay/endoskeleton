/* SSI Endoskeleton — Landingpage-Baukasten: Video erst nach Klick, vergangene Termine ausblenden, Formularprüfung. */
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
      block.querySelectorAll('.lp-termin-liste, .lp-termin-ort, .lp-knopf').forEach(function (e) { e.hidden = true; });
      block.querySelector('.lp-termin-leer').hidden = false;
    }
  });

  // Formular: Pflichtfelder prüfen, Fehler am Feld zeigen, das erste Fehlerfeld fokussieren.
  // Ohne JavaScript prüft der Browser selbst (required); mit JavaScript übernehmen die eigenen Meldungen.
  document.querySelectorAll('form.lp-formular').forEach(function (form) {
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
})();
