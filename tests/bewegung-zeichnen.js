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

const block = (name, f) => { try { f(); } catch (e) { pruefe(false, `${name}: Prüfung bricht ab (${e && e.message})`); } };   // ein älterer Player ohne die Funktion = rote Prüfung statt Absturz
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


// ===== (11) 3.68.0 Requisit „Stab“: aus den Handpunkten errechnet, in jedem Stil gezeichnet, geglättet, nie ins Blaue =====
const STAB = { requisit: 'stab' };
const ALLE = [...NEU, 'strich', 'punkte', 'figur'];
const FW = 360, FH = 640, RH = Math.min(FH, FW / winken.verhaeltnis);
const st = (i, o, d = winken) => versuch(() => api.stab(d, i, FW, FH, o));
const laenge = (s) => Math.hypot(s.b.x - s.a.x, s.b.y - s.a.y);
const px = (d, i, k) => ({ x: d.rahmen[i][k * 3] * RH * d.verhaeltnis + (FW - RH * d.verhaeltnis) / 2, y: d.rahmen[i][k * 3 + 1] * RH + (FH - RH) / 2 });
const abstandLinie = (p, s) => Math.abs((s.b.x - s.a.x) * (s.a.y - p.y) - (s.a.x - p.x) * (s.b.y - s.a.y)) / laenge(s);
const winkelZu = (s, von, nach) => { const a = [s.b.x - s.a.x, s.b.y - s.a.y], b = [nach.x - von.x, nach.y - von.y]; return Math.acos(Math.abs(a[0] * b[0] + a[1] * b[1]) / Math.hypot(...a) / Math.hypot(...b)) * 180 / Math.PI; };
pruefe(typeof api.stab === 'function' && JSON.stringify(api.REQUISITE) === '["stab"]' && JSON.stringify(api.STAB_HAENDE) === '["rechts","links","beide"]' && JSON.stringify(api.STAB_LAENGEN) === '["kurz","mittel","lang"]', 'Player nennt das Requisit „stab“ mit Hand (rechts, links, beide) und Länge (kurz, mittel, lang)');

// Lage: der Stab geht durch die Hand, folgt dem Unterarm, ragt nach vorn heraus; die Länge ist fest und wächst mit der Stufe
block('Stab Lage', () => {
  const s = st(45, { ...STAB }), r = st(45, { ...STAB, hand: 'rechts' }), l = st(45, { ...STAB, hand: 'links' });
  pruefe(s && !s.fehler && JSON.stringify(s) === JSON.stringify(r), 'Stab: ohne Handangabe gilt die rechte Hand');
  const hand = px(winken, 45, 16), ell = px(winken, 45, 14), lw = px(winken, 45, 15), le = px(winken, 45, 13);
  pruefe(s && !s.fehler && abstandLinie(hand, s) <= 0.03 * RH, `Stab rechts: geht durch die Hand (Abstand Handgelenk – Stab ${s && !s.fehler ? (abstandLinie(hand, s) / RH * 100).toFixed(1) : '?'} % der Bildhöhe, Soll ≤ 3)`);
  pruefe(s && !s.fehler && winkelZu(s, ell, hand) <= 45, `Stab rechts: folgt dem Unterarm (Winkel ${s && !s.fehler ? winkelZu(s, ell, hand).toFixed(0) : '?'}° zur Linie Ellbogen–Handgelenk, Soll ≤ 45)`);
  pruefe(s && !s.fehler && Math.hypot(s.b.x - ell.x, s.b.y - ell.y) > Math.hypot(s.a.x - ell.x, s.a.y - ell.y), 'Stab rechts: das längere Ende ragt von der Hand weg nach vorn (Verlängerung des Unterarms)');
  pruefe(l && !l.fehler && abstandLinie(lw, l) <= 0.03 * RH && winkelZu(l, le, lw) <= 45 && JSON.stringify(l) !== JSON.stringify(s), 'Stab links: sitzt an der linken Hand, nicht an der rechten');
  const k = st(45, { ...STAB, laenge: 'kurz' }), m = st(45, { ...STAB, laenge: 'mittel' }), g = st(45, { ...STAB, laenge: 'lang' }), u = st(45, { ...STAB, laenge: 'riesig' });
  const rumpf = Math.hypot((px(winken, 45, 11).x + px(winken, 45, 12).x - px(winken, 45, 23).x - px(winken, 45, 24).x) / 2, (px(winken, 45, 11).y + px(winken, 45, 12).y - px(winken, 45, 23).y - px(winken, 45, 24).y) / 2);
  pruefe([k, m, g].every((x) => x && !x.fehler) && Math.abs(laenge(m) / laenge(g) - 0.7) < 0.01 && Math.abs(laenge(k) / laenge(g) - 0.4) < 0.01 && Math.abs(laenge(g) / (3.1 * rumpf) - 1) < 0.08,
    `Länge: kurz : mittel : lang = 0,4 : 0,7 : 1, lang ≈ eine Körpergröße (3,1 Rumpflängen; gemessen ${g && !g.fehler ? (laenge(g) / rumpf).toFixed(2) : '?'})`);
  pruefe(u && !u.fehler && JSON.stringify(u) === JSON.stringify(m), 'Länge: ein unbekannter Wert gilt als mittel');
  const ls = [15, 30, 45, 60, 75].map((i) => st(i, { ...STAB }));
  pruefe(ls.every((x) => x && !x.fehler) && Math.max(...ls.map(laenge)) - Math.min(...ls.map(laenge)) < 1e-6, 'Länge ist fest: in allen Bildern gleich lang (hängt nicht an der Haltung)');
  const sp = st(45, { ...STAB, spiegeln: true });
  pruefe(sp && !sp.fehler && Math.abs(sp.a.x - (FW - s.a.x)) < 1e-6 && Math.abs(sp.b.x - (FW - s.b.x)) < 1e-6 && Math.abs(sp.a.y - s.a.y) < 1e-6, 'Spiegeln: der Stab wird mitgespiegelt');
});

