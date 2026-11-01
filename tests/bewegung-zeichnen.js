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
  for (const stil of ['silhouette', 'piktogramm', 'koerper']) {   // 3.67.0: auch die drei Figur-Stile müssen nach dem Umbau Befehl für Befehl gleich zeichnen
    for (const i of [0, 45, 89]) { aus[`${stil}-${i}`] = summe(lauf(winken, i, { ...HELL, stil }).log); }
    aus[`${stil}-ueber`] = summe(lauf(winken, 10, { farbe: '#fff', stil, spiegeln: true, ueber: true, schatten: 'rgb(0, 0, 0)' }).log);
  }
  process.stdout.write(JSON.stringify(aus));
  process.exit(0);
}

const erg = [];
const pruefe = (ok, was) => erg.push([!!ok, was]);
const versuch = (f) => { try { return f(); } catch (e) { return { fehler: String(e && e.message) }; } };

const NEU = ['silhouette', 'piktogramm', 'koerper'];
pruefe(JSON.stringify(api.STILE) === JSON.stringify([...NEU, 'strich', 'punkte', 'figur']) && api.VORGABE === 'silhouette', 'Player kennt STILE (drei neue, dann strich und punkte, seit 3.67.0 am Ende figur) und VORGABE silhouette');

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

// (4b) 3.67.0: auch silhouette, piktogramm und koerper zeichnen nach dem Umbau Befehl für Befehl wie im Player 3.66.0 (Prüfsummen mit BW_HASH=1 vom 3.66.0-Player)
const BESTAND3 = {
  'silhouette-0': '70a312bbee93', 'silhouette-45': 'f8709299f63e', 'silhouette-89': '8de080cf6570', 'silhouette-ueber': '5cd0057fd8b8',
  'piktogramm-0': '1d37276eae0c', 'piktogramm-45': '8ed615b02298', 'piktogramm-89': 'fa91e36d8636', 'piktogramm-ueber': '134e4bb5bbab',
  'koerper-0': '9ad957145297', 'koerper-45': '6e79309fd0dd', 'koerper-89': 'd78e7f4161e7', 'koerper-ueber': '8cd6b9a79c97',
};
for (const stil of NEU) {
  for (const i of [0, 45, 89]) { jetzt[`${stil}-${i}`] = summe(lauf(winken, i, { ...HELL, stil }).log); }
  jetzt[`${stil}-ueber`] = summe(lauf(winken, 10, { farbe: '#fff', stil, spiegeln: true, ueber: true, schatten: 'rgb(0, 0, 0)' }).log);
}
for (const [k, soll] of Object.entries(BESTAND3)) { pruefe(jetzt[k] === soll, `Bestand ${k}: zeichnet wie 3.66.0 (${jetzt[k]} statt ${soll})`); }

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
const pl = versuch(() => api.farben('koerper', 'rgb(15, 15, 26)', 'rgb(255, 255, 255)'));
pruefe(!pl.fehler && lum(pl.hell) > lum(pl.mitte) && lum(pl.mitte) > lum(pl.dunkel), 'Körper auf hellem Grund: die helle Seite ist auch dort die hellere (Licht von links oben)');
pruefe(typeof api.farben === 'function' && !versuch(() => api.farben('silhouette', 'kein Wert', undefined)).fehler && !versuch(() => api.farben('koerper', '', '')).fehler, 'unlesbare Farben: Ersatzfarben, kein Fehler');
const pp = versuch(() => api.farben('piktogramm', 'rgb(255, 255, 255)', 'rgb(27, 36, 48)'));
pruefe(!pp.fehler && JSON.stringify(pp.luecke) === JSON.stringify([27, 36, 48]), 'Piktogramm: die Lücke hat die Farbe des Grundes');


