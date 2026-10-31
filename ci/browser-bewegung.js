#!/usr/bin/env node
/*
 * Browsertests des Bausteins „bewegung“ (3.64.0) gegen das gebaute Musterbuch (musterbuch/bewegung--wiedergabe, musterbuch/bewegung--live).
 * Die Seiten kommen über die Scheinadresse https://lp.test/ aus dem Ordner (https: ohne sicheren Kontext gibt der Browser keine Kamera frei); jede andere Adresse wird abgebrochen und gezählt (Soll: 0).
 * Die Kamera ist die Prüfkamera von Chromium (grünes Muster, keine Person, kein echtes Bild).
 *
 *   ES_PLAYWRIGHT=/home/pawbot/projects/panel/node_modules/playwright [ES_MODELL=<Ordner modell/>] node ci/browser-bewegung.js
 *
 * ES_MODELL: der Ordner modell/ von scripts/modell-holen.sh (Standard: ../endoskeleton-modelle/modell neben dem Kit). Fehlt er, laufen nur
 * die Prüfungen ohne die echte Erkennung — und das wird laut gemeldet. Kein npm install im Endoskeleton (Schadcode-Commit eb8a34d).
 */
'use strict';

const pw = require(process.env.ES_PLAYWRIGHT || '/home/pawbot/projects/panel/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { pruefen } = require('../lp/bewegung.js');

const KIT = path.join(__dirname, '..');
const MUSTERBUCH = path.join(KIT, 'musterbuch');
const MODELL = process.env.ES_MODELL || path.join(KIT, '..', 'endoskeleton-modelle', 'modell');
const SUMMEN = fs.readFileSync(path.join(KIT, 'scripts', 'modell-summen.txt'), 'utf8').split('\n').map((z) => z.split('  ')[1]).filter(Boolean);
const MODELL_DA = SUMMEN.every((n) => fs.existsSync(path.join(MODELL, n)));
const TYPEN = { html: 'text/html', css: 'text/css', js: 'text/javascript', wasm: 'application/wasm', task: 'application/octet-stream', bewegung: 'application/octet-stream', svg: 'image/svg+xml', webp: 'image/webp', png: 'image/png', txt: 'text/plain' };
const PRUEFKAMERA = ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'];

let bestanden = 0;
const fehler = [];
const pruefe = (ok, was) => { if (ok) { bestanden++; } else { fehler.push(was); } };
const warte = (ms) => new Promise((r) => setTimeout(r, ms));

// Spione: jeder Zugriff auf Cookies, Web-Speicher und IndexedDB; jeder Aufruf von getUserMedia (mit Zeit des letzten echten Klicks davor).
const SPION = (kamera) => `(() => {
  window.__zugriffe = []; window.__kamera = []; window.__spuren = []; window.__klick = 0;
  const merke = (w) => window.__zugriffe.push(w);
  for (const m of ['getItem', 'setItem', 'removeItem', 'clear', 'key']) {
    const alt = Storage.prototype[m];
    Storage.prototype[m] = function (...a) { merke('Storage.' + m); return alt.apply(this, a); };
  }
  const c = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
  Object.defineProperty(Document.prototype, 'cookie', { configurable: true, get() { merke('cookie lesen'); return c.get.call(this); }, set(v) { merke('cookie schreiben'); c.set.call(this, v); } });
  const auf = IDBFactory.prototype.open;
  IDBFactory.prototype.open = function (...a) { merke('indexedDB'); return auf.apply(this, a); };
  document.addEventListener('click', (e) => { if (e.isTrusted) { window.__klick = performance.now(); } }, true);
  const md = navigator.mediaDevices;
  ${kamera === 'ohne' ? "Object.defineProperty(Navigator.prototype, 'mediaDevices', { configurable: true, get: () => undefined });" : `
  const echt = md.getUserMedia.bind(md);
  md.getUserMedia = function (cfg) {
    window.__kamera.push({ nach: performance.now(), video: !!cfg.video, audio: !!cfg.audio });
    ${kamera === 'verweigert' ? "return Promise.reject(new DOMException('Permission denied', 'NotAllowedError'));" : 'return echt(cfg).then((s) => { window.__spuren.push(...s.getTracks()); return s; });'}
  };`}
})();`;
const OHNE_WASM = 'delete window.WebAssembly;';
// Fänger für lp-bewegung:aufnahme am document (kommt nur an, wenn das Ereignis aufsteigt); optional einer davor, der wirft
const FAENGER = (wirft = false) => `(() => {
  window.__aufnahmen = [];
  ${wirft ? "document.addEventListener('lp-bewegung:aufnahme', () => { throw new Error('Fänger kaputt'); });" : ''}
  document.addEventListener('lp-bewegung:aufnahme', (e) => window.__aufnahmen.push({ ziel: e.target.tagName.toLowerCase() + '.' + e.target.className, steigt: e.bubbles, text: e.detail && e.detail.text }));
})();`;

// Ein Stand-in für das Erkennungsmodell: liefert bei jedem Kamerabild 33 Punkte. Prüft Aufnehmen, Vorschau und Speichern — nicht die Erkennung.
// art: ok | wirft (die Erkennung wirft, etwa weil der Grafikkontext verloren ging) | nan (Punkte ohne Zahl). window.__erkannt zählt die Aufrufe,
// window.__modellpfade merkt sich, wohin das Live-Skript WASM und Modell schickt (3.64.1: data-lp-modell).
const ATTRAPPE = (art = 'ok') => `export const FilesetResolver = { forVisionTasks: async (p) => { (window.__modellpfade ||= []).push(p); return {}; } };
export const PoseLandmarker = { createFromOptions: async (f, o) => (window.__modellpfade.push(o.baseOptions.modelAssetPath), { detectForVideo: (v, ts) => {
  window.__erkannt = (window.__erkannt || 0) + 1;
  ${art === 'wirft' ? "throw new Error('Grafikkontext verloren');" : ''}
  return { landmarks: [Array.from({ length: 33 }, (_, i) => ({ x: ${art === 'nan' ? 'NaN' : '0.5 + 0.2 * Math.sin(ts / 400 + i / 5)'}, y: 0.1 + i * 0.025, z: 0, visibility: 0.9 }))] };
} }) };
`;
const ATTRAPPEN = ['attrappe', 'wirft', 'nan'];
// Verzögerungen, die den Tab-Wechsel mitten im Start möglich machen: Kamera-Erlaubnis und video.play() brauchen je 0,8 s
const LANGSAM_ERLAUBNIS = `(() => { const md = navigator.mediaDevices, g = md.getUserMedia; md.getUserMedia = function (cfg) { return g.call(md, cfg).then((s) => new Promise((r) => setTimeout(() => r(s), 800))); }; })();`;
const LANGSAM_PLAY = `(() => { const p = HTMLMediaElement.prototype.play; HTMLMediaElement.prototype.play = function (...a) { return p.apply(this, a).then((r) => new Promise((res) => setTimeout(() => res(r), 800))); }; })();`;
const VERSTECKEN = () => { Object.defineProperty(document, 'hidden', { configurable: true, get: () => true }); document.dispatchEvent(new Event('visibilitychange')); };

/** Öffnet eine Seite des Musterbuchs; optionen: kamera (echt|verweigert|ohne), modell (echt|attrappe|wirft|nan|fehlt), verzoegern (ms, bis das Live-Modul antwortet), still, vorher (Skript), datei (Ersatz für die Bewegungsdatei), modellOrdner (zweiter Ordner, aus dem Modelldateien kommen: Ziel von data-lp-modell, relativ zur Seite),
 *  3.64.2: stil (Ersatz für data-lp-stil der Figur; null = Attribut weg), thema (data-theme am html), farben ({ figur, grund }: Farben der Leinwand, nur im Test), fenster ({ width, height }). */
async function oeffnen(browser, name, optionen = {}) {
  const kontext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: optionen.fenster || { width: 1000, height: 1100 }, reducedMotion: optionen.still ? 'reduce' : 'no-preference', permissions: optionen.kamera === 'echt' ? ['camera'] : [] });
  const page = await kontext.newPage();
  const aus = { kontext, page, fremd: [], anfragen: [], seitenfehler: [], konsole: [] };
  page.on('pageerror', (e) => aus.seitenfehler.push(e.message));
  // nur Fehler, keine Warnungen: getImageData im Test selbst löst eine Chromium-Leistungswarnung aus
  page.on('console', (m) => { if (m.type() === 'error') { aus.konsole.push(m.text()); } });
  if (process.env.ES_DEBUG) { page.on('console', (m) => console.log(`  [Konsole ${name}] ${m.text().slice(0, 200)}`)); page.on('requestfailed', (r) => console.log(`  [Anfrage fehlgeschlagen] ${r.url()} ${r.failure()?.errorText}`)); }
  await page.addInitScript(SPION(optionen.kamera || 'echt'));
  if (optionen.vorher) { await page.addInitScript(optionen.vorher); }
  await page.route('**/*', async (r) => {
    const u = new URL(r.request().url());
    if (u.host !== 'lp.test') { aus.fremd.push(u.href); return r.abort(); }
    aus.anfragen.push(u.pathname);
    const rel = decodeURIComponent(u.pathname).replace(/^\/+/, '');
    const unter = rel.slice(name.length + 1);
    const ordner = ['modell/', optionen.modellOrdner].find((o) => o && unter.startsWith(o));
    if (ordner) {
      const datei0 = unter.slice(ordner.length);
      if (optionen.modell === 'fehlt') { return r.fulfill({ status: 404, body: '' }); }
      if (ATTRAPPEN.includes(optionen.modell) && datei0 === 'vision_bundle.js') {
        if (optionen.verzoegern) { await warte(optionen.verzoegern); }
        return r.fulfill({ status: 200, contentType: 'text/javascript', body: ATTRAPPE(optionen.modell === 'attrappe' ? 'ok' : optionen.modell) });
      }
      const datei = path.join(MODELL, datei0);
      return SUMMEN.includes(datei0) && fs.existsSync(datei) ? r.fulfill({ status: 200, contentType: TYPEN[datei.split('.').pop()] || 'application/octet-stream', body: fs.readFileSync(datei) }) : r.fulfill({ status: 404, body: '' });
    }
    if (optionen.datei !== undefined && unter.startsWith('bew/')) {
      return optionen.datei === null ? r.fulfill({ status: 404, body: '' }) : r.fulfill({ status: 200, contentType: 'application/octet-stream', body: optionen.datei });
    }
    const datei = path.join(MUSTERBUCH, rel.endsWith('/') || rel === name ? rel.replace(/\/?$/, '/') + 'index.html' : rel);
    if (!datei.startsWith(MUSTERBUCH) || !fs.existsSync(datei) || fs.statSync(datei).isDirectory()) { return r.fulfill({ status: 404, body: '' }); }
    let inhalt = fs.readFileSync(datei);
    if (datei.endsWith('index.html') && (optionen.stil !== undefined || optionen.thema || optionen.farben)) {
      let h = inhalt.toString('utf8');
      if (optionen.stil !== undefined) { h = h.replace(/ data-lp-stil="[^"]*"/, optionen.stil === null ? '' : ` data-lp-stil="${optionen.stil}"`); }
      if (optionen.thema) { h = h.replace('<html ', `<html data-theme="${optionen.thema}" `); }
      if (optionen.farben) { h = h.replace('</head>', `<style>.lp-bewegung-figur canvas{background:${optionen.farben.grund};color:${optionen.farben.figur}}</style></head>`); }
      inhalt = Buffer.from(h, 'utf8');
    }
    return r.fulfill({ status: 200, contentType: TYPEN[datei.split('.').pop()] || 'application/octet-stream', body: inhalt });
  });
  await page.goto(`https://lp.test/${name}/`);
  await page.waitForLoadState('load');
  await warte(500);
  return aus;
}