// Beide Hände: ein Stab, mittig zwischen den Griffen, entlang der Linie von Hand zu Hand, ragt auf beiden Seiten heraus
block('Stab beide Hände', () => {
  const b = st(45, { ...STAB, hand: 'beide' }), h1 = px(winken, 45, 16), h2 = px(winken, 45, 15);
  pruefe(b && !b.fehler, 'Beide Hände: ein Stab entsteht');
  const mit = { x: (b.a.x + b.b.x) / 2, y: (b.a.y + b.b.y) / 2 }, hm = { x: (h1.x + h2.x) / 2, y: (h1.y + h2.y) / 2 };
  pruefe(b && !b.fehler && Math.hypot(mit.x - hm.x, mit.y - hm.y) <= 0.04 * RH, `Beide Hände: die Mitte des Stabes liegt zwischen den Handpunkten (Abstand ${b && !b.fehler ? (Math.hypot(mit.x - hm.x, mit.y - hm.y) / RH * 100).toFixed(1) : '?'} % der Bildhöhe, Soll ≤ 4)`);
  pruefe(b && !b.fehler && winkelZu(b, h1, h2) <= 8, `Beide Hände: der Stab liegt auf der Linie von Hand zu Hand (Abweichung ${b && !b.fehler ? winkelZu(b, h1, h2).toFixed(1) : '?'}°, Soll ≤ 8)`);
  pruefe(b && !b.fehler && laenge(b) >= 1.29 * Math.hypot(h1.x - h2.x, h1.y - h2.y), 'Beide Hände: der Stab ist länger als der Abstand der Hände (ragt auf beiden Seiten heraus)');
});

