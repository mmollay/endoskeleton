#!/usr/bin/env node
/*
 * Browsertest des Besucherzählers statistik.js (Webseiten, seit 3.63.0; Vertrag v2 Teil 1 — mehrfaches Verlassen und „da“ — seit 3.65.0) in echtem Chromium.
 * Die Seiten kommen über Scheinadressen (http://beispiel.test/ u. a.) von einer Attrappe, die statistik.js aus dem Kit ausliefert; die Nachricht an
 * https://panel.ssi.at/lp/treffer fängt der Test ab und beantwortet sie selbst mit 204 — es geht NIE etwas hinaus. Jede andere Anfrage wird abgebrochen
 * und als fremd gemeldet. Die Logik im Einzelnen (Quellen-Muster, Grenzen, Fehlerfälle) prüft tests/statistik.test.php; hier geht es um das, was nur ein
 * Browser zeigt: die Zeile mit defer und data-quelle, die Hostprüfung am echten location, sendBeacon als einfache Anfrage, Cookies und Speicher, die Seite
 * bleibt unverändert, kein Fehler in der Konsole.
 *
 *   ES_PLAYWRIGHT=/home/pawbot/projects/panel/node_modules/playwright node ci/browser-statistik.js
 *
 * Kein npm install im Endoskeleton (Schadcode-Commit eb8a34d) — Playwright kommt von außen.
 */
'use strict';

const pw = require(process.env.ES_PLAYWRIGHT || '/home/pawbot/projects/panel/node_modules/playwright');
const fs = require('fs');
const path = require('path');

const SKRIPT = path.join(__dirname, '..', 'statistik.js');
const PANEL = 'https://panel.ssi.at/lp/treffer';
const problem = [];
let pruefungen = 0;
const ok = (bedingung, was) => { pruefungen++; if (!bedingung) { problem.push(was); } };

// Spione: zählen jeden Zugriff auf Cookies, Web-Speicher und IndexedDB; Nicht verfolgen; Global Privacy Control; Seite zunächst unsichtbar; werfender sendBeacon.
const SPION = `(() => {
  window.__zugriffe = [];
  const merke = (w) => window.__zugriffe.push(w);
  for (const m of ['getItem', 'setItem', 'removeItem', 'clear', 'key']) {
    const alt = Storage.prototype[m];
    Storage.prototype[m] = function (...a) { merke('Storage.' + m); return alt.apply(this, a); };
  }
  const c = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
  Object.defineProperty(Document.prototype, 'cookie', { configurable: true, get() { merke('cookie lesen'); return c.get.call(this); }, set(v) { merke('cookie schreiben'); c.set.call(this, v); } });
  const auf = IDBFactory.prototype.open;
  IDBFactory.prototype.open = function (...a) { merke('indexedDB'); return auf.apply(this, a); };
})();`;
const DNT = `Object.defineProperty(Navigator.prototype, 'doNotTrack', { configurable: true, get: () => '1' });`;
const GPC = `Object.defineProperty(Navigator.prototype, 'globalPrivacyControl', { configurable: true, get: () => true });`;
const BEACON_WIRFT = `Navigator.prototype.sendBeacon = function () { throw new Error('Beacon kaputt'); };`;
const VERWEISER = (url) => `Object.defineProperty(Document.prototype, 'referrer', { configurable: true, get: () => ${JSON.stringify(url)} });`;
const UNSICHTBAR = `(() => {
  let sicht = 'hidden';
  Object.defineProperty(Document.prototype, 'visibilityState', { configurable: true, get: () => sicht });
  window.__zeigen = () => { sicht = 'visible'; document.dispatchEvent(new Event('visibilitychange')); };
  window.__verstecken = () => { sicht = 'hidden'; document.dispatchEvent(new Event('visibilitychange')); };
})();`;

/** Die Testseite: lang genug zum Scrollen (4000 px), die Einbindungszeile vor </body> genau wie in der Anleitung. */
const seite = (o) => `<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Testseite</title>
<style>main { height: 4000px; }</style></head>
<body${o.body ? ' ' + o.body : ''}><main><h1>Willkommen</h1><p>Text der Seite.</p></main>
${o.ohneZeile ? '' : `<script src="${o.skriptPfad || 'statistik.js'}" data-quelle="${o.quelle || 'web:beispiel.test'}" defer></script>`}
</body></html>`;

/**
 * Öffnet die Seite unter o.host (Standard beispiel.test). Gesammelt wird: beacons (Methode, Inhaltstyp, Text, Adresse), fremd (jede andere Anfrage),
 * konsole (Fehlermeldungen), fehler (unbehandelte Fehler der Seite). o.init: Skripte vor der Seite, o.uhr: gefälschte Uhr, o.suche: Abfrage der Adresse.
 */