/** Zustand der Leinwand: Zahl der Pixel, die vom Grund abweichen, ihr Schwerpunkt in x, die Breite und das Bild als Text. */
const leinwand = (page) => page.evaluate(() => {
  const c = document.querySelector('.lp-bewegung-figur canvas'), d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
  let n = 0, sx = 0;
  for (let i = 0; i < d.length; i += 4) {
    if (Math.abs(d[i] - d[0]) + Math.abs(d[i + 1] - d[1]) + Math.abs(d[i + 2] - d[2]) > 60) { n++; sx += (i / 4) % c.width; }
  }
  return { n, mx: n ? sx / n : 0, breite: c.width, url: c.toDataURL() };
});
/** Wie leinwand(), aber nach Deckkraft: Pixel, die etwas zeigen (alpha > 24), ihr Rahmen, ihr Schwerpunkt und die häufigste deckende Farbe (je 16 Stufen gerundet). */
const masse = (page) => page.evaluate(() => {
  const c = document.querySelector('.lp-bewegung-figur canvas'), d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data, farben = {};
  let n = 0, sx = 0, x0 = 1e9, x1 = -1, y0 = 1e9, y1 = -1;
  for (let i = 0; i < d.length; i += 4) {
    if (d[i + 3] <= 24) { continue; }
    const p = i / 4, x = p % c.width, y = (p - x) / c.width;
    n++; sx += x; x0 = Math.min(x0, x); x1 = Math.max(x1, x); y0 = Math.min(y0, y); y1 = Math.max(y1, y);
    if (d[i + 3] === 255) { const k = [d[i], d[i + 1], d[i + 2]].map((v) => Math.round(v / 16) * 16).join(','); farben[k] = (farben[k] || 0) + 1; }
  }
  const haupt = Object.entries(farben).sort((a, b) => b[1] - a[1])[0];
  const st = getComputedStyle(c);
  return { n, mx: n ? sx / n : 0, breite: c.width, hoehe: c.height, x0, x1, y0, y1, haupt: haupt ? haupt[0].split(',').map(Number) : null, grund: st.backgroundColor, url: c.toDataURL(),
    scrollBreit: document.documentElement.scrollWidth, fensterBreit: window.innerWidth, fig: c.closest('.lp-bewegung-figur').getBoundingClientRect().toJSON(), cv: c.getBoundingClientRect().toJSON() };
});
const lum = (c) => { const t = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * t[0] + 0.7152 * t[1] + 0.0722 * t[2]; };
const kontrast = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
const text = (page, wahl) => page.locator(wahl).first().textContent().then((t) => t.trim());
const sichtbar = (page, wahl) => page.locator(wahl).first().isVisible();
const meldung = (page, was) => sichtbar(page, `[data-lp-t="${was}"]`);
/** Wartet, bis eine der Bedingungen eintritt; liefert deren Namen oder 'zeit'. */
async function bis(page, bedingungen, ms = 60000) {
  const ende = Date.now() + ms;
  while (Date.now() < ende) {
    for (const [name, fn] of Object.entries(bedingungen)) { if (await fn()) { return name; } }
    await warte(200);
  }
  return 'zeit';
}
/** Die Kamera läuft wirklich (nicht erst im Ladehinweis): der Knopf heißt „ausschalten“ und „Aufnehmen“ ist da. */
const kameraAn = async (page) => await text(page, '[data-lp-b="kamera"]') === 'Kamera ausschalten' && await sichtbar(page, '[data-lp-b="aufnehmen"]');
const kameraAus = (page) => page.evaluate(() => window.__spuren.every((s) => s.readyState === 'ended'));
const zugriffe = (page) => page.evaluate(() => ({ z: window.__zugriffe.slice(), s: sessionStorage.length, l: localStorage.length }));
const SEITE = 'https://lp.test/bewegung--live/';
const modellpfade = (page) => page.evaluate(() => (window.__modellpfade || []).slice());
const modellAttribut = (page, wert) => page.evaluate((v) => document.querySelector('.lp-bewegung-figur').setAttribute('data-lp-modell', v), wert);
/** Prüft Aufnahme-Text mit beiden Kit-Prüfern (JS: lp/bewegung.js, PHP: Bewegung::pruefenText); liefert 'ok' oder die Kennung. */
function pruefeText(t) {
  const js = pruefen(JSON.parse(t), Buffer.byteLength(t));
  const ordner = fs.mkdtempSync(path.join(require('os').tmpdir(), 'bewegung-'));
  try {
    fs.writeFileSync(path.join(ordner, 'a.bewegung'), t);
    const php = execFileSync('php8.2', ['-r', 'require "lp/autoload.php"; echo Endo\\Lp\\Bewegung::pruefenText(file_get_contents(getenv("BEW"))) ?? "ok";'], { cwd: KIT, env: { ...process.env, BEW: path.join(ordner, 'a.bewegung') } }).toString();
    return js === null && php === 'ok' ? 'ok' : `js=${js} php=${php}`;
  } finally { fs.rmSync(ordner, { recursive: true, force: true }); }
}

