'use strict';
// Zeichentest (tests/bewegung.test.php, BW15): lässt LpBewegung.zeichne() gegen einen aufzeichnenden Kontext laufen (kein Browser nötig).
// Aufruf: node tests/bewegung-zeichnen.js [Pfad zum Player]      Ausgabe: [[true|false, "Beschreibung"], …]
// Mit dem Pfad eines älteren Players und der Umgebungsvariable BW_HASH=1 gibt es stattdessen die Prüfsummen für den Bestandsschutz aus.
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const api = require(process.argv[2] ? path.resolve(process.argv[2]) : path.join(__dirname, '..', 'lp', 'bewegung.js'));
const winken = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'lp', 'musterbuch', 'bew', 'beispiel-winken.bewegung'), 'utf8'));

/** Kontext, der jeden Aufruf und jede Zuweisung mitschreibt (Zahlen auf drei Stellen), dazu die Zahl der Füll- und Strichbefehle. */
function schreiber() {
  const log = [], rund = (v) => (typeof v === 'number' ? Math.round(v * 1e3) / 1e3 : v);
  const c = new Proxy({}, {
    get(_, n) {
      if (n === 'createLinearGradient' || n === 'createRadialGradient') { return (...a) => { log.push([n, ...a.map(rund)]); return { addColorStop: (...s) => log.push(['stop', ...s.map(rund)]) }; }; }
      return (...a) => { log.push([n, ...a.map(rund)]); };
    },
    set(_, n, v) { log.push(['=' + n, typeof v === 'object' ? 'Verlauf' : rund(v)]); return true; },
  });
  return { c, log, mal: () => log.filter((z) => z[0] === 'fill' || z[0] === 'stroke').length };
}
const lauf = (d, i, o, w = 360, h = 640) => { const s = schreiber(); api.zeichne(s.c, d, i, w, h, o); return s; };
const summe = (log) => crypto.createHash('sha1').update(JSON.stringify(log)).digest('hex').slice(0, 12);
const HELL = { farbe: 'rgb(255, 255, 255)', grund: 'rgb(15, 23, 42)' };

if (process.env.BW_HASH) {   // Prüfsummen eines älteren Players (für BW15 „Bestandsschutz“)
  const aus = {};
  for (const stil of ['strich', 'punkte']) { for (const i of [0, 45, 89]) { aus[`${stil}-${i}`] = summe(lauf(winken, i, { farbe: 'rgb(255, 255, 255)', stil }).log); } }
  aus['strich-ueber'] = summe(lauf(winken, 10, { farbe: '#fff', stil: 'strich', spiegeln: true, ueber: true, schatten: 'rgb(0, 0, 0)' }).log);
  process.stdout.write(JSON.stringify(aus));
  process.exit(0);
}

const erg = [];
const pruefe = (ok, was) => erg.push([!!ok, was]);
const versuch = (f) => { try { return f(); } catch (e) { return { fehler: String(e && e.message) }; } };

const NEU = ['silhouette', 'piktogramm', 'koerper'];
pruefe(JSON.stringify(api.STILE) === JSON.stringify([...NEU, 'strich', 'punkte']) && api.VORGABE === 'silhouette', 'Player kennt STILE (drei neue, dann strich und punkte) und VORGABE silhouette');

// (3) jeder neue Stil zeichnet, die drei Bilder unterscheiden sich
const logs = {};
for (const stil of NEU) {
  const r = versuch(() => [0, 30, 60].map((i) => lauf(winken, i, { ...HELL, stil })));
  pruefe(Array.isArray(r) && r.every((s) => s.mal() >= 8), `${stil}: zeichnet in drei Bildern (Füll- und Strichbefehle: ${Array.isArray(r) ? r.map((s) => s.mal()).join('/') : r.fehler})`);
  logs[stil] = Array.isArray(r) ? summe(r[0].log) : '';
}
pruefe(new Set(Object.values(logs)).size === 3 && !Object.values(logs).includes(''), 'die drei Stile zeichnen verschieden');
const alt = lauf(winken, 0, { ...HELL, stil: 'strich' });
pruefe(NEU.every((s) => logs[s] !== summe(alt.log)), 'kein neuer Stil zeichnet wie die alte Strichfigur');

