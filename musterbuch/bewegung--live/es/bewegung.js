/* SSI Endoskeleton 3.64.0 — Bewegungs-Player: spielt eine Bewegungsdatei „bewegung v1“ (docs/bewegung-format.md) als Strichfigur oder Punkte.
   Ohne Kamera, ohne Modell, ohne Abhängigkeit. Drei getrennte Teile: pruefen() (dieselben Regeln wie lp/src/Bewegung.php, Test BW04),
   zeichne() (reine Zeichenfunktion: kennt keine Zeit — derselbe Code rendert später Shorts) und spielen() (Zeitsteuerung). Jeder Fehler wird
   verschluckt: die Seite bleibt benutzbar, sie zeigt dann den Hinweis „fehler“. */
(function (g) {
  'use strict';
  var NAMEN = ('nase auge-links-innen auge-links auge-links-aussen auge-rechts-innen auge-rechts auge-rechts-aussen ohr-links ohr-rechts mund-links mund-rechts '
      + 'schulter-links schulter-rechts ellbogen-links ellbogen-rechts handgelenk-links handgelenk-rechts kleiner-finger-links kleiner-finger-rechts '
      + 'zeigefinger-links zeigefinger-rechts daumen-links daumen-rechts huefte-links huefte-rechts knie-links knie-rechts knoechel-links knoechel-rechts '
      + 'ferse-links ferse-rechts fussspitze-links fussspitze-rechts').split(' '),
    SCHL = ['v', 'name', 'quelle', 'verhaeltnis', 'fps', 'dim', 'dauer_ms', 'punkte', 'rahmen'],
    QUELLEN = ['eigene-aufnahme', 'einwilligung', 'synthetisch'],
    GESICHT = [1, 2, 3, 4, 5, 6, 9, 10],   // Augen und Mund: nie gespeichert
    MAX_BYTES = 300000,
    // Verbindungen der Strichfigur (Teil des Players, nicht der Datei): Schultern, Arme, Hände, Rumpf, Beine, Füße
    VERB = [11, 12, 11, 13, 13, 15, 12, 14, 14, 16, 15, 17, 15, 19, 17, 19, 16, 18, 16, 20, 18, 20, 11, 23, 12, 24, 23, 24, 23, 25, 25, 27, 24, 26, 26, 28,
      27, 29, 29, 31, 27, 31, 28, 30, 30, 32, 28, 32];

  function zahl(v) { return typeof v === 'number' && isFinite(v); }
  function ganz(v) { return zahl(v) && Math.floor(v) === v; }
  function stellen(v, n) { var m = Math.pow(10, n); return Math.abs(Math.round(v * m) - v * m) < 1e-6; }

  /* Gibt die Kennung des ersten Fehlers zurück (Texte: PHP Bewegung::TEXTE) oder null. Reihenfolge wie in PHP. */
  function pruefen(d, bytes) {
    var k, r, i, j, f, w, s, v, je, dauer;
    if (bytes > MAX_BYTES) return 'groesse';
    if (!d || typeof d !== 'object' || Array.isArray(d)) return 'form';
    k = Object.keys(d);
    if (k.length !== SCHL.length || SCHL.some(function (x) { return k.indexOf(x) < 0; })) return 'schluessel';
    if (!ganz(d.v) || d.v !== 1) return 'v';
    if (typeof d.name !== 'string' || !/^[\p{L}\p{N}][\p{L}\p{N} .,:()+_\/–\-]{0,59}$/u.test(d.name)) return 'name';
    if (QUELLEN.indexOf(d.quelle) < 0) return 'quelle';
    if (!zahl(d.verhaeltnis) || d.verhaeltnis < 0.25 || d.verhaeltnis > 4 || !stellen(d.verhaeltnis, 4)) return 'verhaeltnis';
    if (!ganz(d.fps) || d.fps < 10 || d.fps > 60) return 'fps';
    if (!ganz(d.dim) || (d.dim !== 2 && d.dim !== 3)) return 'dim';
    if (JSON.stringify(d.punkte) !== JSON.stringify(NAMEN)) return 'punkte';
    r = d.rahmen;
    if (!Array.isArray(r) || r.length < 2 || r.length > 600) return 'rahmen.anzahl';
    je = d.dim + 1;
    for (i = 0; i < r.length; i++) {
      f = r[i];
      if (!Array.isArray(f) || f.length !== 33 * je) return 'rahmen.laenge';
      for (j = 0; j < 33; j++) {
        w = f.slice(j * je, j * je + je);
        for (k = 0; k < je; k++) {
          v = w[k];
          if (!zahl(v)) return 'wert';
          if (v < (je === 4 && k === 2 ? -1 : 0) || v > 1) return 'bereich';
          if (!stellen(v, 3)) return 'stellen';
        }
        s = w[je - 1];
        if (s === 0 && w.some(function (x) { return x !== 0; })) return 'unsichtbar';
        if (s !== 0 && GESICHT.indexOf(j) >= 0) return 'gesicht';
      }
    }
    dauer = Math.round(r.length * 1000 / d.fps);
    if (!ganz(d.dauer_ms) || d.dauer_ms !== dauer || dauer > 10000) return 'dauer';
    return null;
  }

  /* Leinwandgröße zum Bildverhältnis v: lange Seite 640 Punkte (mal Bildschirmdichte, höchstens 2) — dieselbe Rechnung wie Kontext::bewegungsDatei. */
  function format(cv, v) {
    var k = Math.min(2, g.devicePixelRatio || 1);
    cv.width = Math.round((v >= 1 ? 640 : 640 * v) * k);
    cv.height = Math.round((v >= 1 ? 640 / v : 640) * k);
  }

  /* Zeichnet Bild i auf einen 2D-Kontext (Breite w, Höhe h). o: farbe, stil ('strich'|'punkte'), spiegeln, linie; ueber: nicht löschen (liegt
     über einem Bild), schatten: Farbe eines Schattens für Lesbarkeit. Ohne ueber bleibt der Hintergrund durchsichtig. */
  function zeichne(c, d, i, w, h, o) {
    o = o || {};
    var je = d.dim + 1, f = d.rahmen[i], rh = Math.min(h, w / d.verhaeltnis), rw = rh * d.verhaeltnis, ox = (w - rw) / 2, oy = (h - rh) / 2,
      lw = o.linie || Math.max(2, rh / 90), P = [], k, a, b, m, punkte = o.stil === 'punkte';
    for (k = 0; k < 33; k++) {
      a = k * je;
      P[k] = f[a + je - 1] >= 0.5 ? [ox + (o.spiegeln ? 1 - f[a] : f[a]) * rw, oy + f[a + 1] * rh] : null;
    }
    c.save();
    if (!o.ueber) c.clearRect(0, 0, w, h);
    if (o.schatten) { c.shadowColor = o.schatten; c.shadowBlur = lw * 2; }
    c.strokeStyle = c.fillStyle = o.farbe || '#fff';
    c.lineWidth = lw;
    c.lineCap = c.lineJoin = 'round';
    if (!punkte) {
      c.beginPath();
      for (k = 0; k < VERB.length; k += 2) {
        a = P[VERB[k]]; b = P[VERB[k + 1]];
        if (a && b) { c.moveTo(a[0], a[1]); c.lineTo(b[0], b[1]); }
      }
      c.stroke();
      a = P[7]; b = P[8]; m = P[0];   // Kopf: Kreis um die Ohren, sonst um die Nase
      if (a && b) { c.beginPath(); c.arc((a[0] + b[0]) / 2, (a[1] + b[1]) / 2, Math.max(rh * 0.03, Math.hypot(a[0] - b[0], a[1] - b[1]) * 0.85), 0, 6.2832); c.stroke(); }
      else if (m) { c.beginPath(); c.arc(m[0], m[1], rh * 0.045, 0, 6.2832); c.stroke(); }
    }
    for (k = 0; k < 33; k++) {
      m = P[k];
      if (m && (punkte || k > 10)) { c.beginPath(); c.arc(m[0], m[1], lw * (punkte ? 1.4 : 0.9), 0, 6.2832); c.fill(); }
    }
    c.restore();
  }

  /* Zeitsteuerung: Schleife, Pause, Tempo, Spiegeln. bei(laeuft) meldet jeden Wechsel zwischen Lauf und Pause. */
  function spielen(cv, stil, bei) {
    var c = cv.getContext('2d'), d, n, ms, pos = 0, last = null, id = 0, lauf = false, sicht = true, tempo = 1, spiegel = false, farbe = '', s = {};
    function nr() { return Math.min(n - 1, Math.floor(pos * d.fps / 1000 + 1e-6)); }
    function bild() { zeichne(c, d, nr(), cv.width, cv.height, { farbe: farbe, stil: stil, spiegeln: spiegel }); }
    function tick(ts) {
      id = 0;
      if (!lauf || !sicht) return;
      if (last !== null) pos = (pos + (ts - last) * tempo) % ms;
      last = ts;
      bild();
      id = requestAnimationFrame(tick);
    }
    function meldet() { if (bei) bei(lauf); }
    s.setze = function (daten, still) {
      s.pause();
      d = daten; n = d.rahmen.length; ms = n * 1000 / d.fps; pos = still ? ms / 2 : 0;
      farbe = g.getComputedStyle(cv).color;
      bild();
      if (!still) s.abspielen();
    };
    s.abspielen = function () { if (!d || lauf) return; lauf = true; last = null; if (!id) id = requestAnimationFrame(tick); meldet(); };
    s.pause = function () { var war = lauf; lauf = false; if (id) cancelAnimationFrame(id); id = 0; if (war) meldet(); };
    s.umschalten = function () { if (lauf) s.pause(); else s.abspielen(); };
    s.zu = function (i) { if (!d) return; s.pause(); pos = ((i % n + n) % n) * 1000 / d.fps; bild(); meldet(); };
    s.nr = function () { return d ? nr() : 0; };
    s.tempo = function (x) { tempo = x; };
    s.spiegeln = function (b) { spiegel = b; if (d) bild(); };
    s.sichtbar = function (b) { sicht = b; if (b && lauf && !id) { last = null; id = requestAnimationFrame(tick); } };
    s.laeuft = function () { return lauf; };
    return s;
  }

  var SKRIPT = (g.document && g.document.currentScript && g.document.currentScript.src) || '',
    BASIS = SKRIPT.replace(/[^\/?#]*(\?.*)?$/, ''), STEMPEL = (SKRIPT.match(/\?.*$/) || [''])[0];

  function start(fig) {
    var sec = fig.closest('.lp-bewegung'), cv = fig.querySelector('canvas'), live = fig.getAttribute('data-lp-modus') === 'live',
      el = function (n) { return sec.querySelector('[data-lp-b="' + n + '"]'); },
      melde = function (n) {
        Array.prototype.forEach.call(sec.querySelectorAll('[data-lp-t]'), function (p) { p.hidden = p.getAttribute('data-lp-t') !== n; });
      },
      pa = el('pause'), sp, still = !!(g.matchMedia && g.matchMedia('(prefers-reduced-motion: reduce)').matches);
    sp = spielen(cv, fig.getAttribute('data-lp-stil') || 'strich', function (l) {
      if (pa) pa.textContent = pa.getAttribute(l ? 'data-an' : 'data-aus');
    });
    el('steuerung').hidden = false;
    pa.addEventListener('click', sp.umschalten);
    el('spiegeln').addEventListener('click', function (e) {
      var an = e.currentTarget.getAttribute('aria-pressed') !== 'true';
      e.currentTarget.setAttribute('aria-pressed', an);
      sp.spiegeln(an);
    });
    el('tempo').addEventListener('change', function (e) { sp.tempo(parseFloat(e.currentTarget.value) || 1); });
    cv.addEventListener('keydown', function (e) {
      var t = e.key;
      if (t === ' ' || t === 'Enter') sp.umschalten();
      else if (t === 'ArrowRight') sp.zu(sp.nr() + 1);
      else if (t === 'ArrowLeft') sp.zu(sp.nr() - 1);
      else if (t === 'Home') sp.zu(0);
      else return;
      e.preventDefault();
    });
    if (g.IntersectionObserver) new g.IntersectionObserver(function (e) { sp.sichtbar(e[0].isIntersecting); }).observe(cv);
    fetch(fig.getAttribute('data-lp-bewegung'), { credentials: 'omit' })
      .then(function (r) { if (!r.ok) throw 0; return r.text(); })
      .then(function (t) {
        var d = JSON.parse(t);
        if (pruefen(d, new Blob([t]).size)) throw 0;
        format(cv, d.verhaeltnis);
        sp.setze(d, still);
        if (still && pa) pa.textContent = pa.getAttribute('data-aus');
        if (live) {
          el('kamera').addEventListener('click', function () {
            import(BASIS + 'bewegung-live.js' + STEMPEL).then(function (m) { return m.umschalten({ fig: fig, sec: sec, cv: cv, sp: sp, el: el, melde: melde, api: api, daten: d, still: still }); })
              .catch(function () { melde('keinkamera'); });
          });
          sec.querySelector('.lp-bewegung-kamera').hidden = false;   // erst jetzt: der Klick findet Beispielbewegung und Hörer vor
        }
      })
      .catch(function () { melde('fehler'); });
  }

  var api = { pruefen: pruefen, zeichne: zeichne, spielen: spielen, format: format, NAMEN: NAMEN, GESICHT: GESICHT, MAX_BYTES: MAX_BYTES };
  if (typeof module === 'object' && module.exports) module.exports = api;
  g.LpBewegung = api;
  if (g.document && g.document.querySelectorAll) {
    Array.prototype.forEach.call(g.document.querySelectorAll('[data-lp-bewegung]'), function (fig) {
      try { start(fig); } catch (e) { /* die Seite bleibt benutzbar */ }
    });
  }
})(typeof window !== 'undefined' ? window : globalThis);