// ===== (10) 3.67.0 Stil „figur“: Kopf, Gesicht (vier Ausdrücke, Lidschlag), Kleidung in Markenfarbe; nur die Figur, keine Bühne =====
const FIG = { ...HELL, stil: 'figur' };
const AUSD = ['neutral', 'muede', 'ueberrascht', 'entschlossen'];
const fortan = (stil) => summe(lauf(winken, 0, { ...HELL, stil }).log);
const fg = versuch(() => [0, 30, 60].map((i) => lauf(winken, i, FIG)));
pruefe(Array.isArray(fg) && fg.every((s) => s.mal() >= 20), `figur: zeichnet in drei Bildern (Füll- und Strichbefehle: ${Array.isArray(fg) ? fg.map((s) => s.mal()).join('/') : fg.fehler})`);
pruefe(Array.isArray(fg) && [...NEU, 'strich', 'punkte'].every((s) => fortan(s) !== summe(fg[0].log)), 'figur wird erkannt: zeichnet anders als jede der fünf anderen Darstellungen (kein Rückfall auf die Vorgabe)');
pruefe(JSON.stringify(api.AUSDRUECKE) === JSON.stringify(AUSD), 'Player nennt die vier Ausdrücke (neutral zuerst = Vorgabe)');

// Ausdruck: vier Zeichenbefehlsfolgen, nur im Gesicht; unbekannt = neutral
const gesicht = AUSD.map((a) => lauf(winken, 20, { ...FIG, ausdruck: a }).log);
pruefe(new Set(gesicht.map(summe)).size === 4, 'Ausdruck: neutral, müde, überrascht und entschlossen zeichnen vier verschiedene Befehlsfolgen');
const vorClip = (log) => JSON.stringify(log.slice(0, log.findIndex((z) => z[0] === 'clip')));
pruefe(gesicht.every((l) => l.some((z) => z[0] === 'clip')) && new Set(gesicht.map(vorClip)).size === 1, 'Ausdruck ändert nur das Gesicht (alles vor dem Gesicht ist gleich)');
const lider = (log) => JSON.stringify(log.filter((z) => z[0] === 'ellipse' || z[0] === 'rotate'));
pruefe(new Set(gesicht.map(lider)).size === 4, 'Ausdruck ändert die Lider (Ellipsen und Neigung) in allen vier Fällen');
pruefe(summe(lauf(winken, 20, FIG).log) === summe(gesicht[0]), 'ohne Angabe gilt neutral');
for (const a of ['wuetend', '', null, 'MUEDE', 'muede ', 42, {}, ['muede']]) {
  const r = versuch(() => lauf(winken, 20, { ...FIG, ausdruck: a }));
  pruefe(!r.fehler && summe(r.log) === summe(gesicht[0]), `Ausdruck ${JSON.stringify(a)} fällt auf neutral zurück${r.fehler ? ' (Fehler: ' + r.fehler + ')' : ''}`);
}