// (4) Bestandsschutz: strich/punkte zeichnen Befehl für Befehl wie im Player 3.64.1 (Prüfsummen mit BW_HASH=1 vom alten Player)
const BESTAND = { 'strich-0': '2fa21c5b7320', 'strich-45': '7b4ef650bb39', 'strich-89': '306691d27644', 'punkte-0': '54b4e727dbbb', 'punkte-45': '7cecb72b9f2f', 'punkte-89': '69903aa550fa', 'strich-ueber': '8b419328f583' };
const jetzt = {};
for (const stil of ['strich', 'punkte']) { for (const i of [0, 45, 89]) { jetzt[`${stil}-${i}`] = summe(lauf(winken, i, { farbe: 'rgb(255, 255, 255)', stil }).log); } }
jetzt['strich-ueber'] = summe(lauf(winken, 10, { farbe: '#fff', stil: 'strich', spiegeln: true, ueber: true, schatten: 'rgb(0, 0, 0)' }).log);
for (const [k, soll] of Object.entries(BESTAND)) { pruefe(jetzt[k] === soll, `Bestand ${k}: zeichnet wie 3.64.1 (${jetzt[k]} statt ${soll})`); }

// (5) unbekannter Stil → Vorgabe, nie ein Fehler
for (const stil of ['knochen', '', undefined, null, 'SILHOUETTE', 42, {}, 'strich ']) {
  const r = versuch(() => lauf(winken, 5, { ...HELL, stil }));
  pruefe(!r.fehler && summe(r.log) === summe(lauf(winken, 5, { ...HELL, stil: 'silhouette' }).log), `Stil ${JSON.stringify(stil)} fällt auf silhouette zurück${r.fehler ? ' (Fehler: ' + r.fehler + ')' : ''}`);
}
pruefe(summe(lauf(winken, 5, {}).log) === summe(lauf(winken, 5, { stil: 'silhouette' }).log) && !versuch(() => lauf(winken, 5, undefined)).fehler, 'ohne Angaben (o fehlt) gilt die Vorgabe, ohne Fehler');

// (6) rein: gleiche Eingabe = gleiche Befehle; Spiegeln ändert das Bild; ohne ueber wird gelöscht, mit ueber nicht
for (const stil of NEU) {
  const a = summe(lauf(winken, 20, { ...HELL, stil }).log);
  pruefe(a === summe(lauf(winken, 20, { ...HELL, stil }).log), `${stil}: zweimal gezeichnet = dieselben Befehle (keine Zeit, kein Zufall)`);
  pruefe(a !== summe(lauf(winken, 20, { ...HELL, stil, spiegeln: true }).log), `${stil}: Spiegeln ändert das Bild`);
  pruefe(lauf(winken, 20, { ...HELL, stil }).log.some((z) => z[0] === 'clearRect') && !lauf(winken, 20, { ...HELL, stil, ueber: true }).log.some((z) => z[0] === 'clearRect'), `${stil}: ohne ueber wird gelöscht, mit ueber nicht`);
  const w2 = lauf(winken, 20, { ...HELL, stil }, 200, 200);
  pruefe(w2.mal() > 0, `${stil}: auch in einer quadratischen Fläche (Figur mittig im Bildverhältnis)`);
}

// (7) Daten mit Tiefe (dim 3), unsichtbare Punkte, Bild ohne jeden sichtbaren Punkt: nie ein Fehler
const d3 = JSON.parse(JSON.stringify(winken));
d3.dim = 3;
d3.rahmen = winken.rahmen.map((f) => { const g = []; for (let k = 0; k < 33; k++) { g.push(f[k * 3], f[k * 3 + 1], Math.round(Math.sin(k) * 100) / 1000, f[k * 3 + 2]); } return g; });
const leer = JSON.parse(JSON.stringify(winken));
leer.rahmen[3] = new Array(33 * 3).fill(0);
const halb = JSON.parse(JSON.stringify(winken));
halb.rahmen[4] = halb.rahmen[4].map((v, j) => ((j % 3 === 2 && Math.floor(j / 3) > 10 && Math.floor(j / 3) < 17) ? 0 : v));
for (const stil of NEU) {
  pruefe(!versuch(() => lauf(d3, 7, { ...HELL, stil })).fehler && lauf(d3, 7, { ...HELL, stil }).mal() >= 8, `${stil}: Daten mit Tiefe (dim 3) werden gezeichnet`);
  pruefe(!versuch(() => lauf(leer, 3, { ...HELL, stil })).fehler, `${stil}: ein Bild ohne sichtbaren Punkt ist kein Fehler`);
  pruefe(!versuch(() => lauf(halb, 4, { ...HELL, stil })).fehler, `${stil}: fehlende Arme (unsichtbare Punkte) sind kein Fehler`);
}

