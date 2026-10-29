/* ═══════════════════════════════════════════════════════════════════════════
   SSI Endoskeleton Module — newsletter.js
   Newsletter-Anmeldung direkt über dem Fuß der Seite (Sprungmarke #anmeldung).

   Einbau: modules: ["newsletter"] und newsletter: { liste: "…" } in SITE_CONFIG der Seite.
   Das Formular geht als normaler POST an das Panel (panel.ssi.at/lp/anmelden), das prüft, die
   Bestätigungsmail schickt (doppelte Bestätigung) und auf <Seite>?anmeldung=ok|fehler#anmeldung zurückleitet.
   Gruppe und Absender gehören NICHT in die Seite — sie stehen im Panel bei der Liste (Kennung = liste).
   Alle Schlüssel und der Ablauf stehen in _endoskeleton/_newsletter.html; die Stile in newsletter.css.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var ZIEL = 'https://panel.ssi.at/lp/anmelden';
  /* Der Vertrag nach außen kennt nur ok und fehler — die Seite verrät nie, ob eine Adresse schon auf der Liste steht. */
  var ZUSATZ = 'Falls Sie schon angemeldet waren, bekommen Sie keine neue E-Mail.';
  var STANDARD = {
    liste: '',
    tag: 'Newsletter',
    titel: 'Bleiben Sie informiert',
    einleitung: 'Neuigkeiten und Angebote direkt in Ihr Postfach.',
    einwilligung: 'Ich möchte den Newsletter erhalten und kann mich jederzeit abmelden.',
    datenschutz: 'datenschutz.html#newsletter',
    knopf: 'Anmelden',
    danke: 'Vielen Dank! Bitte bestätigen Sie Ihre Anmeldung über den Link in der E-Mail, die wir Ihnen schicken. Kommt keine an, sehen Sie bitte auch im Spam-Ordner nach.',
    fehler: 'Das hat leider nicht geklappt. Bitte prüfen Sie Ihre Angaben und versuchen Sie es noch einmal.'
  };
  var RUECKMELDUNG = { ok: 'ok', bereits: 'ok', fehler: 'fehler' };

  var cfg = Object.assign({}, STANDARD, (window.SITE_CONFIG || {}).newsletter);
  var skript = document.currentScript;
  var fuss = document.querySelector('#site-footer, .site-footer');

  if (!cfg.liste) { console.warn('[Endoskeleton] newsletter: SITE_CONFIG.newsletter.liste fehlt.'); return; }
  /* Ohne WebCrypto (Seite nicht über https) ließe sich der Nachweis nicht bilden — dann lieber kein Formular als ein totes. */
  if (!fuss || !window.crypto || !crypto.subtle) { console.warn('[Endoskeleton] newsletter: kein Fuß oder kein https — Baustein bleibt aus.'); return; }

  function e(text) {
    return String(text).replace(/[&<>"']/g, function (z) { return '&#' + z.charCodeAt(0) + ';'; });
  }

  /* Die ersten 12 Hex-Zeichen von SHA-256 des Einwilligungstexts: der Nachweis, welchen Wortlaut die Person gesehen hat. */
  function kurzsumme(text) {
    return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (puffer) {
      return Array.prototype.map.call(new Uint8Array(puffer), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('').slice(0, 12);
    });
  }

  /* Ein Eingabefeld mit Label; mit fehlertext ist es Pflicht und bekommt seinen Fehlertext (erst nach dem Absenden sichtbar). */
  function feld(name, label, art, auto, max, fehlertext) {
    return '<div class="form-group">'
      + '<label for="newsletter-' + name + '">' + label + '</label>'
      + '<input id="newsletter-' + name + '" name="' + name + '" type="' + art + '" autocomplete="' + auto + '" maxlength="' + max + '"' + (fehlertext ? ' required' : '') + '>'
      + (fehlertext ? '<p class="form-error" id="newsletter-' + name + '-fehler" hidden>' + fehlertext + '</p>' : '')
      + '</div>';
  }

  function abschnitt() {
    return '<section class="newsletter" id="anmeldung" aria-labelledby="newsletter-titel">'
      + '<div class="container-narrow">'
      + '<div class="text-center">'
      + (cfg.tag ? '<p class="section-tag">' + e(cfg.tag) + '</p>' : '')
      + '<h2 class="section-title" id="newsletter-titel">' + e(cfg.titel) + '</h2>'
      + (cfg.einleitung ? '<p class="section-lead">' + e(cfg.einleitung) + '</p>' : '')
      + '</div>'
      + '<div class="newsletter-karte">'
      + '<form method="post" action="' + ZIEL + '" novalidate>'
      + '<div class="newsletter-felder">'
      + '<div class="form-row">'
      + feld('vorname', 'Vorname', 'text', 'given-name', 50, 'Bitte geben Sie Ihren Vornamen ein.')
      + feld('nachname', 'Nachname (freiwillig)', 'text', 'family-name', 50, '')
      + '</div>'
      + feld('email', 'E-Mail-Adresse', 'email', 'email', 80, 'Bitte geben Sie eine gültige E-Mail-Adresse ein.')
      + '<div class="newsletter-einwilligung">'
      + '<div class="form-checkbox">'
      + '<input id="newsletter-einwilligung" name="einwilligung" type="checkbox" value="ja" required>'
      + '<label for="newsletter-einwilligung">' + e(cfg.einwilligung) + ' <a href="' + e(cfg.datenschutz) + '">Mehr dazu in der Datenschutzerklärung.</a></label>'
      + '</div>'
      + '<p class="form-error" id="newsletter-einwilligung-fehler" hidden>Bitte bestätigen Sie die Einwilligung.</p>'
      + '</div>'
      + '<div class="form-hp" aria-hidden="true"><label for="newsletter-website">Website</label>'
      + '<input id="newsletter-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
      + '<input type="hidden" name="liste" value="' + e(cfg.liste) + '">'
      + '<input type="hidden" name="einwilligung_kurz" value="">'
      + '<button type="submit" class="btn btn-primary">' + e(cfg.knopf) + '</button>'
      + '</div>'
      + '</form>'
      + '</div>'
      + '</div>'
      + '</section>';
  }

  /* Meldung nach der Rückleitung des Panels: ok blendet die Felder aus, fehler lässt sie stehen. */
  function meldungZeigen(abschn, art) {
    var kasten = document.createElement('div');
    kasten.className = 'newsletter-meldung newsletter-meldung--' + art;
    kasten.setAttribute('role', 'status');
    kasten.setAttribute('aria-live', 'polite');
    kasten.tabIndex = -1;
    kasten.innerHTML = '<p>' + e(art === 'ok' ? cfg.danke : cfg.fehler) + '</p><p class="newsletter-nachsatz">' + e(ZUSATZ) + '</p>';
    abschn.querySelector('.newsletter-karte').insertBefore(kasten, abschn.querySelector('form'));
    if (art === 'ok') { abschn.querySelector('.newsletter-felder').hidden = true; }
    /* Parameter und Sprungmarke weg: ein Neuladen oder eine geteilte Adresse zeigt wieder das Formular. */
    var adresse = new URL(location.href);
    adresse.searchParams.delete('anmeldung');
    adresse.hash = '';
    try { history.replaceState(history.state, '', adresse.href); } catch (ausnahme) { /* ohne Verlauf bleibt die Adresse */ }
    kasten.focus();
  }

  /* Pflichtfelder prüfen, Fehler am Feld zeigen (mit aria-describedby), das erste Fehlerfeld fokussieren. */
  function felderPruefen(form) {
    var erstes = null;
    form.querySelectorAll('[required]').forEach(function (f) {
      var ok = f.type === 'checkbox' ? f.checked : f.value.trim() !== '' && f.checkValidity();
      var fehler = document.getElementById(f.id + '-fehler');
      f.setAttribute('aria-invalid', ok ? 'false' : 'true');
      fehler.hidden = ok;
      if (ok) { f.removeAttribute('aria-describedby'); } else { f.setAttribute('aria-describedby', fehler.id); }
      if (!ok && !erstes) { erstes = f; }
    });
    if (erstes) { erstes.focus(); }
    return !erstes;
  }

  function aufbauen() {
    fuss.insertAdjacentHTML('beforebegin', abschnitt());
    var abschn = document.getElementById('anmeldung');
    var form = abschn.querySelector('form');
    var bereit = kurzsumme(cfg.einwilligung).then(function (summe) { form.elements.einwilligung_kurz.value = summe; });
    form.addEventListener('submit', function (ereignis) {
      ereignis.preventDefault();
      if (felderPruefen(form)) { bereit.then(function () { form.submit(); }); }
    });
    var art = new URL(location.href).searchParams.get('anmeldung');
    if (Object.prototype.hasOwnProperty.call(RUECKMELDUNG, art)) { meldungZeigen(abschn, RUECKMELDUNG[art]); }
  }

  /* Erst mit dem Stylesheet aufbauen, sonst blitzt das Formular ungestaltet auf; ohne Stylesheet trotzdem (es bleibt benutzbar). */
  var stile = document.createElement('link');
  stile.rel = 'stylesheet';
  stile.href = skript && skript.src ? skript.src.replace(/\.js$/, '.css') : 'modules/newsletter.css';
  stile.onload = stile.onerror = aufbauen;
  document.head.appendChild(stile);
})();