// Lidschlag: reine Zeitfunktion, bei „weniger Bewegung“ aus
const offen = summe(lauf(winken, 20, { ...FIG, zeit: 0 }).log), BLINK_MS = 2280;
pruefe(typeof api.blinzeln === 'function' && api.blinzeln(0) === 0 && api.blinzeln(1000) === 0 && api.blinzeln(BLINK_MS) > 0.95, 'blinzeln(ms): offen außerhalb des Lidschlags, in der Mitte des Schlags (2,28 s) fast zu');
pruefe(summe(lauf(winken, 20, { ...FIG, zeit: BLINK_MS }).log) !== offen, 'Blinzeln: im Lidschlag zeichnet die Figur anders als mit offenen Augen');
pruefe(summe(lauf(winken, 20, { ...FIG, zeit: BLINK_MS }).log) === summe(lauf(winken, 20, { ...FIG, zeit: BLINK_MS }).log), 'Blinzeln ist rein: dieselbe Zeit = dieselben Befehle');
pruefe(summe(lauf(winken, 20, { ...FIG, zeit: BLINK_MS, ruhe: true }).log) === offen && offen === summe(lauf(winken, 20, FIG).log), 'weniger Bewegung (ruhe): kein Blinzeln, auch im Lidschlag nicht');
// Zeitsteuerung: spielen() reicht die Zeit weiter; mit ruhe:true blinzelt auch das laufende Bild nicht
{
  const alt = { getComputedStyle: globalThis.getComputedStyle, raf: globalThis.requestAnimationFrame, caf: globalThis.cancelAnimationFrame };
  const spiel = (opt) => {
    let wartet = null;
    globalThis.getComputedStyle = () => ({ color: 'rgb(255, 255, 255)', backgroundColor: 'rgb(27, 36, 48)' });
    globalThis.requestAnimationFrame = (f) => { wartet = f; return 1; };
    globalThis.cancelAnimationFrame = () => {};
    const s = schreiber(), cv = { width: 360, height: 640, nodeType: 1, parentNode: null, getContext: () => s.c };
    const sp = api.spielen(cv, 'figur', null, opt);
    sp.setze(winken, false);
    wartet(0); wartet(BLINK_MS);   // erster Takt, dann springt die Uhr in den Lidschlag
    const l = s.log, letzt = l.map((z) => z[0]).lastIndexOf('clearRect');
    return { nr: sp.nr(), befehle: summe(l.slice(letzt - 1)) };   // zeichne() beginnt mit save, dann clearRect
  };
  try {
    const a = versuch(() => spiel({ ruhe: false })), b = versuch(() => spiel({ ruhe: true }));
    pruefe(!a.fehler && !b.fehler && a.nr === b.nr && a.befehle !== b.befehle, `spielen(): mit Lidschlag-Zeit blinzelt das laufende Bild, mit ruhe:true nicht ${a.fehler || b.fehler || ''}`);
    const ref = versuch(() => summe(lauf(winken, b.nr, { farbe: 'rgb(255, 255, 255)', grund: 'rgb(27, 36, 48)', stil: 'figur', zeit: 0 }).log));
    pruefe(!ref.fehler && ref === b.befehle, 'spielen() mit ruhe:true zeichnet dasselbe Bild wie zeichne() ohne Zeit');
  } finally { globalThis.getComputedStyle = alt.getComputedStyle; globalThis.requestAnimationFrame = alt.raf; globalThis.cancelAnimationFrame = alt.caf; }
}

// Nur die Figur: kein Verlauf über die ganze Fläche, keine Lichtpunkte, kein Regen — die Bühne gehört der Seite
{
  const l = lauf(winken, 20, FIG).log;
  pruefe(!l.some((z) => z[0] === 'fillRect') && !l.some((z) => z[0] === 'createLinearGradient'), 'figur zeichnet keine Bühne (kein fillRect, kein Linearverlauf)');
  pruefe(l[0][0] === 'save' && l.filter((z) => z[0] === 'clearRect').length === 1, 'figur löscht die Fläche genau einmal (Hintergrund bleibt durchsichtig)');
}

// Reinheit, Spiegeln, quadratische Fläche, Tiefe, fehlende Punkte, Kamera-Aufruf wie bei den anderen Stilen
{
  const a = summe(lauf(winken, 20, FIG).log);
  pruefe(a === summe(lauf(winken, 20, FIG).log), 'figur: zweimal gezeichnet = dieselben Befehle (keine Zeit, kein Zufall)');
  pruefe(a !== summe(lauf(winken, 20, { ...FIG, spiegeln: true }).log), 'figur: Spiegeln ändert das Bild');
  pruefe(lauf(winken, 20, FIG).log.some((z) => z[0] === 'clearRect') && !lauf(winken, 20, { ...FIG, ueber: true }).log.some((z) => z[0] === 'clearRect'), 'figur: ohne ueber wird gelöscht, mit ueber nicht');
  pruefe(lauf(winken, 20, FIG, 200, 200).mal() > 0, 'figur: auch in einer quadratischen Fläche');
  pruefe(!versuch(() => lauf(d3, 7, FIG)).fehler && lauf(d3, 7, FIG).mal() >= 20, 'figur: Daten mit Tiefe (dim 3) werden gezeichnet, Teile nach Tiefe sortiert');
  pruefe(!versuch(() => lauf(leer, 3, FIG)).fehler, 'figur: ein Bild ohne sichtbaren Punkt ist kein Fehler');
  pruefe(!versuch(() => lauf(halb, 4, FIG)).fehler, 'figur: fehlende Arme (unsichtbare Punkte) sind kein Fehler');
  const einzel = { dim: 2, verhaeltnis: 640 / 480, rahmen: [winken.rahmen[10]] };
  const r = versuch(() => lauf(einzel, 0, { farbe: 'rgb(255, 255, 255)', stil: 'figur', spiegeln: true, ueber: true, schatten: 'rgba(0, 0, 0, 0.6)' }, 640, 480));
  pruefe(!r.fehler && r.mal() >= 20 && !r.log.some((z) => z[0] === 'clearRect'), 'figur: Aufruf wie in der Kamera-Vorführung (ein Bild, ueber, schatten), ohne Ausdruck');
}