// (8) Live-Aufruf: ein Bild, über dem Kamerabild, Schattenfarbe statt Grund
for (const stil of NEU) {
  const einzel = { dim: 2, verhaeltnis: 640 / 480, rahmen: [winken.rahmen[10]] };
  const r = versuch(() => lauf(einzel, 0, { farbe: 'rgb(255, 255, 255)', stil, spiegeln: true, ueber: true, schatten: 'rgba(0, 0, 0, 0.6)' }, 640, 480));
  pruefe(!r.fehler && r.mal() >= 8 && !r.log.some((z) => z[0] === 'clearRect'), `${stil}: Aufruf wie in der Kamera-Vorführung (ein Bild, ueber, schatten)`);
}

// (9) Farbregeln: die tragende Farbe jedes Stils hat gegen den Grund mindestens 3 : 1 (WCAG 1.4.11), auch bei ungünstigen Markenfarben
const lum = (c) => { const t = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * t[0] + 0.7152 * t[1] + 0.0722 * t[2]; };
const kr = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
const PAARE = {
  'weiß auf Nachtblau': ['rgb(255, 255, 255)', 'rgb(27, 36, 48)'],
  'Dunkelblau auf Weiß (dunkler Modus)': ['rgb(15, 15, 26)', 'rgb(255, 255, 255)'],
  'Gelb auf Weiß (zu hell)': ['#f2c200', '#ffffff'],
  'Dunkelgrün auf Schwarz (zu dunkel)': ['rgb(20, 60, 30)', 'rgb(0, 0, 0)'],
  'Mittelgrau auf Mittelgrau': ['rgb(120, 120, 120)', 'rgb(130, 130, 130)'],
  'Magenta auf Hellgrau': ['rgb(190, 30, 120)', 'rgb(230, 230, 235)'],
  'gleiche Farbe': ['rgb(40, 90, 160)', 'rgb(40, 90, 160)'],
};
const tragend = { silhouette: (p) => p.kontur, piktogramm: (p) => p.fuell, koerper: (p) => p.mitte };
pruefe(typeof api.farben === 'function', 'api.farben(stil, farbe, grund) ist da');
for (const [name, [farbe, grund]] of Object.entries(PAARE)) {
  for (const stil of NEU) {
    const p = versuch(() => api.farben(stil, farbe, grund));
    const gr = grund[0] === '#' ? [1, 3, 5].map((n) => parseInt(grund.slice(n, n + 2), 16)) : grund.match(/\d+/g).slice(0, 3).map(Number);
    const k = p.fehler ? 0 : kr(tragend[stil](p), gr);
    pruefe(!p.fehler && k >= 3, `Farbe ${stil}, ${name}: tragende Farbe gegen den Grund ${k.toFixed(1)} : 1 (Soll ≥ 3)`);
  }
}
const ps = versuch(() => api.farben('silhouette', 'rgb(255, 255, 255)', 'rgb(27, 36, 48)'));
pruefe(!ps.fehler && kr(ps.fuell, ps.kontur) >= 1.2 && JSON.stringify(ps.fuell) !== JSON.stringify(ps.kontur), 'Silhouette: Körper und Kontur sind verschiedene Farben');
const pk = versuch(() => api.farben('koerper', 'rgb(255, 255, 255)', 'rgb(27, 36, 48)'));
pruefe(!pk.fehler && lum(pk.hell) > lum(pk.mitte) && lum(pk.mitte) > lum(pk.dunkel), 'Körper: Verlauf von hell über Mitte nach dunkel');
pruefe(typeof api.farben === 'function' && !versuch(() => api.farben('silhouette', 'kein Wert', undefined)).fehler && !versuch(() => api.farben('koerper', '', '')).fehler, 'unlesbare Farben: Ersatzfarben, kein Fehler');
const pp = versuch(() => api.farben('piktogramm', 'rgb(255, 255, 255)', 'rgb(27, 36, 48)'));
pruefe(!pp.fehler && JSON.stringify(pp.luecke) === JSON.stringify([27, 36, 48]), 'Piktogramm: die Lücke hat die Farbe des Grundes');

process.stdout.write(JSON.stringify(erg));