// Fehlt die Hand, fehlt der Stab: ehrlich weglassen, nichts irgendwohin zeichnen
block('Stab fehlende Hand', () => {
  const ohnePunkte = (ks, i = 45) => { const d = JSON.parse(JSON.stringify(winken)); ks.forEach((k) => { for (let a = 0; a < 3; a++) d.rahmen[i][k * 3 + a] = 0; }); return d; };
  const faelle = { 'Handgelenk': [16], 'Ellbogen': [14], 'alle drei Handpunkte': [18, 20, 22] };
  for (const [name, ks] of Object.entries(faelle)) {
    const d = ohnePunkte(ks);
    pruefe(st(45, { ...STAB }, d) === null, `Hand fehlt (${name} nicht sichtbar): kein Stab`);
    pruefe(ALLE.every((stil) => summe(lauf(d, 45, { ...HELL, stil, ...STAB }).log) === summe(lauf(d, 45, { ...HELL, stil }).log)), `Hand fehlt (${name}): alle sechs Stile zeichnen dann dasselbe wie ohne Requisit (nichts wird irgendwohin gezeichnet)`);
  }
  const eins = ohnePunkte([18, 20]);
  const e = st(45, { ...STAB }, eins);
  pruefe(e && !e.fehler && Number.isFinite(e.a.x) && Number.isFinite(e.b.y), 'ein einziger Handpunkt (Daumen) genügt für die Handmitte: der Stab bleibt');
  const d2 = ohnePunkte([16]);
  pruefe(st(45, { ...STAB, hand: 'beide' }, d2) === null && st(45, { ...STAB, hand: 'links' }, d2) !== null, 'beide Hände verlangt beide: fehlt eine, gibt es keinen Stab; die linke Hand allein bleibt davon unberührt');
  const nb = ohnePunkte([14, 16, 18, 20, 22], 44), z = st(45, { ...STAB }, nb);
  pruefe(z && !z.fehler && [z.a.x, z.a.y, z.b.x, z.b.y].every(Number.isFinite), 'fehlt die Hand nur im Nachbarbild, bleibt der Stab bestehen (die Glättung überspringt das Bild, kein NaN)');
});

// Jeder Stil zeichnet ihn; unbekannte Requisiten werden ignoriert; Sonderfälle ohne Fehler
for (const stil of ALLE) {
  const mit = versuch(() => lauf(winken, 45, { ...HELL, stil, ...STAB })), ohne = lauf(winken, 45, { ...HELL, stil });
  pruefe(!mit.fehler && summe(mit.log) !== summe(ohne.log) && mit.mal() > ohne.mal(), `${stil}: mit Requisit „stab“ kommt mindestens eine Zeichnung dazu (${mit.fehler || `${ohne.mal()} → ${mit.mal()} Füll- und Strichbefehle`})`);
  const fremd = ['knochen', '', null, 'STAB', 'stab ', 42, {}].map((x) => versuch(() => lauf(winken, 45, { ...HELL, stil, requisit: x })));
  pruefe(fremd.every((x) => !x.fehler && summe(x.log) === summe(ohne.log)), `${stil}: ein unbekanntes Requisit zeichnet nichts dazu und ist kein Fehler`);
  const vier = [versuch(() => lauf(d3, 7, { ...HELL, stil, ...STAB })), versuch(() => lauf(winken, 45, { ...HELL, stil, ...STAB, spiegeln: true, ueber: true, schatten: 'rgba(0, 0, 0, 0.6)' })),
    versuch(() => lauf(winken, 45, { ...HELL, stil, ...STAB, hand: 'beide', laenge: 'lang' }, 200, 200)), versuch(() => lauf({ dim: 2, verhaeltnis: 640 / 480, rahmen: [winken.rahmen[10]] }, 0, { ...HELL, stil, ...STAB }, 640, 480))];
  pruefe(vier.every((x) => !x.fehler), `${stil}: Stab auch mit Tiefe (dim 3), gespiegelt über dem Bild, in einer quadratischen Fläche und bei nur einem Bild (Kamera) ohne Fehler`);
}
block('Stab Kontrast', () => {   // Kontrast des Stabes gegen den Grund ≥ 3 : 1
  for (const [name, [farbe, grund]] of Object.entries(PAARE)) {
    const gr = grundRgb(grund);
    const farbeVon = (stil) => { const l = lauf(winken, 45, { farbe, grund, stil, ...STAB }).log, z = l.filter((x) => x[0] === '=strokeStyle').pop(); return z && z[1].match(/\d+/g).slice(0, 3).map(Number); };
    const ks = ['strich', 'punkte'].map((stil) => krs(farbeVon(stil), gr)), fp = versuch(() => api.farben('figur', farbe, grund));
    pruefe(ks.every((k) => k >= 3) && !fp.fehler && krs(fp.kleid, gr) >= 3, `Stab-Farbe, ${name}: strich ${ks[0].toFixed(1)} : 1, punkte ${ks[1].toFixed(1)} : 1, figur (Kleidfarbe) ${fp.fehler ? 0 : krs(fp.kleid, gr).toFixed(1)} : 1 gegen den Grund (Soll ≥ 3)`);
  }
});