async function laden(browser, o = {}) {
  const kontext = await browser.newContext({ viewport: { width: 390, height: 600 }, javaScriptEnabled: o.ohneJs !== true });
  const page = await kontext.newPage();
  const stand = { beacons: [], angefragt: [], fremd: [], konsole: [], fehler: [] };
  const host = o.host || 'beispiel.test';
  for (const skript of o.init || []) { await kontext.addInitScript(skript); }
  page.on('console', (m) => { if (m.type() === 'error') { stand.konsole.push(m.text()); } });
  page.on('pageerror', (e) => stand.fehler.push(String(e)));
  kontext.on('request', (r) => { if (r.url() === PANEL) { stand.angefragt.push(JSON.parse(r.postData() || '{}').art || '(abgebrochen)'); } });   // auch Anfragen, die beim Entladen der Seite abgebrochen werden
  await kontext.route('**/*', (r) => {
    const u = new URL(r.request().url());
    if (u.origin + u.pathname === PANEL) {
      stand.beacons.push({ adresse: u.href, methode: r.request().method(), typ: r.request().headers()['content-type'] || '', text: r.request().postData() || '' });
      return r.fulfill({ status: 204 });
    }
    if (u.host !== host) { stand.fremd.push(u.href); return r.abort(); }
    if (u.pathname.endsWith('/statistik.js')) { return o.skriptAbbruch ? r.abort() : r.fulfill({ status: 200, contentType: 'text/javascript', body: fs.readFileSync(SKRIPT) }); }
    if (u.pathname === '/seite/' || u.pathname === '/andere/') { return r.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: seite(o) }); }
    return r.fulfill({ status: 404, body: '' });
  });
  if (o.uhr) { await page.clock.install({ time: new Date(o.uhr) }); }
  await page.goto(`http://${host}/seite/${o.suche || ''}`);
  return { page, kontext, stand };
}

const warten = (page) => page.waitForTimeout(300);
const gehen = (page) => page.evaluate(() => { window.dispatchEvent(new PageTransitionEvent('pagehide', { persisted: false })); });
const nachrichten = (stand) => stand.beacons.map((b) => JSON.parse(b.text));
const arten = (stand) => nachrichten(stand).map((m) => m.art).join();