// Kontrast Figur gegen Grund ≥ 3 : 1 (WCAG 1.4.11): die Außenkante — der Rand, wenn der dunkle Kopf allein zu schwach ist, sonst Kopf, Kleid, Hose, Schuhe
const krs = (a, b) => (Array.isArray(a) && Array.isArray(b) ? kr(a, b) : 0);   // fehlt ein Wert (alter Player), ist das Ergebnis 0 statt eines Absturzes
const lums = (c) => (Array.isArray(c) ? lum(c) : -1);
const grundRgb = (g) => (g[0] === '#' ? [1, 3, 5].map((n) => parseInt(g.slice(n, n + 2), 16)) : g.match(/\d+/g).slice(0, 3).map(Number));
pruefe(typeof api.farben === 'function' && !versuch(() => api.farben('figur', 'kein Wert', undefined)).fehler && !versuch(() => api.farben('figur', '', '')).fehler, 'figur: unlesbare Farben bekommen Ersatzfarben, kein Fehler');
for (const [name, [farbe, grund]] of Object.entries(PAARE)) {
  const p = versuch(() => api.farben('figur', farbe, grund)), gr = grundRgb(grund);
  const aussen = p.fehler ? [] : (p.randNoetig ? [p.rand] : [p.kopf, p.kleid, p.hose, p.schuh]);
  const k = aussen.length ? Math.min(...aussen.map((c) => krs(c, gr))) : 0, kk = p.fehler ? 0 : krs(p.kleid, gr);
  pruefe(!p.fehler && k >= 3 && kk >= 3, `Farbe figur, ${name}: Außenkante ${k.toFixed(1)} : 1, Kleid ${kk.toFixed(1)} : 1 gegen den Grund (Soll ≥ 3)`);
}
const fd = versuch(() => api.farben('figur', 'rgb(255, 255, 255)', 'rgb(27, 36, 48)')), fh = versuch(() => api.farben('figur', 'rgb(15, 15, 26)', 'rgb(255, 255, 255)'));
pruefe(!fd.fehler && fd.randNoetig === true && lums(fd.rand) > 0.5 && krs(fd.kopf, [27, 36, 48]) > 0 && krs(fd.kopf, [27, 36, 48]) < 3, 'figur auf dunklem Grund: der dunkle Kopf allein ist zu schwach, der Rand um die Figur ist hell');
pruefe(!fh.fehler && fh.randNoetig === false && lums(fh.kopf) >= 0 && lums(fh.kopf) < 0.05 && krs(fh.kopf, [255, 255, 255]) >= 3, 'figur auf hellem Grund: der Kopf ist dunkel und trägt allein');
{
  const l = lauf(winken, 20, { farbe: 'rgb(255, 255, 255)', grund: 'rgb(27, 36, 48)', stil: 'figur' }).log;
  pruefe(!fd.fehler && Array.isArray(fd.rand) && l.some((z) => z[0] === '=fillStyle' && z[1] === `rgb(${fd.rand.join(',')})`), 'figur auf dunklem Grund: der helle Rand wird auch gezeichnet');
}

process.stdout.write(JSON.stringify(erg));