(async () => {
  const browser = await pw.chromium.launch({ args: PRUEFKAMERA });

  // ===== Wiedergabe =====
  {
    const w = await oeffnen(browser, 'bewegung--wiedergabe');
    const { page } = w;
    const a = await leinwand(page);
    pruefe(a.n > 200, `Wiedergabe: die Figur ist gezeichnet (${a.n} Pixel)`);
    pruefe(await sichtbar(page, '[data-lp-b="steuerung"]'), 'Wiedergabe: die Steuerung ist mit JavaScript sichtbar');
    pruefe(await page.locator('[data-lp-b="kamera"]').count() === 0, 'Wiedergabe: kein Kamera-Knopf');
    pruefe(await text(page, '.lp-bewegung-beschriftung') !== '' && (await page.getAttribute('.lp-bewegung-figur canvas', 'aria-labelledby')) !== null, 'Wiedergabe: die Leinwand hat eine Beschriftung (alt)');
    pruefe(!w.anfragen.some((p) => p.includes('bewegung-live') || p.includes('/modell/')), `Wiedergabe: weder Live-Skript noch Modell angefordert (${w.anfragen.join(', ')})`);
    pruefe(w.anfragen.some((p) => p.endsWith('/es/bewegung.js')) && w.anfragen.some((p) => p.endsWith('.bewegung')), 'Wiedergabe: Player und Bewegungsdatei kommen von der Seite');
    pruefe(w.fremd.length === 0, `Wiedergabe: keine Fremdanfrage (${w.fremd.join(', ')})`);
    const z = await zugriffe(page);
    pruefe(z.z.length === 0 && z.s === 0 && z.l === 0, `Wiedergabe: kein Cookie, kein Web-Speicher, keine IndexedDB (${z.z.join(', ')})`);
    const st = await w.kontext.storageState();
    pruefe(st.cookies.length === 0 && st.origins.every((o) => o.localStorage.length === 0), 'Wiedergabe: der Browser hält nach dem Laden weder Cookie noch localStorage');

    // läuft, Pause, weiter
    await warte(300);
    pruefe((await leinwand(page)).url !== a.url, 'Wiedergabe: die Figur bewegt sich');
    await page.click('[data-lp-b="pause"]');
    const p1 = await leinwand(page); await warte(400); const p2 = await leinwand(page);
    pruefe(p1.url === p2.url && await text(page, '[data-lp-b="pause"]') === 'Abspielen', 'Wiedergabe: Pause hält das Bild an, der Knopf heißt „Abspielen“');
    // Spiegeln: der Schwerpunkt wandert auf die andere Seite der Mitte
    await page.click('[data-lp-b="spiegeln"]');
    const sp = await leinwand(page);
    pruefe(await page.getAttribute('[data-lp-b="spiegeln"]', 'aria-pressed') === 'true' && Math.abs(sp.mx - (sp.breite - p2.mx)) < 4, `Wiedergabe: Spiegeln spiegelt das Bild (${p2.mx.toFixed(1)} → ${sp.mx.toFixed(1)} von ${sp.breite})`);
    await page.click('[data-lp-b="spiegeln"]');
    await page.click('[data-lp-b="pause"]');
    await warte(400);
    pruefe((await leinwand(page)).url !== p2.url && await text(page, '[data-lp-b="pause"]') === 'Pause', 'Wiedergabe: „Abspielen“ läuft weiter');
    // Tastatur
    await page.focus('.lp-bewegung-figur canvas');
    await page.keyboard.press('Space');
    const k1 = await leinwand(page); await warte(300);
    pruefe((await leinwand(page)).url === k1.url, 'Wiedergabe/Tastatur: Leertaste hält an');
    await page.keyboard.press('Home'); const h1 = await leinwand(page);
    await page.keyboard.press('ArrowRight'); const h2 = await leinwand(page);
    await page.keyboard.press('ArrowRight'); await page.keyboard.press('ArrowLeft'); const h3 = await leinwand(page);
    await page.keyboard.press('Home'); const h4 = await leinwand(page);
    pruefe(h1.url !== h2.url && h2.url === h3.url && h1.url === h4.url, 'Wiedergabe/Tastatur: Pfeil rechts/links schaltet Bild für Bild, Home springt zum Anfang');
    await page.selectOption('[data-lp-b="tempo"]', '2');
    pruefe(w.seitenfehler.length === 0, `Wiedergabe: keine Fehler im Browser (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Weniger Bewegung: Standbild und „Abspielen“
  {
    const w = await oeffnen(browser, 'bewegung--wiedergabe', { still: true });
    const a = await leinwand(w.page); await warte(500); const b = await leinwand(w.page);
    pruefe(a.n > 200 && a.url === b.url, `Weniger Bewegung: Standbild mit Figur (${a.n} Pixel), nichts bewegt sich`);
    pruefe(await text(w.page, '[data-lp-b="pause"]') === 'Abspielen', 'Weniger Bewegung: der Knopf heißt „Abspielen“');
    await w.page.click('[data-lp-b="pause"]'); await warte(500);
    pruefe((await leinwand(w.page)).url !== b.url && await text(w.page, '[data-lp-b="pause"]') === 'Pause', 'Weniger Bewegung: „Abspielen“ startet die Bewegung erst auf Wunsch');
    await w.kontext.close();
  }

  // Die Bewegungsdatei fehlt, ist kaputt oder verletzt das Format: freundlicher Hinweis, kein Fehler im Browser
  {
    const echt = JSON.parse(fs.readFileSync(path.join(KIT, 'lp', 'musterbuch', 'bew', 'beispiel-winken.bewegung'), 'utf8'));
    const mitGesicht = JSON.parse(JSON.stringify(echt)); mitGesicht.rahmen[0][3 + 2] = 1; mitGesicht.rahmen[0][3] = 0.5; mitGesicht.rahmen[0][4] = 0.5; // Punkt 1 (Auge) sichtbar
    const zuLang = JSON.parse(JSON.stringify(echt)); zuLang.dauer_ms = 20000;
    for (const [was, datei] of [['fehlende Datei', null], ['kein JSON', 'nichts{'], ['sichtbares Gesichtsmerkmal', JSON.stringify(mitGesicht)], ['falsche Dauer', JSON.stringify(zuLang)]]) {
      const w = await oeffnen(browser, 'bewegung--wiedergabe', { datei });
      pruefe(await meldung(w.page, 'fehler'), `Bewegungsdatei (${was}): Hinweis „konnte nicht geladen werden“`);
      pruefe(w.seitenfehler.length === 0 && w.fremd.length === 0, `Bewegungsdatei (${was}): kein Browserfehler, keine Fremdanfrage (${w.seitenfehler.join('; ')})`);
      await w.kontext.close();
    }
  }

  // Zu große Datei: Hinweis, und der Player parst sie gar nicht erst (JSON.parse sieht nie mehr als 300 000 Zeichen)
  {
    const echt = fs.readFileSync(path.join(KIT, 'lp', 'musterbuch', 'bew', 'beispiel-winken.bewegung'), 'utf8');
    const zaehler = `(() => { const p = JSON.parse; JSON.parse = function (t, ...a) { if (typeof t === 'string' && t.length > 300000) { window.__grossGeparst = true; } return p.call(this, t, ...a); }; })();`;
    const w = await oeffnen(browser, 'bewegung--wiedergabe', { datei: echt + ' '.repeat(300001), vorher: zaehler });
    pruefe(await meldung(w.page, 'fehler'), 'Zu große Bewegungsdatei: Hinweis „konnte nicht geladen werden“');
    pruefe(!(await w.page.evaluate(() => window.__grossGeparst)) && w.seitenfehler.length === 0, `Zu große Bewegungsdatei: nicht mit JSON.parse gelesen, kein Browserfehler (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // ===== Darstellungen der Figur (3.64.2): silhouette (Vorgabe), piktogramm, koerper — und die älteren strich, punkte =====
  {
    const SEITEN = { silhouette: 'bewegung--wiedergabe', piktogramm: 'bewegung--wiedergabe--piktogramm', koerper: 'bewegung--wiedergabe--koerper' };
    const rgbVon = (t) => String(t).match(/\d+/g).slice(0, 3).map(Number);
    const stand = {};   // Standbild je Stil (Weniger Bewegung = festes Bild in der Mitte der Bewegung)

    // (3) Pixel in jedem Stil, und die Bilder unterscheiden sich; (5) unbekannter Stil und fehlendes Attribut = Vorgabe; (7) keine Fremdanfrage, keine Fehler
    for (const [stil, name] of Object.entries(SEITEN)) {
      const w = await oeffnen(browser, name, { still: true });
      const m = await masse(w.page);
      stand[stil] = m;
      pruefe(await w.page.getAttribute('.lp-bewegung-figur', 'data-lp-stil') === stil, `Darstellung ${stil}: die Figur trägt data-lp-stil="${stil}"`);
      pruefe(m.n > 1500, `Darstellung ${stil}: Standbild mit Figur (${m.n} Pixel)`);
      pruefe(w.fremd.length === 0 && w.seitenfehler.length === 0 && w.konsole.length === 0, `Darstellung ${stil}: keine Fremdanfrage, keine Konsolen-/Seitenfehler (${w.fremd.join(', ')} ${w.seitenfehler.concat(w.konsole).join('; ')})`);
      const z = await zugriffe(w.page);
      pruefe(z.z.length === 0 && z.s === 0 && z.l === 0, `Darstellung ${stil}: kein Cookie, kein Web-Speicher`);
      await w.kontext.close();
    }
    const paare = await (async () => {
      const w = await oeffnen(browser, 'bewegung--wiedergabe', { still: true });
      const r = await w.page.evaluate(async () => {
        const d = await (await fetch(document.querySelector('[data-lp-bewegung]').getAttribute('data-lp-bewegung'))).json();
        const mal = (stil) => {
          const cv = document.createElement('canvas'); cv.width = 360; cv.height = Math.round(360 / d.verhaeltnis);
          const c = cv.getContext('2d');
          window.LpBewegung.zeichne(c, d, 45, cv.width, cv.height, { stil, farbe: 'rgb(240, 240, 245)', grund: 'rgb(26, 26, 46)' });
          return c.getImageData(0, 0, cv.width, cv.height).data;
        };
        const s = ['silhouette', 'piktogramm', 'koerper'].map(mal), aus = {};
        for (const [a, b] of [[0, 1], [0, 2], [1, 2]]) {
          let n = 0;
          for (let i = 0; i < s[a].length; i += 4) { if (Math.abs(s[a][i] - s[b][i]) + Math.abs(s[a][i + 1] - s[b][i + 1]) + Math.abs(s[a][i + 2] - s[b][i + 2]) + Math.abs(s[a][i + 3] - s[b][i + 3]) > 90) { n++; } }
          aus[a + '-' + b] = n;
        }
        return aus;
      });
      await w.kontext.close();
      return r;
    })();
    pruefe(Object.values(paare).every((n) => n > 1500), `Darstellungen: die drei Bilder unterscheiden sich messbar (abweichende Pixel Silhouette/Piktogramm/Körper: ${JSON.stringify(paare)})`);
    pruefe(new Set(Object.values(stand).map((m) => m.url)).size === 3, 'Darstellungen: auf den Seiten sind die Standbilder der drei Stile verschieden');
    for (const [was, stil] of [['unbekannter Stil „knochen“', 'knochen'], ['leerer Stil', ''], ['Stil ohne Attribut', null], ['Großschreibung „PIKTOGRAMM“', 'PIKTOGRAMM']]) {
      const w = await oeffnen(browser, 'bewegung--wiedergabe', { still: true, stil });
      const m = await masse(w.page);
      pruefe(m.url === stand.silhouette.url, `Darstellung (${was}): fällt auf die Vorgabe silhouette zurück, Bild wie die Vorgabe`);
      pruefe(w.fremd.length === 0 && w.seitenfehler.length === 0 && w.konsole.length === 0 && !(await meldung(w.page, 'fehler')), `Darstellung (${was}): kein Fehler im Browser, kein Hinweis „fehler“ (${w.seitenfehler.concat(w.konsole).join('; ')})`);
      await w.kontext.close();
    }

    // (4) strich und punkte bleiben erreichbar und zeichnen weiter (Befehle gleich wie vorher: tests/bewegung-zeichnen.js)
    for (const stil of ['strich', 'punkte']) {
      const w = await oeffnen(browser, 'bewegung--wiedergabe', { still: true, stil });
      const m = await masse(w.page);
      pruefe(m.n > 200 && m.url !== stand.silhouette.url, `Darstellung ${stil}: Bestandsdarstellung zeichnet (${m.n} Pixel) und ist nicht die Vorgabe`);
      await w.kontext.close();
    }

    // (6) Spiegeln, Pause, Tempo, Standbild in jedem Stil
    for (const stil of ['silhouette', 'piktogramm', 'koerper', 'strich', 'punkte']) {
      const w = await oeffnen(browser, 'bewegung--wiedergabe', { stil });
      const { page } = w;
      await warte(300);
      const a = await masse(page);
      await page.click('[data-lp-b="pause"]');
      const p1 = await masse(page); await warte(300); const p2 = await masse(page);
      pruefe(a.url !== p1.url || a.n > 200, `Bedienung ${stil}: läuft`);
      pruefe(p1.url === p2.url && await text(page, '[data-lp-b="pause"]') === 'Abspielen', `Bedienung ${stil}: Pause hält das Bild an`);
      await page.click('[data-lp-b="spiegeln"]');
      const sp = await masse(page);
      pruefe(Math.abs(sp.mx - (sp.breite - p2.mx)) < 6 && sp.url !== p2.url, `Bedienung ${stil}: Spiegeln spiegelt das Bild (${p2.mx.toFixed(1)} → ${sp.mx.toFixed(1)} von ${sp.breite})`);
      await page.click('[data-lp-b="spiegeln"]');
      await page.selectOption('[data-lp-b="tempo"]', '2');
      await page.click('[data-lp-b="pause"]'); await warte(400);
      pruefe((await masse(page)).url !== p2.url && await text(page, '[data-lp-b="pause"]') === 'Pause', `Bedienung ${stil}: Abspielen und doppeltes Tempo laufen`);
      await page.focus('.lp-bewegung-figur canvas');
      await page.keyboard.press('Home'); const h1 = await masse(page);
      await page.keyboard.press('ArrowRight'); const h2 = await masse(page);
      pruefe(h1.url !== h2.url, `Bedienung ${stil}: Pfeil rechts schaltet ein Bild weiter`);
      pruefe(w.seitenfehler.length === 0 && w.konsole.length === 0 && w.fremd.length === 0, `Bedienung ${stil}: keine Fehler, keine Fremdanfrage (${w.seitenfehler.concat(w.konsole).join('; ')})`);
      await w.kontext.close();
      const s = await oeffnen(browser, 'bewegung--wiedergabe', { still: true, stil });
      const s1 = await masse(s.page); await warte(400); const s2 = await masse(s.page);
      pruefe(s1.n > 200 && s1.url === s2.url && await text(s.page, '[data-lp-b="pause"]') === 'Abspielen', `Weniger Bewegung ${stil}: Standbild mit Figur, nichts bewegt sich, Knopf „Abspielen“`);
      await s.page.click('[data-lp-b="pause"]'); await warte(500);
      pruefe((await masse(s.page)).url !== s1.url, `Weniger Bewegung ${stil}: „Abspielen“ startet die Bewegung erst auf Wunsch`);
      await s.kontext.close();
    }

    // (8) hell und dunkel, 1280 und 390 Punkte breit, zwei Markenfarben: die Figur liegt in der Fläche, nichts läuft über, die Hauptfarbe hat 3 : 1 gegen den Grund
    const VARIANTEN = [
      ['hell', { thema: 'light' }], ['dunkel', { thema: 'dark' }],
      ['Marke Grün/Gelb', { farben: { grund: '#1b5e3a', figur: '#ffd84d' } }], ['Marke Rosa/Hellgrau', { farben: { grund: '#f1e4ea', figur: '#c2185b' } }],
    ];
    for (const stil of ['silhouette', 'piktogramm', 'koerper']) {
      for (const [was, opt] of VARIANTEN) {
        for (const fenster of [{ width: 1280, height: 900 }, { width: 390, height: 800 }]) {
          const w = await oeffnen(browser, SEITEN[stil], { still: true, fenster, ...opt });
          const m = await masse(w.page);
          const lage = `${stil}, ${was}, ${fenster.width} px`;
          pruefe(m.n > 1500 && m.x0 >= 2 && m.y0 >= 2 && m.x1 <= m.breite - 3 && m.y1 <= m.hoehe - 3, `Lage ${lage}: Figur liegt ganz in der Leinwand (Rahmen ${m.x0}–${m.x1} von ${m.breite}, ${m.y0}–${m.y1} von ${m.hoehe})`);
          pruefe(m.scrollBreit <= m.fensterBreit && m.cv.left >= m.fig.left - 1 && m.cv.right <= m.fig.right + 1, `Lage ${lage}: nichts läuft über (Seite ${m.scrollBreit} von ${m.fensterBreit} Punkten, Leinwand ${m.cv.width.toFixed(0)} Punkte breit)`);
          const k = m.haupt ? kontrast(m.haupt, rgbVon(m.grund)) : 0;
          pruefe(k >= 3, `Farbe ${lage}: die Hauptfarbe der Figur hat gegen den Grund ${k.toFixed(1)} : 1 (Soll ≥ 3)`);
          pruefe(w.seitenfehler.length === 0 && w.konsole.length === 0 && w.fremd.length === 0, `Lage ${lage}: keine Fehler, keine Fremdanfrage`);
          await w.kontext.close();
        }
      }
    }
  }

  // ===== Live: Einwilligung zuerst =====
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: MODELL_DA ? 'echt' : 'attrappe' });
    const { page } = w;
    pruefe(await sichtbar(page, '[data-lp-b="kamera"]') && /Gerät/.test(await text(page, '.lp-bewegung-hinweis')), 'Live: Kamera-Knopf und Datenschutzhinweis („bleibt auf deinem Gerät“) stehen vor dem Klick da');
    await warte(1500);
    pruefe((await page.evaluate(() => window.__kamera.length)) === 0, 'Live: vor dem Klick wird die Kamera nicht angefragt');
    pruefe(!w.anfragen.some((p) => p.includes('bewegung-live') || p.includes('/modell/')), `Live: vor dem Klick weder Live-Skript noch Modell angefordert (${w.anfragen.filter((p) => p.includes('modell') || p.includes('live')).join(', ')})`);
    pruefe(await sichtbar(page, '[data-lp-b="steuerung"]') && (await leinwand(page)).n > 200, 'Live: vor dem Klick läuft der Player mit der Beispielbewegung');
    await page.click('[data-lp-b="kamera"]');
    const ergebnis = await bis(page, { an: () => kameraAn(page), hinweis: () => meldung(page, 'keinkamera') }, 90000);
    pruefe(ergebnis === 'an', `Live (Prüfkamera${MODELL_DA ? ', echtes Modell' : ', Attrappe — ES_MODELL fehlt'}): Kamera läuft nach dem Klick (${ergebnis})`);
    const aufrufe = await page.evaluate(() => ({ kamera: window.__kamera.slice(), klick: window.__klick }));
    pruefe(aufrufe.kamera.length === 1 && aufrufe.klick > 0 && aufrufe.kamera[0].nach > aufrufe.klick && aufrufe.kamera[0].video && !aufrufe.kamera[0].audio, `Live: Kamera erst NACH dem echten Klick, einmal, nur Bild (${JSON.stringify(aufrufe)})`);
    if (MODELL_DA) {
      const geholt = w.anfragen.filter((p) => p.includes('/modell/')).map((p) => p.split('/modell/')[1]);
      pruefe(geholt.includes('vision_bundle.js') && geholt.includes('pose_landmarker_lite.task') && geholt.some((n) => n.endsWith('.wasm')), `Live: Skript, Modell und WASM kommen von der eigenen Seite (${geholt.join(', ')})`);
      console.log(`  Info: angeforderte Modelldateien: ${geholt.join(', ')}`);
    }
    await warte(1500);
    const bild = await leinwand(w.page);
    pruefe(bild.n > 200, `Live: die Leinwand zeigt das Kamerabild (${bild.n} abweichende Pixel)`);
    pruefe(await sichtbar(page, '[data-lp-b="aufnehmen"]'), 'Live: „Aufnehmen“ erscheint');
    await page.click('[data-lp-b="aufnehmen"]');
    pruefe(await meldung(page, 'aufnahme') && await text(page, '[data-lp-b="aufnehmen"]') === 'Stopp', 'Live: Aufnahme läuft, Knopf heißt „Stopp“');
    await warte(1500);
    await page.click('[data-lp-b="aufnehmen"]');
    if (MODELL_DA) {
      pruefe(await meldung(page, 'leer'), 'Live (Prüfkamera ohne Person): ehrlicher Hinweis „keine Person erkannt“, nichts zum Speichern');
      pruefe(!(await sichtbar(page, '[data-lp-b="speichern"]')), 'Live (ohne Person): „Als Datei speichern“ bleibt verborgen');
    } else {
      pruefe(await meldung(page, 'fertig') && await sichtbar(page, '[data-lp-b="speichern"]'), 'Live (Attrappe): Aufnahme fertig, „Als Datei speichern“ erscheint');
    }
    pruefe(await page.evaluate(() => window.__spuren.length > 0 && window.__spuren.every((s) => s.readyState === 'ended')), 'Live: nach dem Aufnehmen sind alle Kameraspuren beendet (Kamera aus)');
    pruefe(await text(page, '[data-lp-b="kamera"]') === 'Kamera starten', 'Live: der Kamera-Knopf heißt wieder „Kamera starten“');
    pruefe(w.fremd.length === 0, `Live: keine Fremdanfrage (${w.fremd.join(', ')})`);
    const z = await zugriffe(page);
    pruefe(z.z.length === 0 && z.s === 0 && z.l === 0, `Live: kein Cookie, kein Web-Speicher, keine IndexedDB (${z.z.join(', ')})`);
    const st = await w.kontext.storageState();
    pruefe(st.cookies.length === 0 && st.origins.every((o) => o.localStorage.length === 0), 'Live: der Browser hält am Ende weder Cookie noch localStorage');
    pruefe(w.seitenfehler.length === 0, `Live: keine Fehler im Browser (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Tab wechseln → Kamera aus
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe' });
    await w.page.click('[data-lp-b="kamera"]');
    await bis(w.page, { an: () => kameraAn(w.page) }, 20000);
    await w.page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, get: () => true }); document.dispatchEvent(new Event('visibilitychange')); });
    await warte(300);
    pruefe(await w.page.evaluate(() => window.__spuren.length > 0 && window.__spuren.every((s) => s.readyState === 'ended')) && await text(w.page, '[data-lp-b="kamera"]') === 'Kamera starten', 'Live: beim Wechsel des Tabs geht die Kamera aus');
    await w.kontext.close();
  }

  // Tab wechseln, noch bevor die Kamera läuft (Laden / Kamera-Erlaubnis / Start des Kamerabilds): sie darf im versteckten Tab nie laufen
  for (const [phase, opt, bereit] of [
    ['während des Ladens', { modell: 'attrappe', verzoegern: 1500 }, null],
    ['während der Kamera-Erlaubnis', { modell: 'attrappe', vorher: LANGSAM_ERLAUBNIS }, () => window.__kamera.length === 1],
    ['während das Kamerabild startet', { modell: 'attrappe', vorher: LANGSAM_PLAY }, () => window.__spuren.length === 1],
  ]) {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', ...opt });
    await w.page.click('[data-lp-b="kamera"]');
    if (bereit) { await bis(w.page, { da: () => w.page.evaluate(bereit) }, 20000); await warte(150); }
    await w.page.evaluate(VERSTECKEN);
    await warte(2600);
    const angefragt = await w.page.evaluate(() => window.__kamera.length);
    pruefe(await kameraAus(w.page) && (phase !== 'während des Ladens' || angefragt === 0), `Tab-Wechsel ${phase}: keine Kameraspur läuft weiter${phase === 'während des Ladens' ? ', die Kamera wird gar nicht erst angefragt' : ''} (angefragt: ${angefragt})`);
    pruefe(await text(w.page, '[data-lp-b="kamera"]') === 'Kamera starten' && !(await sichtbar(w.page, '[data-lp-b="aufnehmen"]')) && !(await meldung(w.page, 'laedt')), `Tab-Wechsel ${phase}: Bedienung zurück im Ausgang`);
    pruefe(!(await meldung(w.page, 'keinkamera')) && w.seitenfehler.length === 0, `Tab-Wechsel ${phase}: leiser Abbruch, kein Fehlerhinweis, kein Browserfehler (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Klick während des Ladens = Abbrechen; danach geht ein neuer Start
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe', verzoegern: 1500 });
    const { page } = w;
    await page.click('[data-lp-b="kamera"]');
    await bis(page, { laedt: () => meldung(page, 'laedt') }, 5000);   // das Live-Skript wird beim Klick nachgeladen
    pruefe(await text(page, '[data-lp-b="kamera"]') === 'Kamera ausschalten' && await meldung(page, 'laedt'), 'Laden: der Knopf heißt schon „Kamera ausschalten“, der Ladehinweis steht da');
    await page.click('[data-lp-b="kamera"]');
    await bis(page, { zurueck: async () => await text(page, '[data-lp-b="kamera"]') === 'Kamera starten' }, 5000);   // der Klick geht über import(): erst im nächsten Augenblick
    pruefe(await text(page, '[data-lp-b="kamera"]') === 'Kamera starten' && !(await meldung(page, 'laedt')) && await sichtbar(page, '[data-lp-b="steuerung"]'), 'Laden: ein Klick bricht ab — Knopf wieder „Kamera starten“, Hinweis weg, Beispielbewegung zurück');
    await warte(2200);
    pruefe((await page.evaluate(() => window.__kamera.length)) === 0 && await kameraAus(page), 'Laden: nach dem Abbruch wird die Kamera nie angefragt');
    await page.click('[data-lp-b="kamera"]');
    const e = await bis(page, { an: () => kameraAn(page) }, 20000);
    pruefe(e === 'an' && (await page.evaluate(() => window.__kamera.length)) === 1, `Laden: nach dem Abbruch startet ein neuer Klick die Kamera (${e})`);
    pruefe(w.seitenfehler.length === 0, `Laden/Abbrechen: kein Browserfehler (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Die Erkennung wirft: Kamera aus, freundlicher Hinweis, keine Fehlerschleife
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'wirft' });
    await w.page.click('[data-lp-b="kamera"]');
    const e = await bis(w.page, { hinweis: () => meldung(w.page, 'keinkamera') }, 20000);
    await warte(800);
    pruefe(e === 'hinweis' && await kameraAus(w.page) && await text(w.page, '[data-lp-b="kamera"]') === 'Kamera starten', `Erkennung wirft: Hinweis, Kamera aus, Knopf im Ausgang (${e})`);
    pruefe((await w.page.evaluate(() => window.__erkannt)) === 1, 'Erkennung wirft: keine Fehlerschleife — genau ein Aufruf der Erkennung');
    const a = await leinwand(w.page); await warte(400);
    pruefe(a.n > 200 && (await leinwand(w.page)).url !== a.url && w.seitenfehler.length === 0, `Erkennung wirft: die Beispielbewegung läuft wieder, kein Browserfehler (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Punkte ohne Zahl (NaN) beim Beenden der Aufnahme: Kamera aus, freundlicher Hinweis statt stiller Ausnahme
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'nan' });
    await w.page.click('[data-lp-b="kamera"]');
    await bis(w.page, { an: () => kameraAn(w.page) }, 20000);
    await warte(400);
    await w.page.click('[data-lp-b="aufnehmen"]');
    await warte(1200);
    await w.page.click('[data-lp-b="aufnehmen"]');
    const e = await bis(w.page, { hinweis: () => meldung(w.page, 'keinkamera') }, 5000);
    pruefe(e === 'hinweis' && await kameraAus(w.page) && await text(w.page, '[data-lp-b="kamera"]') === 'Kamera starten' && !(await sichtbar(w.page, '[data-lp-b="speichern"]')), `Aufnahme mit NaN: Hinweis, Kamera aus, nichts zum Speichern (${e})`);
    pruefe(w.seitenfehler.length === 0, `Aufnahme mit NaN: kein Browserfehler (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Tastatur auf der Leinwand: wirkt nicht, solange die Kamera läuft (sonst zeichnet der Beispiel-Player in dieselbe Leinwand)
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe' });
    const { page } = w;
    await page.click('[data-lp-b="kamera"]');
    await bis(page, { an: () => kameraAn(page) }, 20000);
    const vorher = await text(page, '[data-lp-b="pause"]');
    await page.focus('.lp-bewegung-figur canvas');
    const unveraendert = [];   // nach jeder Taste einzeln: Leertaste und Enter würden sich sonst gegenseitig aufheben
    for (const t of ['Space', 'Enter', 'ArrowRight', 'Home']) { await page.keyboard.press(t); unveraendert.push(await text(page, '[data-lp-b="pause"]') === vorher); }
    pruefe(unveraendert.every(Boolean), `Kamera läuft: Leertaste, Enter, Pfeil und Home auf der Leinwand tun nichts (Knopf bleibt „${vorher}“; Taste für Taste: ${unveraendert})`);
    await page.click('[data-lp-b="kamera"]');
    await warte(300);
    const nachher = await text(page, '[data-lp-b="pause"]');
    await page.focus('.lp-bewegung-figur canvas');
    await page.keyboard.press('Space');
    pruefe(await text(page, '[data-lp-b="pause"]') !== nachher, 'Kamera aus: die Leertaste steuert den Beispiel-Player wieder');
    await w.kontext.close();
  }

  // Attrappe: Aufnehmen → Vorschau → Speichern; die Datei besteht beide Prüfer
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe' });
    const { page } = w;
    await page.click('[data-lp-b="kamera"]');
    await bis(page, { an: () => kameraAn(page) }, 20000);
    await warte(500);
    await page.click('[data-lp-b="aufnehmen"]');
    await warte(2200);
    await page.click('[data-lp-b="aufnehmen"]');
    pruefe(await meldung(page, 'fertig') && await sichtbar(page, '[data-lp-b="speichern"]'), 'Aufnahme (Attrappe): fertig, Speichern-Knopf da');
    const v1 = await leinwand(page); await warte(300);
    pruefe((await leinwand(page)).url !== v1.url, 'Aufnahme (Attrappe): die Vorschau spielt im Player');
    const [dl] = await Promise.all([page.waitForEvent('download'), page.click('[data-lp-b="speichern"]')]);
    const datei = await dl.path();
    const inhalt = fs.readFileSync(datei, 'utf8');
    const d = JSON.parse(inhalt);
    pruefe(/^aufnahme-\d{8}-\d{4}\.bewegung$/.test(dl.suggestedFilename()), `Aufnahme: Dateiname aufnahme-JJJJMMTT-HHMM.bewegung (${dl.suggestedFilename()})`);
    pruefe(pruefen(d, Buffer.byteLength(inhalt)) === null, `Aufnahme: die Datei besteht den JS-Prüfer (${pruefen(d, Buffer.byteLength(inhalt))})`);
    const php = execFileSync('php8.2', ['-r', 'require "lp/autoload.php"; $t = file_get_contents(getenv("BEW")); echo Endo\\Lp\\Bewegung::pruefen(json_decode($t, false, 8, JSON_THROW_ON_ERROR), strlen($t)) ?? "ok";'], { cwd: KIT, env: { ...process.env, BEW: datei } }).toString();
    pruefe(php === 'ok', `Aufnahme: die Datei besteht den PHP-Prüfer (${php})`);
    pruefe(d.quelle === 'eigene-aufnahme' && d.rahmen.length >= 40 && d.rahmen.length <= 300 && inhalt.length < 300000, `Aufnahme: eigene-aufnahme, ${d.rahmen.length} Bilder, ${inhalt.length} Byte`);
    const gesicht = [1, 2, 3, 4, 5, 6, 9, 10];
    pruefe(d.rahmen.every((r) => gesicht.every((i) => r[i * 3] === 0 && r[i * 3 + 1] === 0 && r[i * 3 + 2] === 0)), 'Aufnahme: Augen und Mund sind in jedem Bild 0,0,0');
    pruefe(!/data:image|base64/.test(inhalt), 'Aufnahme: kein Bild in der Datei');
    pruefe(w.fremd.length === 0 && w.seitenfehler.length === 0, `Aufnahme: keine Fremdanfrage, kein Browserfehler (${w.fremd.join(', ')} ${w.seitenfehler.join('; ')})`);
    const zs = await zugriffe(page);
    pruefe(zs.z.length === 0 && zs.s === 0 && zs.l === 0, `Aufnahme: auch nach „Als Datei speichern“ kein Cookie, kein Web-Speicher, keine IndexedDB (${zs.z.join(', ')})`);
    await w.kontext.close();
  }

  // Ereignis lp-bewegung:aufnahme (3.64.1): am Ende der Aufnahme, am <figure>, steigt auf, trägt den Text des Downloads; der Download bleibt
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe', vorher: FAENGER() });
    const { page } = w;
    await page.click('[data-lp-b="kamera"]');
    await bis(page, { an: () => kameraAn(page) }, 20000);
    pruefe((await page.evaluate(() => window.__aufnahmen.length)) === 0, 'Ereignis: vor und während der Aufnahme kommt keins');
    await warte(500);
    await page.click('[data-lp-b="aufnehmen"]');
    await warte(1500);
    pruefe((await page.evaluate(() => window.__aufnahmen.length)) === 0, 'Ereignis: mitten in der Aufnahme kommt keins');
    await page.click('[data-lp-b="aufnehmen"]');
    const e = await bis(page, { da: () => page.evaluate(() => window.__aufnahmen.length > 0) }, 10000);
    await warte(600);
    const ev = await page.evaluate(() => window.__aufnahmen.slice());
    pruefe(e === 'da' && ev.length === 1, `Ereignis: nach „Stopp“ genau einmal (${e}, ${ev.length})`);
    pruefe(ev[0] && ev[0].ziel === 'figure.lp-bewegung-figur' && ev[0].steigt === true, `Ereignis: am <figure>, steigt zum document auf (${ev[0] && ev[0].ziel})`);
    pruefe(ev[0] && typeof ev[0].text === 'string' && pruefeText(ev[0].text) === 'ok', `Ereignis: der Text besteht JS- und PHP-Prüfer (${ev[0] && typeof ev[0].text === 'string' ? pruefeText(ev[0].text) : 'kein Text'})`);
    pruefe(await meldung(page, 'fertig') && await sichtbar(page, '[data-lp-b="speichern"]') && await kameraAus(page), 'Ereignis: die Seite verhält sich wie bisher (fertig, „Als Datei speichern“ da, Kamera aus)');
    const [dl] = await Promise.all([page.waitForEvent('download'), page.click('[data-lp-b="speichern"]')]);
    pruefe(ev[0] && fs.readFileSync(await dl.path(), 'utf8') === ev[0].text, 'Ereignis: der Text ist genau der, den „Als Datei speichern“ enthält');
    pruefe(JSON.stringify(await modellpfade(page)) === JSON.stringify([SEITE + 'modell/wasm', SEITE + 'modell/pose_landmarker_lite.task']), `Modell ohne data-lp-modell: weiter ../modell/ neben dem Skript (${JSON.stringify(await modellpfade(page))})`);
    pruefe(w.fremd.length === 0 && w.seitenfehler.length === 0, `Ereignis: keine Fremdanfrage, kein Browserfehler (${w.fremd.join(', ')} ${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Ein Fänger, der wirft, stört weder den zweiten Fänger noch die Seite
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe', vorher: FAENGER(true) });
    const { page } = w;
    await page.click('[data-lp-b="kamera"]');
    await bis(page, { an: () => kameraAn(page) }, 20000);
    await warte(400);
    await page.click('[data-lp-b="aufnehmen"]');
    await warte(1500);
    await page.click('[data-lp-b="aufnehmen"]');
    const e = await bis(page, { da: () => page.evaluate(() => window.__aufnahmen.length > 0) }, 10000);
    await warte(300);
    pruefe(e === 'da' && await meldung(page, 'fertig') && await sichtbar(page, '[data-lp-b="speichern"]') && await kameraAus(page), `Werfender Fänger: der zweite Fänger bekommt das Ereignis, die Seite zeigt „fertig“ und den Speichern-Knopf (${e})`);
    pruefe(w.seitenfehler.length === 1 && w.seitenfehler[0].includes('Fänger kaputt'), `Werfender Fänger: nur sein eigener Fehler (${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Höchstdauer: ohne „Stopp“ endet die Aufnahme nach 300 Bildern, und auch dann kommt das Ereignis
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe', vorher: FAENGER() });
    const { page } = w;
    await page.click('[data-lp-b="kamera"]');
    await bis(page, { an: () => kameraAn(page) }, 20000);
    await warte(400);
    await page.click('[data-lp-b="aufnehmen"]');
    const e = await bis(page, { da: () => page.evaluate(() => window.__aufnahmen.length > 0) }, 20000);
    await warte(400);
    const ev = await page.evaluate(() => window.__aufnahmen.slice());
    pruefe(e === 'da' && ev.length === 1 && pruefeText(ev[0].text) === 'ok' && JSON.parse(ev[0].text).rahmen.length === 300, `Höchstdauer: ohne Stopp genau ein Ereignis mit 300 Bildern und gültigem Text (${e}, ${ev.length})`);
    pruefe(await meldung(page, 'fertig') && await kameraAus(page) && await text(page, '[data-lp-b="kamera"]') === 'Kamera starten', 'Höchstdauer: Kamera aus, „fertig“ gezeigt');
    await w.kontext.close();
  }

  // Keine Person erkannt / leere Aufnahme: kein Ereignis (es gibt nichts zu übergeben) — nur mit echtem Modell prüfbar, sonst über die Attrappe 'nan'
  {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'nan', vorher: FAENGER() });
    await w.page.click('[data-lp-b="kamera"]');
    await bis(w.page, { an: () => kameraAn(w.page) }, 20000);
    await warte(400);
    await w.page.click('[data-lp-b="aufnehmen"]');
    await warte(1200);
    await w.page.click('[data-lp-b="aufnehmen"]');
    await bis(w.page, { hinweis: () => meldung(w.page, 'keinkamera') }, 5000);
    await warte(300);
    pruefe((await w.page.evaluate(() => window.__aufnahmen.length)) === 0, 'Ungültige Aufnahme (Punkte ohne Zahl): kein Ereignis');
    await w.kontext.close();
  }

  // data-lp-modell (3.64.1): Modellordner der Seite; relativ zur Seite oder absolut, gleiche Herkunft
  for (const [was, wert, erwartet] of [
    ['relativ zur Seite', 'eigen/modell/', SEITE + 'eigen/modell/'],
    ['absoluter Pfad ohne Schrägstrich am Ende', '/bewegung--live/eigen/modell', SEITE + 'eigen/modell/'],
    ['mit ../ (Standardordner über den Umweg)', 'eigen/../modell/', SEITE + 'modell/'],
  ]) {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe', modellOrdner: 'eigen/modell/' });
    await modellAttribut(w.page, wert);
    await w.page.click('[data-lp-b="kamera"]');
    const e = await bis(w.page, { an: () => kameraAn(w.page), hinweis: () => meldung(w.page, 'keinkamera') }, 20000);
    const pfade = await modellpfade(w.page);
    pruefe(e === 'an' && JSON.stringify(pfade) === JSON.stringify([erwartet + 'wasm', erwartet + 'pose_landmarker_lite.task']), `data-lp-modell (${was}): Skript, WASM und Modell kommen aus ${erwartet} (${e}, ${pfade.join(' ')})`);
    pruefe(w.fremd.length === 0 && w.seitenfehler.length === 0, `data-lp-modell (${was}): keine Fremdanfrage, kein Browserfehler (${w.fremd.join(', ')} ${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }
  // unsichere Werte werden verworfen: der Standard gilt, nichts geht an einen fremden Host (auch nicht ein gleicher Pfad mit Anfrageteil)
  for (const wert of ['javascript:alert(1)', 'data:text/javascript,export const FilesetResolver = {}', 'https://fremd.test/modell/', '//fremd.test/modell/', '/\\fremd.test/modell/', 'eigen/modell/?x=1', 'eigen/modell/#a', 'eigen/%2e%2e/modell/', 'eigen/mo dell/']) {
    const w = await oeffnen(browser, 'bewegung--live', { kamera: 'echt', modell: 'attrappe', modellOrdner: 'eigen/modell/' });
    await modellAttribut(w.page, wert);
    await w.page.click('[data-lp-b="kamera"]');
    const e = await bis(w.page, { an: () => kameraAn(w.page), hinweis: () => meldung(w.page, 'keinkamera') }, 20000);
    const pfade = await modellpfade(w.page);
    pruefe(e === 'an' && JSON.stringify(pfade) === JSON.stringify([SEITE + 'modell/wasm', SEITE + 'modell/pose_landmarker_lite.task']), `data-lp-modell="${wert}": verworfen, der Standard ../modell/ gilt (${e}, ${pfade.join(' ')})`);
    pruefe(w.fremd.length === 0 && w.seitenfehler.length === 0, `data-lp-modell="${wert}": keine Fremdanfrage, kein Browserfehler (${w.fremd.join(', ')} ${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }

  // Ohne Kamera, ohne Erlaubnis, ohne Modell, ohne WASM: Hinweis, die Beispielbewegung läuft weiter
  for (const [was, opt, kameraAngefragt] of [
    ['Erlaubnis verweigert', { kamera: 'verweigert', modell: 'attrappe' }, 1],
    ['kein Kameragerät (mediaDevices fehlt)', { kamera: 'ohne', modell: 'attrappe' }, 0],
    ['Modelldateien fehlen', { kamera: 'echt', modell: 'fehlt' }, 0],
    ...(MODELL_DA ? [['WebAssembly nicht vorhanden', { kamera: 'echt', modell: 'echt', vorher: OHNE_WASM }, 0]] : []),
  ]) {
    const w = await oeffnen(browser, 'bewegung--live', opt);
    await w.page.click('[data-lp-b="kamera"]');
    const e = await bis(w.page, { hinweis: () => meldung(w.page, 'keinkamera') }, 30000);
    pruefe(e === 'hinweis', `Ohne Kamera (${was}): freundlicher Hinweis (${e})`);
    const a = await leinwand(w.page); await warte(400); const b = await leinwand(w.page);
    pruefe(a.n > 200 && a.url !== b.url, `Ohne Kamera (${was}): die Beispielbewegung läuft weiter`);
    pruefe(await text(w.page, '[data-lp-b="kamera"]') === 'Kamera starten' && !(await sichtbar(w.page, '[data-lp-b="aufnehmen"]')), `Ohne Kamera (${was}): Knöpfe wieder im Ausgang`);
    const n = await w.page.evaluate(() => window.__kamera.length);
    pruefe(n === kameraAngefragt, `Ohne Kamera (${was}): Kamera ${kameraAngefragt}-mal angefragt (${n}) — ohne Modell wird sie gar nicht erst angefragt`);
    pruefe(w.fremd.length === 0 && w.seitenfehler.length === 0, `Ohne Kamera (${was}): keine Fremdanfrage, kein Browserfehler (${w.fremd.join(', ')} ${w.seitenfehler.join('; ')})`);
    await w.kontext.close();
  }
  if (!MODELL_DA) { console.log(`  Hinweis: ${MODELL} unvollständig — die echte Erkennung wurde NICHT geprüft (scripts/modell-holen.sh).`); }

  await browser.close();
  console.log('');
  if (fehler.length) {
    console.log(`FEHLER — ${fehler.length} Problem(e), ${bestanden} Prüfung(en) bestanden\n`);
    for (const f of fehler) { console.log(`  ✗ ${f}`); }
    console.log('');
    process.exit(1);
  }
  console.log(`ok — ${bestanden} Browserprüfungen (Bewegung) bestanden\n`);
})().catch((e) => {
  // Ein Abbruch (z. B. ein Knopf, der nie erscheint) zählt als Fehler und nennt zuerst die Prüfungen, die vorher schon rot waren.
  for (const f of fehler) { console.log(`  ✗ ${f}`); }
  const stelle = String((e && e.stack) || '').split('\n').find((z) => z.includes('browser-bewegung.js')) || '';
  console.log(`  ✗ Abbruch des Tests: ${String((e && e.message) || e).split('\n')[0]} ${stelle.trim()}`);
  process.exit(1);
});