async function haupt() {
  const browser = await pw.chromium.launch();

  // 1. Auf der Domain selbst: aufruf beim Laden, verlassen mit Zeit und Tiefe, Felder, einfache Anfrage, kein Speicher, Seite unverändert.
  {
    const { page, kontext, stand } = await laden(browser, { init: [SPION, VERWEISER('https://www.Verweis.test/p/abc?x=1')], uhr: '2026-10-30T10:00:00', suche: '?utm_source=mail&utm_medium=newsletter&utm_campaign=herbst' });
    await warten(page);
    ok(arten(stand) === 'aufruf', `Domain: beim Laden genau ein aufruf (${arten(stand)})`);
    const a = nachrichten(stand)[0] || {};
    ok(Object.keys(a).sort().join() === 'art,breite,liste,pfad,ref,utm,v', `Domain: genau die Felder des Vertrags (${Object.keys(a).sort()})`);
    ok(a.v === 2 && a.liste === 'web:beispiel.test' && a.pfad === '/seite/' && a.ref === 'www.verweis.test' && a.breite === 390, `Domain: v, liste, pfad, ref (nur der Host), breite (${JSON.stringify(a)})`);
    ok(a.utm && a.utm.quelle === 'mail' && a.utm.medium === 'newsletter' && a.utm.kampagne === 'herbst', 'Domain: UTM aus der Adresse');
    ok((stand.beacons[0] || {}).adresse === PANEL && (stand.beacons[0] || {}).methode === 'POST' && ((stand.beacons[0] || {}).typ || '').startsWith('text/plain'), `Domain: POST als text/plain an das Panel (${(stand.beacons[0] || {}).typ})`);
    await page.evaluate(() => window.scrollTo(0, 1800));
    await page.clock.fastForward(12000);
    await gehen(page);
    await gehen(page);
    await warten(page);
    ok(arten(stand) === 'aufruf,verlassen', `Domain: pagehide zweimal → genau ein verlassen (${arten(stand)})`);
    const v = nachrichten(stand)[1] || {};
    ok(Object.keys(v).sort().join() === 'art,breite,dauer,liste,pfad,ref,tiefe,utm,v', `Domain: verlassen trägt dauer und tiefe (${Object.keys(v).sort()})`);
    ok(v.dauer >= 12 && v.dauer <= 13, `Domain: 12 Sekunden sichtbar → dauer 12 (${v.dauer})`);
    ok(v.tiefe === 50, `Domain: Fensterrand bei 2400 von 4000 px → tiefe 50 (${v.tiefe})`);
    ok(stand.beacons.every((b) => b.text.length <= 2048), 'Domain: Nachrichten höchstens 2 KB');
    const spur = await page.evaluate(() => ({ zugriffe: window.__zugriffe.slice(), cookie: document.cookie }));
    ok(spur.zugriffe.length === 0 && spur.cookie === '' && (await kontext.cookies()).length === 0, `Domain: keine Cookies, kein Web-Speicher, kein IndexedDB (${spur.zugriffe})`);
    await page.evaluate(() => { sessionStorage.getItem('probe'); });
    ok((await page.evaluate(() => window.__zugriffe.length)) > 0, 'Domain: der Speicher-Spion schlägt an (Gegenprobe der Messung)');
    ok(stand.fremd.length === 0, `Domain: keine Fremdanfrage (${stand.fremd.join(', ')})`);
    ok(stand.konsole.length === 0 && stand.fehler.length === 0, `Domain: kein Fehler in der Konsole (${stand.konsole.concat(stand.fehler).join(' | ')})`);
    await kontext.close();
  }

  // 2. Die Seite bleibt unverändert: dieselben Elemente, keine neuen Attribute und Stile, gleiche Maße — mit und ohne Einbindungszeile.
  {
    const maß = (page) => page.evaluate(() => ({
      elemente: [...document.querySelectorAll('body *')].filter((e) => e.tagName !== 'SCRIPT').map((e) => e.outerHTML).join('|'),
      hoehe: document.documentElement.scrollHeight, stile: document.querySelectorAll('[style]').length, textfarbe: getComputedStyle(document.body).color,
    }));
    const mit = await laden(browser, {});
    const ohne = await laden(browser, { ohneZeile: true });
    await warten(mit.page);
    ok(JSON.stringify(await maß(mit.page)) === JSON.stringify(await maß(ohne.page)), 'Seite: mit und ohne Zähler dieselben Elemente, Höhe und Farbe, kein style-Attribut');
    ok(nachrichten(ohne.stand).length === 0, 'Seite: ohne Einbindungszeile wird nichts gesendet');
    await mit.kontext.close(); await ohne.kontext.close();
  }

  // 3. Hostprüfung am echten location: www.<Domain> zählt; Vorschau, andere Domains, Adressen mit Port und mit der Domain als Unterdomain zählen nie.
  for (const [host, erwartet] of [['www.beispiel.test', 'aufruf'], ['develop.beispiel.test', ''], ['vorschau.beispiel.test', ''], ['beispiel.example', ''], ['beispiel.test.example', ''], ['127.0.0.1', '']]) {
    const { page, kontext, stand } = await laden(browser, { host });
    await warten(page);
    await gehen(page);
    await warten(page);
    ok(arten(stand) === (erwartet ? 'aufruf,verlassen' : ''), `Host ${host}: ${erwartet ? 'zählt (aufruf, verlassen)' : 'sendet nichts'} (${arten(stand)})`);
    ok(stand.fremd.length === 0 && stand.fehler.length === 0, `Host ${host}: keine Fremdanfrage, kein Fehler (${stand.fremd.concat(stand.fehler).join(', ')})`);
    await kontext.close();
  }
  {
    const { page, kontext, stand } = await laden(browser, { host: 'develop.beispiel.test', quelle: 'web:beispiel.test' });
    await warten(page);
    ok(stand.beacons.length === 0, 'Host develop.beispiel.test zählt auch dann nicht, wenn die Vorschau die Zeile der Domain trägt');
    await kontext.close();
  }

  // 4. Abschalter: Nicht verfolgen, Global Privacy Control, data-statistik="aus" — auch beim Verlassen nichts; "an" schaltet nichts ab.
  for (const [was, o] of [['Do Not Track', { init: [DNT] }], ['Global Privacy Control', { init: [GPC] }], ['data-statistik="aus"', { body: 'data-statistik="aus"' }]]) {
    const { page, kontext, stand } = await laden(browser, o);
    await warten(page);
    await gehen(page);
    await warten(page);
    ok(stand.beacons.length === 0 && stand.fremd.length === 0, `${was}: kein Beacon, auch kein verlassen (${stand.beacons.length})`);
    await kontext.close();
  }
  {
    const { page, kontext, stand } = await laden(browser, { body: 'data-statistik="an"' });
    await warten(page);
    ok(arten(stand) === 'aufruf', `data-statistik="an" schaltet nichts ab (${arten(stand)})`);
    await kontext.close();
  }

  // 5. Unsichtbar geladen: erst beim Sichtbarwerden ein aufruf (genau einer); eigene Domain als Verweis bleibt leer (auch mit www.).
  {
    const { page, kontext, stand } = await laden(browser, { init: [UNSICHTBAR, VERWEISER('http://www.beispiel.test/vorher/')] });
    await warten(page);
    await gehen(page);
    ok(stand.beacons.length === 0, `Unsichtbar: nichts, auch kein verlassen (${stand.beacons.length})`);
    await page.evaluate(() => { window.__zeigen(); window.__zeigen(); });
    await warten(page);
    ok(arten(stand) === 'aufruf' && nachrichten(stand)[0].ref === '', `Unsichtbar → sichtbar: genau ein aufruf, Verweis auf die eigene Domain bleibt leer (${arten(stand)}, „${(nachrichten(stand)[0] || {}).ref}“)`);
    await page.evaluate(() => window.__verstecken());
    await warten(page);
    ok(arten(stand) === 'aufruf,verlassen', `Verstecken des Tabs sendet verlassen (${arten(stand)})`);
    await kontext.close();
  }

  // 6. Störungen: werfender sendBeacon, nicht erreichbares Skript, kein JavaScript — die Seite zeigt ihren Inhalt, kein unbehandelter Fehler.
  for (const [was, o] of [['sendBeacon wirft', { init: [BEACON_WIRFT] }], ['statistik.js nicht erreichbar', { skriptAbbruch: true }], ['Pfad zum Skript falsch', { skriptPfad: 'fehlt.js' }], ['ohne JavaScript', { ohneJs: true }]]) {
    const { page, kontext, stand } = await laden(browser, o);
    await warten(page);
    await gehen(page).catch(() => {});
    ok(await page.locator('h1').innerText() === 'Willkommen', `${was}: die Seite zeigt ihren Inhalt`);
    ok(stand.fehler.length === 0, `${was}: kein unbehandelter Fehler (${stand.fehler.join(' | ')})`);
    ok(stand.beacons.length === 0, `${was}: nichts gesendet (${stand.beacons.length})`);
    await kontext.close();
  }

  // 7. Echter Seitenwechsel: pagehide beim Weitergehen löst verlassen noch aus, die neue Seite zählt ihren eigenen Aufruf. Playwright bricht eine Anfrage, die beim
  //    Entladen der Seite noch läuft, selbst ab (net::ERR_ABORTED) und gibt ihren Inhalt nicht mehr her; darum zählt hier nur, dass die zweite Anfrage gestellt wurde
  //    (Ereignis „request“; je nach Takt ist ihr Inhalt noch lesbar — dann „verlassen“ — oder nicht). Dass sie ein verlassen mit Dauer und Tiefe ist, belegen tests/statistik.test.php (Attrappe) und der Test mit pagehide oben.
  {
    const { page, kontext, stand } = await laden(browser, {});
    await warten(page);
    await page.goto('http://beispiel.test/andere/');
    await page.waitForTimeout(500);
    ok(/^aufruf,(verlassen|\(abgebrochen\)),aufruf$/.test(stand.angefragt.join()), `Seitenwechsel: aufruf, beim Weitergehen eine zweite Anfrage (verlassen), auf der neuen Seite wieder aufruf (${stand.angefragt.join()})`);
    await kontext.close();
  }

  // 8. Vertrag v2 Teil 1 (3.65.0): mehrfaches Verlassen und „da“, auf einer Seite mit abgefangenem sendBeacon und vorgespulter Uhr.
  const zeigen = (page) => page.evaluate(() => window.__zeigen());
  const verstecken = (page) => page.evaluate(() => window.__verstecken());
  {
    // (a) aufruf einmal, auch wenn die Seite mehrfach „sichtbar“ meldet; (b) verstecken → verlassen (Delta, mindestens 1); zeigen, 5 s, verstecken → zweites verlassen (Delta 5), kein zweiter aufruf
    const { page, kontext, stand } = await laden(browser, { init: [UNSICHTBAR], uhr: '2026-10-30T10:00:00' });
    await warten(page);
    ok(stand.beacons.length === 0, 'v2: unsichtbar geladen sendet nichts');
    await zeigen(page); await zeigen(page);
    await warten(page);
    ok(arten(stand) === 'aufruf', `v2 (a): aufruf genau einmal (${arten(stand)})`);
    await verstecken(page);
    await zeigen(page);
    await page.clock.fastForward(5000);
    await verstecken(page);
    await verstecken(page);
    await warten(page);
    ok(arten(stand) === 'aufruf,verlassen,verlassen', `v2 (b): zwei verlassen, nur ein aufruf (${arten(stand)})`);
    const [, v1, v2] = nachrichten(stand);
    ok(v1 && v1.v === 2 && v1.dauer === 1, `v2 (b): sofort versteckt → dauer 1, nicht 0 (${v1 && v1.dauer})`);
    ok(v2 && v2.v === 2 && v2.dauer >= 5 && v2.dauer <= 6, `v2 (b): das zweite verlassen zählt nur die 5 Sekunden seit dem letzten Senden (${v2 && v2.dauer})`);
    ok(nachrichten(stand).every((m) => m.v === 2), 'v2: jede Nachricht trägt v 2');
    // (c) nach über 60 s Abwesenheit meldet sich die Seite bei der Rückkehr mit einem kleinen „da“, ohne neuen aufruf
    await page.clock.fastForward(61000);
    await zeigen(page);
    await zeigen(page);
    await page.clock.fastForward(2000);
    await verstecken(page);
    await warten(page);
    ok(arten(stand) === 'aufruf,verlassen,verlassen,da,verlassen', `v2 (c): nach 61 s Abwesenheit genau ein da, kein neuer aufruf (${arten(stand)})`);
    const da = nachrichten(stand)[3] || {};
    ok(Object.keys(da).sort().join() === 'art,breite,liste,pfad,ref,utm,v' && da.art === 'da' && da.liste === 'web:beispiel.test', `v2 (c): da trägt nur den Rahmen, keine dauer und keine tiefe (${JSON.stringify(da)})`);
    const letztes = nachrichten(stand)[4] || {};
    ok(letztes.dauer >= 2 && letztes.dauer <= 3, `v2 (c): das verlassen nach dem da zählt nur die 2 Sekunden seit der Rückkehr (${letztes.dauer})`);
    // (f) jede Nachricht höchstens 2 KB; keine Fremdanfrage; kein Fehler; kein Speicher
    ok(stand.beacons.every((b) => b.text.length <= 2048), 'v2 (f): jede Nachricht höchstens 2 KB');
    ok(stand.fremd.length === 0 && stand.konsole.length === 0 && stand.fehler.length === 0, 'v2: keine Fremdanfrage, kein Fehler');
    await kontext.close();
  }
  {
    // kürzer als eine Minute weg: kein da
    const { page, kontext, stand } = await laden(browser, { init: [UNSICHTBAR], uhr: '2026-10-30T10:00:00' });
    await zeigen(page); await page.clock.fastForward(3000); await verstecken(page);
    await page.clock.fastForward(30000); await zeigen(page); await page.clock.fastForward(1000); await verstecken(page);
    await warten(page);
    ok(arten(stand) === 'aufruf,verlassen,verlassen', `v2: 30 s weg → kein da (${arten(stand)})`);
    await kontext.close();
  }
  // (d) Do Not Track / Global Privacy Control / data-statistik="aus" und (e) Vorschau-Host: auch mit mehrfachem Verlassen und langer Abwesenheit nichts
  for (const [was, o] of [['Do Not Track', { init: [UNSICHTBAR, DNT] }], ['Global Privacy Control', { init: [UNSICHTBAR, GPC] }], ['data-statistik="aus"', { init: [UNSICHTBAR], body: 'data-statistik="aus"' }], ['Vorschau-Host', { init: [UNSICHTBAR], host: 'develop.beispiel.test' }]]) {
    const { page, kontext, stand } = await laden(browser, { ...o, uhr: '2026-10-30T10:00:00' });
    await zeigen(page); await page.clock.fastForward(3000); await verstecken(page);
    await page.clock.fastForward(90000); await zeigen(page); await verstecken(page);
    await warten(page);
    ok(stand.beacons.length === 0 && stand.angefragt.length === 0 && stand.fremd.length === 0, `v2: ${was} → nichts gesendet, auch kein da (${arten(stand)})`);
    await kontext.close();
  }

  await browser.close();
  console.log(problem.length ? `FEHLER — ${problem.length} Problem(e), ${pruefungen - problem.length} von ${pruefungen} Prüfungen bestanden\n  ✗ ${problem.join('\n  ✗ ')}` : `ok — ${pruefungen} Prüfungen bestanden`);
  process.exit(problem.length ? 1 : 0);
}

haupt().catch((fehler) => { console.error(fehler); process.exit(2); });