// Glättung: Zittern vor und nach (Maß wie Bahn BF: mittlere Bewegung der Stabspitze von Bild zu Bild in % der Bildhöhe; Ruck = mittlere zweite Differenz).
// „Vorher“ = jedes Bild einzeln (ohne Nachbarn, also ungeglättet), „nachher“ = die ganze Aufnahme. Rauschen: AR(0,6) mit den Werten von Bahn BF, Zufallszahlen fest gesät.
block('Stab Glättung', () => {
  const mul = (a) => () => { a |= 0; a = (a + 0x6D2B79F5) | 0; let t = Math.imul(a ^ (a >>> 15), 1 | a); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const SIG = { 11: 0.004, 12: 0.004, 13: 0.006, 14: 0.006, 15: 0.008, 16: 0.008, 17: 0.011, 18: 0.011, 19: 0.011, 20: 0.011, 21: 0.011, 22: 0.011 };
  const rausch = (seed) => {
    const r = mul(seed), ar = {}, d = JSON.parse(JSON.stringify(winken));
    d.rahmen = winken.rahmen.map((f) => { const g = f.slice(); for (const k in SIG) for (const a of [0, 1]) { const q = k + '_' + a; ar[q] = 0.6 * (ar[q] || 0) + Math.sqrt(-2 * Math.log(1 - r())) * Math.cos(2 * Math.PI * r()) * SIG[k] * 0.8; g[k * 3 + a] = Math.round(Math.min(1, Math.max(0, f[k * 3 + a] + ar[q])) * 1e3) / 1e3; } return g; });
    return d;
  };
  const spur = (d, o, einzeln) => { const t = []; for (let i = 16; i <= 75; i++) { const s = einzeln ? api.stab({ ...d, rahmen: [d.rahmen[i]] }, 0, FW, FH, o) : api.stab(d, i, FW, FH, o); t.push(s && s.b); } return t; };
  const mittel = (t, f) => { let z = 0, n = 0; for (let i = 1; i < t.length - 1; i++) if (t[i - 1] && t[i] && t[i + 1]) { z += f(t, i); n++; } return 100 * z / n / FH; };
  const zittern = (t) => mittel(t, (x, i) => Math.hypot(x[i].x - x[i - 1].x, x[i].y - x[i - 1].y));
  const ruck = (t) => mittel(t, (x, i) => Math.hypot(x[i + 1].x - 2 * x[i].x + x[i - 1].x, x[i + 1].y - 2 * x[i].y + x[i - 1].y));
  const abw = (t, c) => { let z = 0; t.forEach((p, i) => { z += Math.hypot(p.x - c[i].x, p.y - c[i].y); }); return 100 * z / t.length / FH; };
  const o = { ...STAB, laenge: 'mittel' }, sauber = spur(winken, o, false), zs = zittern(sauber), rs = ruck(sauber), reihen = [];
  for (const seed of [1, 2, 3]) {
    const n = rausch(seed), roh = spur(n, o, true), gl = spur(n, o, false);
    reihen.push({ zr: zittern(roh), zg: zittern(gl), rr: ruck(roh), rg: ruck(gl), ar: abw(roh, sauber), ag: abw(gl, sauber) });
  }
  const f1 = (v) => v.toFixed(2);
  pruefe(reihen.every((x) => x.rg - rs <= 0.4 * (x.rr - rs)), `Glättung, Ruck der Stabspitze (zweite Differenz, % der Bildhöhe): sauber ${f1(rs)}, verrauscht ungeglättet ${reihen.map((x) => f1(x.rr)).join('/')}, geglättet ${reihen.map((x) => f1(x.rg)).join('/')} — das Rauschen sinkt auf höchstens 40 %`);
  pruefe(reihen.every((x) => x.zg - zs <= 0.6 * (x.zr - zs)), `Glättung, Zittern der Stabspitze (Bewegung je Bild, % der Bildhöhe): sauber ${f1(zs)}, ungeglättet ${reihen.map((x) => f1(x.zr)).join('/')}, geglättet ${reihen.map((x) => f1(x.zg)).join('/')} — der Rauschanteil sinkt auf höchstens 60 %`);
  pruefe(reihen.every((x) => x.ag < x.ar), `Glättung: die geglättete Stabspitze liegt näher an der wahren Lage als die ungeglättete (Abweichung ${reihen.map((x) => f1(x.ag)).join('/')} gegen ${reihen.map((x) => f1(x.ar)).join('/')} % der Bildhöhe)`);
});

// ===== (12) 3.68.0 Hand vor dem Kinn: die Fäuste liegen (ohne Tiefe) über dem Kopf; berührt eine den Kopf, hat sie einen hellen Ring, frei im Raum nicht =====
block('Hand vor dem Kinn', () => {
  // Beide Fäuste vor dem Kinn (Schlag-Deckung): Bild 20 der Beispielbewegung mit Handgelenken kurz unter der Nase, Ellbogen unten
  const kinn = JSON.parse(JSON.stringify(winken)), f = kinn.rahmen[20], nase = [f[0], f[1]];
  const setze = (j, x, y) => { f[j * 3] = x; f[j * 3 + 1] = y; f[j * 3 + 2] = 1; };
  [[13, 15, 17, 19, 21, 1], [14, 16, 18, 20, 22, -1]].forEach(([e, w, a, b, c, v]) => {
    setze(e, nase[0] + v * 0.12, nase[1] + 0.25); setze(w, nase[0] + v * 0.05, nase[1] + 0.085);
    setze(a, nase[0] + v * 0.06, nase[1] + 0.06); setze(b, nase[0] + v * 0.04, nase[1] + 0.055); setze(c, nase[0] + v * 0.03, nase[1] + 0.06);
  });
  const l = lauf(kinn, 20, FIG).log, r = 0.028 * RH;
  const bogen = (log, rad) => log.slice(log.map((z) => z[0]).lastIndexOf('createRadialGradient')).filter((z) => z[0] === 'arc' && Math.abs(z[3] - rad) < 0.05).length;
  pruefe(bogen(l, r) >= 2, `Hand vor dem Kinn: beide Fäuste werden nach dem Kopf gezeichnet (${bogen(l, r)} Kreise der Handgröße nach dem Kopf, Soll 2)`);
  pruefe(bogen(l, r + 0.0035 * RH) >= 2, 'Hand vor dem Kinn: jede Faust hat einen hellen Ring und löst sich vom dunklen Kopf');
  const frei = lauf(winken, 20, FIG).log;
  pruefe(bogen(frei, r) >= 2 && bogen(frei, r + 0.0035 * RH) === 0, 'Fäuste frei im Raum (Arm erhoben, Arm unten): kein Ring — die Außenkante der Figur bleibt dunkel (Kontrast zum Grund)');
  const mitSt = lauf(kinn, 20, { ...FIG, ...STAB, hand: 'beide', laenge: 'kurz' }).log;
  pruefe(bogen(mitSt, r) >= 4, 'Stab in beiden Händen (figur): die Fäuste werden über dem Stab noch einmal gezeichnet, sie umschließen ihn');
});

// ===== (13) 3.68.0 Gesichter: „überrascht“ ist kein Totenkopf, „neutral“ und „müde“ unterscheiden sich, „entschlossen“ hat Brauen =====
block('Gesichter', () => {
  const teil = (log) => { const i0 = log.findIndex((z) => z[0] === 'clip'); let t = 1, j = i0 + 1; for (; j < log.length && t > 0; j++) { if (log[j][0] === 'save') t++; else if (log[j][0] === 'restore') t--; } return log.slice(i0, j); };
  const G = {}, KOPFFARBE = 'rgb(12,14,17)';
  AUSD.forEach((a) => { G[a] = teil(lauf(winken, 20, { ...FIG, ausdruck: a, ruhe: true }).log); });
  const aug = (t) => { const r = t.findIndex((z) => z[0] === 'rect'), e = t.findIndex((z, n) => n > r && z[0] === 'ellipse'); return { lid: (t[r][2] + t[e][4]) / (2 * t[e][4]), ry: t[e][4], rx: t[e][3] }; };
  const dreh = (t) => t.filter((z) => z[0] === 'rotate').map((z) => z[1]), geh = (t) => t.filter((z) => z[0] === 'translate');
  const A = Object.fromEntries(AUSD.map((a) => [a, versuch(() => aug(G[a]))]));
  pruefe(AUSD.every((a) => !A[a].fehler), 'Gesicht: jedes Auge ist eine Ellipse, vom Oberlid oben abgeschnitten');
  pruefe(AUSD.every((a) => G[a].filter((z) => z[0] === '=fillStyle' && z[1] === KOPFFARBE).length >= 2), 'Gesicht: jedes Auge hat eine dunkle Pupille (zwei Pupillen in allen vier Ausdrücken)');
  pruefe(AUSD.every((a) => G[a].filter((z) => z[0] === 'quadraticCurveTo').length >= 2), 'Gesicht: jeder Ausdruck hat zwei Brauen');
  pruefe(A.neutral.lid < 0.05 && A.muede.lid >= 0.4 && A.entschlossen.lid > 0.2 && A.entschlossen.lid < A.muede.lid && A.ueberrascht.lid < 0.05, `neutral und müde unterscheiden sich: neutral hat die Augen offen (Lid ${A.neutral.lid.toFixed(2)}), müde hängt das Lid halb herab (${A.muede.lid.toFixed(2)})`);
  pruefe(G.muede.filter((z) => z[0] === 'ellipse').length >= G.neutral.filter((z) => z[0] === 'ellipse').length + 2, 'müde hat zusätzlich Augenringe (zwei Bögen unter den Augen), neutral nicht');
  const wink = (a) => dreh(G[a])[2];   // rechtes Auge: + = außen tiefer
  pruefe(Math.abs(wink('neutral')) < 1e-6 && wink('muede') > 0.1 && wink('entschlossen') < -0.1 && Math.abs(wink('ueberrascht')) < 1e-6, 'Neigung der Augen: müde außen tiefer, entschlossen innen tiefer, neutral und überrascht gerade');
  const abstandBraue = (a) => { const t = geh(G[a]); return t[0][2] - t[1][2]; };   // Auge minus Braue (größer = Braue höher)
  pruefe(abstandBraue('ueberrascht') > abstandBraue('neutral') && abstandBraue('neutral') > abstandBraue('entschlossen'), 'Brauen: bei überrascht am höchsten, bei neutral darunter, bei entschlossen am tiefsten');
  const braueWinkel = (a) => dreh(G[a])[3];
  pruefe(braueWinkel('entschlossen') < -0.2 && braueWinkel('muede') > 0.1 && Math.abs(braueWinkel('neutral')) < 1e-6, 'Brauen: bei entschlossen steil nach innen unten, bei müde außen tiefer, bei neutral waagerecht');
  const g = G.ueberrascht, letzte = g.map((z) => z[0]).lastIndexOf('ellipse');
  pruefe(g[letzte + 1] && g[letzte + 1][0] === 'stroke' && !g.slice(letzte).some((z) => z[0] === 'fill'), 'überrascht: der Mund ist nur ein Rand (Ring), keine helle gefüllte Fläche — kein Totenkopf');
  pruefe(A.ueberrascht.ry > A.neutral.ry * 1.15 && A.ueberrascht.rx > A.neutral.rx, 'überrascht: die Augen sind größer als bei neutral (aufgerissen), mit Pupille statt hohler weißer Ovale');
});

process.stdout.write(JSON.stringify(erg));
