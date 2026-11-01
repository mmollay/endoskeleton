/* SSI Endoskeleton 3.66.0 — Bewegungs-Player: spielt eine Bewegungsdatei „bewegung v1“ (docs/bewegung-format.md) als Silhouette (Vorgabe), Piktogramm, Körper, Strichfigur oder Punkte.
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
      27, 29, 29, 31, 27, 31, 28, 30, 30, 32, 28, 32],
    // Darstellungen (data-lp-stil): die drei neuen, dann die älteren. Alles andere fällt auf die Vorgabe zurück.
    STILE = ['silhouette', 'piktogramm', 'koerper', 'strich', 'punkte', 'figur'], VORGABE = 'silhouette';

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

  /* ---- Farben: aus der EINEN Farbe des Players (color der Leinwand) und dem Grund (Hintergrund der Fläche) abgeleitet, keine eigenen Tokens ---- */
  function rgb(s, ersatz) {   // "#abc", "#aabbcc", "rgb(…)", "rgba(…)", "color(srgb r g b)" → [r, g, b]; alles andere: ersatz
    var m = String(s == null ? '' : s).trim().toLowerCase();
    if ((s = /^#([0-9a-f]{3}|[0-9a-f]{6})$/.exec(m))) { m = s[1].length === 3 ? s[1].replace(/./g, '$&$&') : s[1]; return [0, 2, 4].map(function (n) { return parseInt(m.substr(n, 2), 16); }); }
    if ((s = /^rgba?\(\s*([\d.]+)\s*[ ,]\s*([\d.]+)\s*[ ,]\s*([\d.]+)/.exec(m))) return [+s[1], +s[2], +s[3]].map(Math.round);
    if ((s = /^color\(\s*srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/.exec(m))) return [+s[1], +s[2], +s[3]].map(function (v) { return Math.round(255 * v); });
    return ersatz;
  }
  function mischen(a, b, t) { return a.map(function (v, n) { return Math.max(0, Math.min(255, Math.round(v + (b[n] - v) * t))); }); }
  function css(c, al) { return al == null ? 'rgb(' + c.join(',') + ')' : 'rgba(' + c.join(',') + ',' + al + ')'; }
  function hell(c) { var t = c.map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * t[0] + 0.7152 * t[1] + 0.0722 * t[2]; }
  function kontrast(a, b) { var x = hell(a), y = hell(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
  /* Zieht die Farbe f vom Grund g weg (zu Weiß oder Schwarz, je nachdem, was mehr Abstand gibt), bis der Kontrast mindestens ziel ist (Vorgabe 3 : 1, WCAG 1.4.11). */
  function lesbar(f, g, ziel) {
    var z = kontrast([255, 255, 255], g) > kontrast([0, 0, 0], g) ? [255, 255, 255] : [0, 0, 0], n = 0, r = f;
    ziel = ziel || 3;
    while (kontrast(r, g) < ziel && n < 10) r = mischen(f, z, ++n / 10);
    return r;
  }
  /* Mischt f um t zu z hin (ohne Angabe: zum Grund g), so weit, wie der Kontrast zu g 3 : 1 hält (sonst weniger). */
  function weich(f, g, t, z) {
    var r = mischen(f, z || g, t);
    while (t > 0.001 && kontrast(r, g) < 3) r = mischen(f, z || g, t = Math.max(0, t - 0.05));
    return r;
  }
  /* Die Farben eines Stils. Tragend (gegen den Grund mindestens 3 : 1): Kontur (silhouette), Füllung (piktogramm), Mitte (koerper). */
  function farben(stil, farbe, grund) {
    var g = rgb(grund, [255, 255, 255]), f = lesbar(rgb(farbe, [255, 255, 255]), g), dunkelGrund = hell(g) < 0.18,
      z = dunkelGrund ? [0, 0, 0] : [255, 255, 255];   // gedämpft wird zu Schwarz (dunkler Grund) oder Weiß (heller Grund): die Farbtöne bleiben, nichts wird schlammig
    if (stil === 'figur') return farbenFigur(farbe, grund);
    if (stil === 'piktogramm') return { fuell: f, luecke: g };
    if (stil === 'koerper') {
      var matt = weich(f, g, 0.5, z), mitte = weich(f, g, 0.2, z);   // Licht von links oben: die helle Seite ist immer die hellere
      return { hell: dunkelGrund ? f : matt, mitte: mitte, dunkel: dunkelGrund ? matt : f, rand: mischen(matt, g, 0.6), schatten: dunkelGrund ? f : [0, 0, 0], schattenA: dunkelGrund ? 0.2 : 0.38, grund: g };
    }
    return { fuell: weich(f, g, 0.3, z), kontur: f, boden: mischen(g, f, 0.3) };
  }

  /* ---- Figur (silhouette, piktogramm, koerper): dieselben Teile aus den 33 Punkten, nur Maße und Pinsel unterscheiden sich ---- */
  /* Maße in Bildhöhen. arm/bein: Radius an Schulter–Hüfte, Ellbogen–Knie, Handgelenk–Knöchel; hand: Radius am Handgelenk, an der Spitze, Länge. */
  var MASSE = {
    piktogramm: { arm: [0.019, 0.019, 0.019], bein: [0.024, 0.024, 0.024], hand: [0.023, 0.017, 0.052], fuss: 0.018, rumpf: 0.027, tiefe: [0.040, 0.040], kopf: 0.052, hals: 0, kopfFrei: true },
    koerper: { arm: [0.023, 0.018, 0.012], bein: [0.037, 0.027, 0.017], hand: [0.014, 0.010, 0.055], fuss: 0.014, rumpf: 0.027, tiefe: [0.054, 0.048], kopf: 0.050, hals: 0.019, kopfFrei: false },
    silhouette: { arm: [0.025, 0.023, 0.021], bein: [0.029, 0.027, 0.025], hand: [0.027, 0.022, 0.058], fuss: 0.021, rumpf: 0.036, tiefe: [0.052, 0.052], kopf: 0.057, hals: 0.023, kopfFrei: false }
  };

  /* Richtung der Hand: Unterarm plus Richtung zum Zeigefinger (die Fingerpunkte liegen nah am Handgelenk und zittern, darum nur zum Teil). */
  function handRichtung(P, e, w, z) {
    var a = P[e], b = P[w], dx, dy, l, f = P[z], fx, fy, fl;
    if (!a || !b) return null;
    dx = b.x - a.x; dy = b.y - a.y; l = Math.hypot(dx, dy) || 1; dx /= l; dy /= l;
    if (f) { fx = f.x - b.x; fy = f.y - b.y; fl = Math.hypot(fx, fy); if (fl > 1e-6) { dx += 0.7 * fx / fl; dy += 0.7 * fy / fl; l = Math.hypot(dx, dy) || 1; dx /= l; dy /= l; } }
    return [dx, dy];
  }

  /* Die Teile der Figur (zwei Arme, Rumpf mit Beinen und Kopf, bei piktogramm der Kopf einzeln). Jedes Teil: z (Tiefe, falls die Datei sie hat), feste Rangzahl
     für den Fall ohne Tiefe, fern (0 nah … 1 fern) und eine Liste Formen: kap (Kapsel von p nach q), kreis, poly (Fläche mit gerundeter Kante). */
  function teile(P, u, s) {
    var T = [], mitte = function (a, b) { return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, z: a.z == null ? null : (a.z + b.z) / 2 }; };
    function tiefe(ks) { var t = 0, n = 0; ks.forEach(function (k) { if (P[k] && P[k].z != null) { t += P[k].z; n++; } }); return n ? t / n : null; }
    function kap(p, q, r1, r2) { return { t: 'kap', p: p, q: q, r1: r1 * u, r2: r2 * u }; }
    function arm(a, b, e, zf, rang, fern) {   // Schulter a, Ellbogen b, Handgelenk e, Zeigefinger zf
      var f = [], d;
      if (!P[a] || !P[b]) return;
      f.push(kap(P[a], P[b], s.arm[0], s.arm[1]));
      if (P[e]) {
        f.push(kap(P[b], P[e], s.arm[1], s.arm[2]));
        if ((d = handRichtung(P, b, e, zf))) f.push(kap(P[e], { x: P[e].x + d[0] * s.hand[2] * u, y: P[e].y + d[1] * s.hand[2] * u }, s.hand[0], s.hand[1]));
      }
      T.push({ z: tiefe([a, b, e]), rang: rang, fern: fern, prims: f });
    }
    function bein(h, k, a, he, to) {   // Hüfte, Knie, Knöchel, Ferse, Fußspitze
      var f = [];
      if (!P[h] || !P[k]) return null;
      f.push(kap(P[h], P[k], s.bein[0], s.bein[1]));
      if (P[a]) {
        f.push(kap(P[k], P[a], s.bein[1], s.bein[2]));
        if (P[he] && P[to]) f.push({ t: 'poly', pts: [P[a], P[he], P[to]], rr: s.fuss * u });
      }
      return { z: tiefe([h, k, a]), prims: f };
    }
    arm(11, 13, 15, 19, 3, 1);
    (function () {   // Rumpf-Teil: Beine (das hintere zuerst), Rumpf, Hals, Kopf
      var f = [], kopf, sm, hm, c, sp, beine = [bein(23, 25, 27, 29, 31), bein(24, 26, 28, 30, 32)].filter(Boolean);
      if (beine.length === 2 && beine[0].z != null && beine[1].z != null && beine[1].z > beine[0].z) beine.reverse();
      beine.forEach(function (b) { f = f.concat(b.prims); });
      if (P[11] && P[12] && P[23] && P[24]) {
        sm = mitte(P[11], P[12]); hm = mitte(P[23], P[24]);
        c = { x: (P[11].x + P[12].x + P[23].x + P[24].x) / 4, y: (P[11].y + P[12].y + P[23].y + P[24].y) / 4 };   // Rumpffläche, etwas nach innen gezogen: runde Schultern
        f.push({ t: 'poly', rumpf: true, rr: s.rumpf * u, pts: [P[11], P[12], P[24], P[23]].map(function (p) {
          var dx = c.x - p.x, dy = c.y - p.y, l = Math.hypot(dx, dy) || 1, v = 0.5 * s.rumpf * u;
          return { x: p.x + dx / l * v, y: p.y + dy / l * v };
        }) });
        // Rumpftiefe: von vorn (Schultern weit auseinander) kaum, von der Seite voll
        sp = Math.max(0, Math.min(1, 1 - Math.hypot(P[11].x - P[12].x, P[11].y - P[12].y) / (0.28 * u)));
        if (sp > 0.02) { f.push(kap(sm, hm, s.tiefe[0] * sp, s.tiefe[1] * sp)); f[f.length - 1].rumpf = true; }
      } else if (P[11] && P[12]) sm = mitte(P[11], P[12]);
      if (P[7] && P[8]) kopf = mitte(P[7], P[8]); else if (P[0]) kopf = { x: P[0].x, y: P[0].y, z: P[0].z };
      if (kopf) {
        if (s.hals && sm) f.push(kap(sm, kopf, s.hals, s.hals));
        c = { t: 'kreis', c: kopf, r: s.kopf * u };
        if (s.kopfFrei) T.push({ z: tiefe([11, 12, 23, 24]), rang: 5, fern: 0.5, prims: [c] }); else f.push(c);
      }
      if (f.length) T.push({ z: tiefe([11, 12, 23, 24]), rang: 4, fern: 0.5, prims: f, beine: beine });
    })();
    arm(12, 14, 16, 20, 6, 0);
    // Tiefe: mit z-Werten von hinten nach vorn (größeres z = weiter weg), sonst die feste Rangzahl
    T.sort(function (p, q) { return (p.z != null && q.z != null && Math.abs(p.z - q.z) > 1e-4) ? q.z - p.z : p.rang - q.rang; });
    var zs = T.map(function (t) { return t.z; }).filter(function (z) { return z != null; }), z0 = Math.min.apply(null, zs), z1 = Math.max.apply(null, zs);
    function fern(z, alt) { return z != null && z1 - z0 > 1e-4 ? Math.max(0, Math.min(1, (z - z0) / (z1 - z0))) : alt; }
    T.forEach(function (t) { t.fern = fern(t.z, t.fern); (t.beine || []).forEach(function (b, n) { b.prims.forEach(function (f) { f.fern = fern(b.z, n ? 0 : 1); }); }); });
    return T;
  }

  function kapPfad(c, p, q, r1, r2) {
    var dx = q.x - p.x, dy = q.y - p.y, L = Math.hypot(dx, dy), al, be;
    if (L < 1e-6 || L <= Math.abs(r1 - r2)) { if (r1 >= r2) c.arc(p.x, p.y, r1, 0, 6.2832); else c.arc(q.x, q.y, r2, 0, 6.2832); return; }
    al = Math.atan2(dy, dx); be = Math.acos((r1 - r2) / L);
    c.arc(q.x, q.y, r2, al - be, al + be, false); c.arc(p.x, p.y, r1, al + be, al - be + 6.2832, false); c.closePath();
  }
  /* Eine Form füllen, um v größer (für die Kontur). */
  function fuellen(c, f, v) {
    c.beginPath();
    if (f.t === 'kreis') c.arc(f.c.x, f.c.y, f.r + v, 0, 6.2832);
    else if (f.t === 'kap') kapPfad(c, f.p, f.q, f.r1 + v, f.r2 + v);
    else f.pts.forEach(function (p, n) { if (n) c.lineTo(p.x, p.y); else c.moveTo(p.x, p.y); });
    if (f.t === 'poly') c.closePath();
    c.fill();
    if (f.t === 'poly') { c.lineWidth = 2 * (f.rr + v); c.lineJoin = 'round'; c.stroke(); }
  }
  /* Je Teil erst die Kontur (alle Formen etwas größer), dann die Füllung: ein Teil hat nur außen eine Kontur, nie innen zwischen seinen Gliedern. */
  function malen(c, T, kontur, v, fuell) {
    T.forEach(function (t) {
      if (kontur) { c.fillStyle = c.strokeStyle = kontur; t.prims.forEach(function (f) { fuellen(c, f, v); }); }
      t.prims.forEach(function (f) { c.fillStyle = c.strokeStyle = fuell(f, t); fuellen(c, f, 0); });
    });
  }

  /* Weicher Verlauf quer über ein Glied (Licht von links oben). Die Rumpf-Formen (Fläche + Tiefe) teilen sich EINEN Verlauf, sonst gäbe es Nähte. */
  function verlauf(c, f, p, t, g) {
    var L = [-0.6, -0.8], fe = f.fern != null ? f.fern : t.fern, gr, po, sp, a, b, r, xs, ys,
      cols = [weich(p.hell, g, fe * 0.35), weich(p.mitte, g, fe * 0.35), weich(p.dunkel, g, 0.1 + fe * 0.2)];
    function achse(a, b, rr) {   // Verlauf quer zur Achse a→b, Breite ±rr um die Mitte
      var dx = b.x - a.x, dy = b.y - a.y, l = Math.hypot(dx, dy) || 1, nx = -dy / l, ny = dx / l, cx = (a.x + b.x) / 2, cy = (a.y + b.y) / 2;
      if (nx * L[0] + ny * L[1] < 0) { nx = -nx; ny = -ny; }
      return c.createLinearGradient(cx + nx * rr, cy + ny * rr, cx - nx * rr, cy - ny * rr);
    }
    if (f.t === 'kreis') gr = c.createRadialGradient(f.c.x - 0.35 * f.r, f.c.y - 0.4 * f.r, 0.1 * f.r, f.c.x, f.c.y, f.r * 1.05);
    else if (f.rumpf) {
      if (!t.rumpfGr) {
        po = t.prims.filter(function (q) { return q.t === 'poly' && q.rumpf; })[0]; sp = t.prims.filter(function (q) { return q.t === 'kap' && q.rumpf; })[0];
        a = sp ? sp.p : po.pts[0]; b = sp ? sp.q : po.pts[3]; r = sp ? Math.max(sp.r1, sp.r2) : 0;
        po.pts.forEach(function (q) { var dd = Math.hypot(b.x - a.x, b.y - a.y) || 1; r = Math.max(r, Math.abs(((q.x - a.x) * -(b.y - a.y) + (q.y - a.y) * (b.x - a.x)) / dd) + po.rr); });
        t.rumpfGr = achse(a, b, r);
      }
      gr = t.rumpfGr;
    } else if (f.t === 'kap') gr = achse(f.p, f.q, Math.max(f.r1, f.r2));
    else {
      xs = f.pts.map(function (q) { return q.x; }); ys = f.pts.map(function (q) { return q.y; });
      gr = c.createLinearGradient(Math.min.apply(null, xs), Math.min.apply(null, ys), Math.max.apply(null, xs), Math.max.apply(null, ys));
    }
    gr.addColorStop(0, css(cols[0])); gr.addColorStop(0.5, css(cols[1])); gr.addColorStop(1, css(cols[2]));
    return gr;
  }

  /* Boden: tiefster sichtbarer Fußpunkt über ALLE Bilder (die Linie bleibt stehen); bei einem einzelnen Bild (Kamera) keiner. */
  function boden(d, oy, rh) {
    var je = d.dim + 1, m = -1, f, k, j;
    if (d.rahmen.length < 2) return null;
    for (j = 0; j < d.rahmen.length; j++) { f = d.rahmen[j]; for (k = 29; k < 33; k++) if (f[k * je + je - 1] >= 0.5 && f[k * je + 1] > m) m = f[k * je + 1]; }
    return m < 0 ? null : oy + m * rh;
  }

  /* Die Figur in einem der drei Stile. by: Bodenhöhe in Pixeln oder null. */
  function figur(c, P, w, u, stil, o, by) {
    var T = teile(P, u, MASSE[stil]), p = farben(stil, o.farbe, o.grund || o.schatten), xs, mn, mx, rx, gr;
    if (stil === 'piktogramm') malen(c, T, css(p.luecke), Math.max(0.006 * u, 1.2), function () { return css(p.fuell); });
    else if (stil === 'koerper') {
      xs = [29, 30, 31, 32].filter(function (k) { return P[k]; }).map(function (k) { return P[k].x; });
      if (by != null && xs.length) {   // weicher Schatten unter den Füßen
        mn = Math.min.apply(null, xs); mx = Math.max.apply(null, xs); rx = Math.max(0.14 * u, (mx - mn) / 2 + 0.09 * u);
        c.save(); c.translate((mn + mx) / 2, by); c.scale(1, 0.13);
        gr = c.createRadialGradient(0, 0, 0, 0, 0, rx); gr.addColorStop(0, css(p.schatten, p.schattenA)); gr.addColorStop(1, css(p.schatten, 0));
        c.fillStyle = gr; c.beginPath(); c.arc(0, 0, rx, 0, 6.2832); c.fill(); c.restore();
      }
      malen(c, T, css(p.rand), Math.max(0.0035 * u, 1), function (f, t) { return verlauf(c, f, p, t, p.grund); });
    } else {
      if (by != null) { c.strokeStyle = css(p.boden); c.lineWidth = Math.max(0.008 * u, 2); c.lineCap = 'round'; c.beginPath(); c.moveTo(w * 0.08, by); c.lineTo(w * 0.92, by); c.stroke(); }
      malen(c, T, css(p.kontur), Math.max(0.008 * u, 1.6), function () { return css(p.fuell); });
    }
  }

  /* ---- Stil figur (3.67.0): großer dunkler Kopf, Gesicht nur aus zwei Lidern und einem Mundstrich, Kleidung in Flächen in der Markenfarbe (color der Leinwand),
     helle, versetzte Randlinie wie ein Aufkleber. Zeichnet NUR die Figur auf die vorhandene Leinwand: keine Bühne, kein Verlauf, kein Regen — der Grund gehört der Seite.
     Der Ausdruck ist eine Angabe der Seite (data-lp-ausdruck), nicht aus der Kamera: die Körper-Aufnahme liefert keine Mimik. ---- */
  /* oben/unten: Höhe der Lid-Form über/unter der Lid-Kante (1 = Normalhöhe), wink: Neigung der Lid-Kante in Grad (+ = außen tiefer, müde; − = innen tiefer, entschlossen),
     mund: b Länge in Kopfradien, w Neigung in Grad, rund = kleiner Kreis. */
  var AUSDRUECKE = ['neutral', 'muede', 'ueberrascht', 'entschlossen'],
    AUSDRUCK = {
      neutral: { oben: 0, unten: 1, wink: 0, mund: { b: 0.32, w: 0, rund: false } },
      muede: { oben: 0, unten: 0.55, wink: 13, mund: { b: 0.26, w: 8, rund: false } },
      ueberrascht: { oben: 0.9, unten: 1.2, wink: 0, mund: { b: 0.1, w: 0, rund: true } },
      entschlossen: { oben: 0, unten: 0.85, wink: -19, mund: { b: 0.38, w: -7, rund: false } }
    },
    // Lidschlag: Zeitpunkte (s) in einem Zyklus von 15,4 s, jeder Schlag dauert 0,16 s
    BLINK = [2.2, 5.6, 8.3, 12.4], BLINK_ZYKLUS = 15.4,
    // Maße in Bildhöhen: kräftige Glieder mit runden Enden
    DM = { arm: 0.028, bein: 0.033, rumpf: 0.05, spine: 0.06, hals: 0.017, hand: 0.028, schuh: 0.017, kragen: 0.026 },
    KOPF = [12, 14, 17], LID = [247, 243, 234], SCHUH = [238, 243, 234];

  /* Lidschlag als reine Zeitfunktion: 0 = offen, 1 = zu. */
  function blinzeln(ms) {
    var t = (ms / 1000) % BLINK_ZYKLUS, k, d;
    for (k = 0; k < BLINK.length; k++) { d = t - BLINK[k]; if (d >= 0 && d < 0.16) return Math.sin(Math.PI * d / 0.16); }
    return 0;
  }

  /* Farben von figur. Kleid (4,5 : 1), Hose (3,2 : 1), Schuhe (4,5 : 1) werden gegen den Grund angehoben. Der Kopf ist fast schwarz: auf hellem Grund trägt er allein
     (Kontrast ≥ 3 : 1); ist er zu schwach (dunkler Grund), trägt ein heller Rand um die ganze Figur (randNoetig, Kontrast ≥ 4,5 : 1). */
  function farbenFigur(farbe, grund) {
    var g = rgb(grund, [255, 255, 255]), kleid = lesbar(rgb(farbe, [255, 255, 255]), g, 4.5), hose = lesbar(mischen(kleid, [0, 0, 0], 0.32), g, 3.2),
      schuh = lesbar(SCHUH, g, 4.5), noetig = kontrast(KOPF, g) < 3, schwarz = [0, 0, 0];
    return { kopf: KOPF, gesicht: LID, kleid: kleid, kleidAus: mischen(kleid, schwarz, 0.6), kragen: mischen(kleid, schwarz, 0.3), hose: hose, hoseAus: mischen(hose, schwarz, 0.6),
      schuh: schuh, schuhAus: mischen(schuh, schwarz, 0.55), randNoetig: noetig, rand: noetig ? lesbar(mischen([255, 255, 255], kleid, 0.1), g, 4.5) : [255, 255, 255],
      schattenA: hell(g) < 0.18 ? 0.5 : 0.3 };
  }

  function lerp(a, b, t) { return { x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t }; }
  function mp(a, b) { return lerp(a, b, 0.5); }
  function klemm(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function linienWinkel(a, b, mind) {
    var dx = a.x - b.x, dy = a.y - b.y;
    if (Math.hypot(dx, dy) < mind) return null;
    if (dx < 0) { dx = -dx; dy = -dy; }
    return Math.atan2(dy, dx);
  }
  /* Kopf aus den Gesichtspunkten: Größe aus dem Abstand Ohren-Mitte → Schulter-Mitte, Neigung aus Augen- oder Ohrenlinie (sonst aus der Halsneigung),
     Drehung aus dem Versatz der Nase gegen die Ohren-Mitte, Nicken aus der Höhe der Nase. */
  function kopfD(P, u) {
    var n = P[0], E, sm, dx, dy, len, R, lean, roll, w, yaw = 0, pit = 0, dir;
    if (P[7] && P[8]) E = mp(P[7], P[8]); else if (n) E = { x: n.x, y: n.y - 0.006 * u }; else return null;
    sm = P[11] && P[12] ? mp(P[11], P[12]) : { x: E.x, y: E.y + 0.105 * u };
    dx = E.x - sm.x; dy = E.y - sm.y; len = Math.hypot(dx, dy) || 1e-6; dir = { x: dx / len, y: dy / len };
    R = klemm(0.84 * len / u, 0.075, 0.1) * u;
    lean = Math.atan2(dx, -dy);
    w = P[2] && P[5] ? linienWinkel(P[2], P[5], 0.03 * u) : null;
    if (w == null && P[7] && P[8]) w = linienWinkel(P[7], P[8], 0.03 * u);
    roll = w == null ? 0.6 * lean : 0.7 * w + 0.3 * lean;
    if (n) { yaw = klemm((n.x - E.x) / (0.05 * u), -1, 1); pit = klemm((n.y - E.y - 0.006 * u) / (0.03 * u), -1, 1); }
    return { c: { x: sm.x + dir.x * (R + 0.05 * u), y: sm.y + dir.y * (R + 0.05 * u) }, r: R, roll: roll, yaw: yaw, pit: pit };
  }

  /* Gesicht: zwei Lid-Formen (untere Halbellipse unter einer geraden Lid-Kante, bei „ueberrascht“ auch eine obere) und ein Mundstrich. Die Merkmale wandern mit der
     Kopfdrehung zur Seite und werden an der Gegenseite schmaler (Seitenblick); der Kopfkreis schneidet sie ab. bl: Lidschlag 0…1. */
  function gesichtD(c, K, f, a, bl) {
    var R = K.r, yw = K.yaw, ay = Math.abs(yw), fx = yw * 0.5 * R, fy = K.pit * 0.16 * R, ex = 0.37 * R * (1 - 0.3 * ay), ey = -0.02 * R + fy, m = a.mund, mw, mx, my, rot, dx, dy;
    c.save(); c.translate(K.c.x, K.c.y); c.rotate(K.roll);
    c.beginPath(); c.arc(0, 0, R * 0.985, 0, 6.2832); c.clip();
    c.fillStyle = c.strokeStyle = css(f.gesicht);
    [-1, 1].forEach(function (sg) {
      var rx = 0.21 * R * (sg * yw < 0 ? 1 - 0.38 * ay : 1 + 0.06 * ay) * (1 - 0.1 * ay), un = Math.max(0.03 * R, 0.2 * R * a.unten * (1 - bl)), ob = 0.2 * R * a.oben * (1 - bl);
      c.save(); c.translate(sg * ex + fx, ey); c.rotate((sg < 0 ? -a.wink : a.wink) * Math.PI / 180);
      c.beginPath(); c.ellipse(0, 0, rx, un, 0, 0, Math.PI);
      if (ob > 0.012 * R) c.ellipse(0, 0, rx, ob, 0, Math.PI, 2 * Math.PI); else c.closePath();
      c.fill(); c.restore();
    });
    mx = fx; my = 0.44 * R + fy * 1.3; mw = m.b * R * (1 - 0.3 * ay);
    if (m.rund) { c.beginPath(); c.ellipse(mx, my, 0.07 * R * (1 - 0.3 * ay), 0.09 * R, 0, 0, 6.2832); c.fill(); }
    else {
      rot = m.w * Math.PI / 180; dx = Math.cos(rot) * (mw - 0.08 * R) / 2; dy = Math.sin(rot) * (mw - 0.08 * R) / 2;
      c.lineCap = 'round'; c.lineWidth = 0.08 * R; c.beginPath(); c.moveTo(mx - dx, my - dy); c.lineTo(mx + dx, my + dy); c.stroke();
    }
    c.restore();
  }

  /* Formen: str = Linienzug mit runden Enden (mit drei Punkten wird das Gelenk zur runden Kurve, kein Knick), kreis, poly = gerundetes Vieleck. g: Zuschlag für die Ränder. */
  function primD(c, f, g, farbe) {
    var p = f.pts, a, b;
    c.fillStyle = c.strokeStyle = farbe; c.lineCap = c.lineJoin = 'round';
    c.beginPath();
    if (f.t === 'kreis') { c.arc(f.c.x, f.c.y, f.r + g, 0, 6.2832); c.fill(); return; }
    c.lineWidth = 2 * (f.r + g);
    c.moveTo(p[0].x, p[0].y);
    if (p.length === 3) { a = lerp(p[1], p[0], 0.3); b = lerp(p[1], p[2], 0.3); c.lineTo(a.x, a.y); c.quadraticCurveTo(p[1].x, p[1].y, b.x, b.y); c.lineTo(p[2].x, p[2].y); }
    else for (a = 1; a < p.length; a++) c.lineTo(p[a].x, p[a].y);
    if (f.t === 'str') { c.stroke(); return; }
    c.closePath(); c.fill(); c.stroke();
  }
  function zMittel(P, ks, ws) { var t = 0, n = 0; ks.forEach(function (k, j) { var w = ws ? ws[j] : 1; if (P[k] && P[k].z != null) { t += P[k].z * w; n += w; } }); return n ? t / n : null; }

  /* Teile mit Tiefe: Beine immer zuerst (untereinander nach z), dann Rumpf, Arme und Kopf nach z (mit Spielraum, damit nichts flackert), sonst feste Rangfolge. */
  function teileD(P, u, f, K) {
    var T = [];
    function arm(a, b, e, ix, rang) {
      var pr = [], d, pe = P[e];
      if (!P[a] || !P[b]) return;
      pr.push({ t: 'str', pts: pe ? [P[a], P[b], pe] : [P[a], P[b]], r: DM.arm * u, fc: f.kleid, ac: f.kleidAus });
      d = pe ? handRichtung(P, b, e, ix) : null;
      if (d) {   // Manschette und runde Hand (dunkel wie der Kopf)
        pr.push({ t: 'str', pts: [{ x: pe.x - d[0] * 0.05 * u, y: pe.y - d[1] * 0.05 * u }, pe], r: (DM.arm + 0.004) * u, fc: f.kragen, ac: f.kleidAus });
        pr.push({ t: 'kreis', c: { x: pe.x + d[0] * 0.028 * u, y: pe.y + d[1] * 0.028 * u }, r: DM.hand * u, fc: f.kopf, ac: null });
      }
      T.push({ z: zMittel(P, [a, b, e], [0.2, 0.3, 0.5]), rang: rang, grp: 1, prims: pr });
    }
    function bein(h, k, a, he, to, rang) {
      var pr, ende;
      if (!P[h] || !P[k]) return;
      ende = P[a] ? lerp(P[a], P[k], 0.14) : null;   // die Hose endet kurz über dem Knöchel, der Schuh deckt den Rest
      pr = [{ t: 'str', pts: ende ? [P[h], P[k], ende] : [P[h], P[k]], r: DM.bein * u, fc: f.hose, ac: f.hoseAus }];
      if (P[a] && P[he] && P[to]) pr.push({ t: 'poly', pts: [P[a], P[he], P[to]], r: DM.schuh * u, fc: f.schuh, ac: f.schuhAus });
      T.push({ z: zMittel(P, [h, k, a]), rang: rang, grp: 0, prims: pr });
    }
    bein(23, 25, 27, 29, 31, 1); bein(24, 26, 28, 30, 32, 2);
    if (P[11] && P[12] && P[23] && P[24]) {
      var pr = [], sm = mp(P[11], P[12]), hm = mp(P[23], P[24]), c = mp(sm, hm), sp, dir, nx, ny, l2;
      pr.push({ t: 'poly', r: DM.rumpf * u, fc: f.kleid, ac: f.kleidAus, pts: [P[11], P[12], P[24], P[23]].map(function (p) {
        var dx = c.x - p.x, dy = c.y - p.y, l = Math.hypot(dx, dy) || 1, g = 0.5 * DM.rumpf * u;
        return { x: p.x + dx / l * g, y: p.y + dy / l * g };
      }) });
      // Rumpftiefe: von vorn (Schultern weit auseinander) kaum, von der Seite voll
      sp = klemm(1 - Math.hypot(P[11].x - P[12].x, P[11].y - P[12].y) / (0.28 * u), 0, 1);
      if (sp > 0.02) pr.push({ t: 'str', pts: [sm, hm], r: DM.spine * u * sp, fc: f.kleid, ac: f.kleidAus });
      if (K) pr.push({ t: 'str', pts: [sm, K.c], r: DM.hals * u, fc: f.kopf, ac: null });
      dir = { x: hm.x - sm.x, y: hm.y - sm.y }; l2 = Math.hypot(dir.x, dir.y) || 1; dir.x /= l2; dir.y /= l2;
      nx = -dir.y; ny = dir.x;
      pr.push({ t: 'str', pts: [lerp(sm, P[11], 0.5), lerp(sm, P[12], 0.5)].map(function (p) { return { x: p.x - dir.x * 0.012 * u, y: p.y - dir.y * 0.012 * u }; }), r: DM.kragen * u, fc: f.kragen, ac: f.kleidAus });
      T.push({ z: zMittel(P, [11, 12, 23, 24]), rang: 3, grp: 1, prims: pr, extra: function (cx) {   // zwei Nähte an der Brust
        var off = (1 - sp) * 0.026 * u, b0 = { x: sm.x + dir.x * 0.04 * u, y: sm.y + dir.y * 0.04 * u };
        cx.save(); cx.strokeStyle = css(f.gesicht, 0.85); cx.lineWidth = Math.max(1, 0.0065 * u); cx.lineCap = 'round';
        [-1, 1].forEach(function (s) { cx.beginPath(); cx.moveTo(b0.x + nx * off * s, b0.y + ny * off * s); cx.lineTo(b0.x + nx * off * s + dir.x * 0.1 * u, b0.y + ny * off * s + dir.y * 0.1 * u); cx.stroke(); });
        cx.restore();
      } });
    }
    arm(11, 13, 15, 19, 4); arm(12, 14, 16, 20, 6);
    if (K) T.push({ z: zMittel(P, [7, 8, 0]), rang: 5, grp: 1, prims: [{ t: 'kreis', c: K.c, r: K.r, fc: null, ac: null }], kopf: true });
    T.sort(function (p, q) {
      if (p.grp !== q.grp) return p.grp - q.grp;
      if (p.z != null && q.z != null && Math.abs(p.z - q.z) > (p.grp ? 0.03 : 1e-4)) return q.z - p.z;
      return p.rang - q.rang;
    });
    return T;
  }

  /* Die Figur im Stil figur. u: Bildhöhe in Pixeln, by: Bodenhöhe oder null. */
  function figurD(c, P, u, o, by) {
    var f = farbenFigur(o.farbe, o.grund || o.schatten), K = kopfD(P, u), a = AUSDRUCK[AUSDRUECKE.indexOf(o.ausdruck) < 0 ? AUSDRUECKE[0] : o.ausdruck],
      bl = o.ruhe || !zahl(o.zeit) ? 0 : blinzeln(o.zeit), T = teileD(P, u, f, K), xs = [29, 30, 31, 32].filter(function (k) { return P[k]; }).map(function (k) { return P[k].x; }), mn, mx, rx, gr;
    if (by != null && xs.length) {   // weicher Schatten unter den Füßen
      mn = Math.min.apply(null, xs); mx = Math.max.apply(null, xs); rx = Math.max(0.16 * u, (mx - mn) / 2 + 0.1 * u);
      c.save(); c.translate((mn + mx) / 2, by); c.scale(1, 0.13);
      gr = c.createRadialGradient(0, 0, 0, 0, 0, rx); gr.addColorStop(0, css([0, 0, 0], f.schattenA)); gr.addColorStop(1, css([0, 0, 0], 0));
      c.fillStyle = gr; c.beginPath(); c.arc(0, 0, rx, 0, 6.2832); c.fill(); c.restore();
    }
    // Randlinie wie ein Aufkleber: die ganze Figur einfarbig, einmal nach oben rechts versetzt und etwas dicker, einmal rundum schmal
    c.save(); c.translate(0.0075 * u, -0.0075 * u);
    T.forEach(function (t) { t.prims.forEach(function (p) { primD(c, p, 0.009 * u, css(f.rand)); }); });
    c.restore();
    T.forEach(function (t) { t.prims.forEach(function (p) { primD(c, p, 0.0045 * u, css(f.rand)); }); });
    T.forEach(function (t) {
      t.prims.forEach(function (p) { if (p.ac) primD(c, p, 0.0035 * u, css(p.ac)); });
      t.prims.forEach(function (p) { if (p.fc) primD(c, p, 0, css(p.fc)); });
      if (t.extra) t.extra(c);
      if (t.kopf && K) {
        gr = c.createRadialGradient(K.c.x - 0.35 * K.r, K.c.y - 0.4 * K.r, 0.1 * K.r, K.c.x, K.c.y, K.r * 1.05);
        gr.addColorStop(0, css(mischen(f.kopf, [255, 255, 255], 0.16))); gr.addColorStop(0.7, css(f.kopf)); gr.addColorStop(1, css(mischen(f.kopf, [0, 0, 0], 0.2)));
        c.fillStyle = gr; c.beginPath(); c.arc(K.c.x, K.c.y, K.r, 0, 6.2832); c.fill();
        gesichtD(c, K, f, a, bl);
      }
    });
  }

  /* Die älteren Darstellungen (Bestandsschutz, unverändert): Strichfigur und Punkte. */
  function strichfigur(c, P, rh, punkte, o) {
    var lw = o.linie || Math.max(2, rh / 90), k, a, b, m;
    if (o.schatten) { c.shadowColor = o.schatten; c.shadowBlur = lw * 2; }
    c.strokeStyle = c.fillStyle = o.farbe || '#fff';
    c.lineWidth = lw;
    c.lineCap = c.lineJoin = 'round';
    if (!punkte) {
      c.beginPath();
      for (k = 0; k < VERB.length; k += 2) {
        a = P[VERB[k]]; b = P[VERB[k + 1]];
        if (a && b) { c.moveTo(a.x, a.y); c.lineTo(b.x, b.y); }
      }
      c.stroke();
      a = P[7]; b = P[8]; m = P[0];   // Kopf: Kreis um die Ohren, sonst um die Nase
      if (a && b) { c.beginPath(); c.arc((a.x + b.x) / 2, (a.y + b.y) / 2, Math.max(rh * 0.03, Math.hypot(a.x - b.x, a.y - b.y) * 0.85), 0, 6.2832); c.stroke(); }
      else if (m) { c.beginPath(); c.arc(m.x, m.y, rh * 0.045, 0, 6.2832); c.stroke(); }
    }
    for (k = 0; k < 33; k++) {
      m = P[k];
      if (m && (punkte || k > 10)) { c.beginPath(); c.arc(m.x, m.y, lw * (punkte ? 1.4 : 0.9), 0, 6.2832); c.fill(); }
    }
  }

  /* Zeichnet Bild i auf einen 2D-Kontext (Breite w, Höhe h). Rein: dasselbe Bild + dieselbe Größe + dieselben Angaben = dieselben Pixel (keine Zeit, kein Zufall).
     o: stil ('silhouette' | 'piktogramm' | 'koerper' | 'strich' | 'punkte' | 'figur'; alles andere = Vorgabe silhouette), farbe (die Figurfarbe), grund (Farbe des Hintergrunds:
     Lücke, Kontrast), spiegeln, linie (nur strich/punkte), ausdruck/zeit/ruhe (nur figur: 'neutral' | 'muede' | 'ueberrascht' | 'entschlossen', alles andere = neutral; zeit in ms
     steuert den Lidschlag, ruhe = „weniger Bewegung“: kein Lidschlag); ueber: nicht löschen (liegt über einem Bild), schatten: Farbe eines Schattens für Lesbarkeit (gilt
     auch als grund, wenn keiner genannt ist). Ohne ueber bleibt der Hintergrund durchsichtig. */
  function zeichne(c, d, i, w, h, o) {
    o = o || {};
    var je = d.dim + 1, f = d.rahmen[i], rh = Math.min(h, w / d.verhaeltnis), rw = rh * d.verhaeltnis, ox = (w - rw) / 2, oy = (h - rh) / 2, P = [], k, a,
      stil = STILE.indexOf(o.stil) < 0 ? VORGABE : o.stil;
    for (k = 0; k < 33; k++) {
      a = k * je;
      P[k] = f[a + je - 1] >= 0.5 ? { x: ox + (o.spiegeln ? 1 - f[a] : f[a]) * rw, y: oy + f[a + 1] * rh, z: d.dim === 3 ? f[a + 2] : null } : null;
    }
    c.save();
    if (!o.ueber) c.clearRect(0, 0, w, h);
    if (stil === 'strich' || stil === 'punkte') strichfigur(c, P, rh, stil === 'punkte', o);
    else if (stil === 'figur') figurD(c, P, rh, o, boden(d, oy, rh));
    else figur(c, P, w, rh, stil, o, boden(d, oy, rh));
    c.restore();
  }

  /* Hintergrundfarbe unter der Leinwand: die erste Farbe, die nicht durchsichtig ist (Leinwand selbst, dann die Elternelemente). */
  function grundFarbe(el) {
    var f;
    for (; el && el.nodeType === 1; el = el.parentNode) {
      f = g.getComputedStyle(el).backgroundColor;
      if (f && f !== 'transparent' && !/^rgba\(.*,\s*0\)$/.test(f) && !/\/\s*0\)$/.test(f)) return f;
    }
    return '';
  }

  /* Zeitsteuerung: Schleife, Pause, Tempo, Spiegeln. bei(laeuft) meldet jeden Wechsel zwischen Lauf und Pause. opt (nur figur): ausdruck, ruhe (= „weniger Bewegung“: kein Lidschlag).
     zeit zählt die echte Laufzeit in ms (unabhängig von Tempo und Schleife) und steuert den Lidschlag. */
  function spielen(cv, stil, bei, opt) {
    var c = cv.getContext('2d'), d, n, ms, pos = 0, zeit = 0, last = null, id = 0, lauf = false, sicht = true, tempo = 1, spiegel = false, farbe = '', grund = '', s = {};
    opt = opt || {};
    function nr() { return Math.min(n - 1, Math.floor(pos * d.fps / 1000 + 1e-6)); }
    function bild() { zeichne(c, d, nr(), cv.width, cv.height, { farbe: farbe, grund: grund, stil: stil, spiegeln: spiegel, ausdruck: opt.ausdruck, ruhe: opt.ruhe, zeit: zeit }); }
    function tick(ts) {
      id = 0;
      if (!lauf || !sicht) return;
      if (last !== null) { pos = (pos + (ts - last) * tempo) % ms; zeit += ts - last; }
      last = ts;
      bild();
      id = requestAnimationFrame(tick);
    }
    function meldet() { if (bei) bei(lauf); }
    s.setze = function (daten, still) {
      s.pause();
      d = daten; n = d.rahmen.length; ms = n * 1000 / d.fps; pos = still ? ms / 2 : 0;
      farbe = g.getComputedStyle(cv).color;
      grund = grundFarbe(cv);
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
    sp = spielen(cv, fig.getAttribute('data-lp-stil'), function (l) {
      if (pa) pa.textContent = pa.getAttribute(l ? 'data-an' : 'data-aus');
    }, { ausdruck: fig.getAttribute('data-lp-ausdruck'), ruhe: still });
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
      if (el('steuerung').hidden) return;   // läuft die Kamera (Steuerung verborgen), gehört die Leinwand ihr
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
        var d;
        if (t.length > MAX_BYTES) throw 0;   // zu groß: gar nicht erst parsen
        d = JSON.parse(t);
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

  var api = { pruefen: pruefen, zeichne: zeichne, farben: farben, spielen: spielen, format: format, NAMEN: NAMEN, GESICHT: GESICHT, STILE: STILE, VORGABE: VORGABE, AUSDRUECKE: AUSDRUECKE, blinzeln: blinzeln, MAX_BYTES: MAX_BYTES };
  if (typeof module === 'object' && module.exports) module.exports = api;
  g.LpBewegung = api;
  if (g.document && g.document.querySelectorAll) {
    Array.prototype.forEach.call(g.document.querySelectorAll('[data-lp-bewegung]'), function (fig) {
      try { start(fig); } catch (e) { /* die Seite bleibt benutzbar */ }
    });
  }
})(typeof window !== 'undefined' ? window : globalThis);
