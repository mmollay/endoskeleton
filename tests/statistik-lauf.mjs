#!/usr/bin/env node
/*
 * Führt statistik.js (Besucherzähler für Webseiten) in einer Attrappe der Seite aus und schreibt die Ergebnisse als JSON auf die Standardausgabe.
 * Node genügt, keine Abhängigkeiten (kein npm install). Die Prüfungen stehen in tests/statistik.test.php; den echten Browser prüft ci/browser-statistik.js.
 * Die Attrappe zählt jeden Zugriff auf Cookies und Web-Speicher und fängt jede Nachricht an sendBeacon ab — es geht nie etwas hinaus.
 */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const QUELLE = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'statistik.js'), 'utf8');

/** Eine Seite aufbauen, das Skript laufen lassen, danach o.ablauf(s) ausführen; Ergebnis: gesendet, zugriffe, fehler. */
function lauf(o = {}) {
  const gesendet = [], zugriffe = [], hoerer = {};
  let jetzt = 0, sicht = o.sichtbar === false ? 'hidden' : 'visible';
  const ziel = (name) => ({
    addEventListener: (art, f) => { (hoerer[name + art] ||= []).push(f); },
    removeEventListener: (art, f) => { hoerer[name + art] = (hoerer[name + art] || []).filter((x) => x !== f); },
  });
  const fenster = {
    ...ziel('w'), scrollY: 0, innerWidth: o.breite ?? 390, innerHeight: o.innen ?? 900,
    location: { hostname: o.host ?? 'beispiel.test', pathname: o.pfad ?? '/seite/', search: o.suche ?? '' },
    navigator: { doNotTrack: o.dnt ?? null, globalPrivacyControl: o.gpc, sendBeacon: o.beacon === 'fehlt' ? undefined : (adresse, daten) => {
      if (o.beacon === 'wirft') { throw new Error('Beacon kaputt'); }
      gesendet.push({ adresse, typ: daten.type, text: daten.teile.join('') });
      return true;
    } },
    performance: { now: () => jetzt },
    URL, URLSearchParams, Blob: class { constructor(teile, opt) { this.teile = teile; this.type = opt.type; } },
  };
  fenster.window = fenster;
  for (const name of ['localStorage', 'sessionStorage', 'indexedDB']) { Object.defineProperty(fenster, name, { get() { zugriffe.push(name); return {}; } }); }
  const dokument = {
    ...ziel('d'), referrer: o.referrer ?? '',
    currentScript: o.skript !== undefined ? o.skript : { dataset: 'quelle' in o ? (o.quelle === undefined ? {} : { quelle: o.quelle }) : { quelle: 'web:beispiel.test' } },
    body: o.ohneBody ? null : { dataset: o.body ?? {} },
  };
  dokument.documentElement = o.hoehe === 'wirft' ? { get scrollHeight() { throw new Error('Höhe nicht lesbar'); } } : { scrollHeight: o.hoehe ?? 4000 };
  Object.defineProperty(dokument, 'visibilityState', { get: () => sicht });
  Object.defineProperty(dokument, 'cookie', { get() { zugriffe.push('cookie'); return ''; }, set() { zugriffe.push('cookie'); } });
  fenster.document = dokument;
  const s = {
    zeit: (ms) => { jetzt += ms; },
    scrollen: (y) => { fenster.scrollY = y; s.feuer('w', 'scroll'); },
    feuer: (wo, art) => { for (const f of hoerer[wo + art] || []) { f({}); } },
    sichtbar: (ja) => { sicht = ja ? 'visible' : 'hidden'; s.feuer('d', 'visibilitychange'); },
    spionieren: () => vm.runInContext('localStorage; document.cookie', kontext),
  };
  const kontext = vm.createContext(fenster);
  const vorher = new Set(Object.keys(fenster));
  let fehler = null;
  try { vm.runInContext(QUELLE, kontext); o.ablauf?.(s); } catch (e) { fehler = String(e); }   // ein Fehler, der hier ankommt, wäre in der Seite ein unbehandelter Fehler
  return { gesendet, zugriffe, fehler, neueGlobale: Object.keys(fenster).filter((k) => !vorher.has(k)) };
}

const gehen = (s) => { s.zeit(12000); s.scrollen(1800); s.feuer('w', 'pagehide'); };
const ergebnis = {
  normal: lauf({
    referrer: 'https://www.Verweis.test/p/abc?x=1', suche: '?utm_source=Insta%20gram%24b&utm_medium=social&utm_campaign=' + 'a'.repeat(80), ablauf: (s) => { gehen(s); s.feuer('w', 'pagehide'); s.sichtbar(false); },
  }),
  mitWww: lauf({ host: 'www.beispiel.test' }),
  eigenerVerweis: lauf({ referrer: 'https://www.beispiel.test/vorher/' }),
  unsichtbar: lauf({ sichtbar: false, ablauf: (s) => { s.feuer('w', 'pagehide'); s.sichtbar(false); } }),
  spaeterAufruf: lauf({ sichtbar: false, ablauf: (s) => { s.sichtbar(true); s.sichtbar(true); } }),
  ohneSpeicher: lauf({ ablauf: gehen }),
  spionWirkt: lauf({ ablauf: (s) => s.spionieren() }),
  tiefe0: lauf({ hoehe: 100000, innen: 10, ablauf: (s) => { s.zeit(1000); s.feuer('w', 'pagehide'); } }),
  tiefe25: lauf({ hoehe: 4000, ablauf: (s) => { s.zeit(1000); s.feuer('w', 'pagehide'); } }),
  tiefe50: lauf({ hoehe: 3600, ablauf: (s) => { s.scrollen(900); s.feuer('w', 'pagehide'); } }),
  tiefe100: lauf({ hoehe: 500, ablauf: (s) => { s.feuer('w', 'pagehide'); } }),
  zurueckgescrollt: lauf({ hoehe: 4000, ablauf: (s) => { s.scrollen(3100); s.scrollen(0); s.feuer('w', 'pagehide'); } }),
  versteckterTab: lauf({ hoehe: 4000, ablauf: (s) => { s.zeit(5000); s.scrollen(1800); s.sichtbar(false); } }),
  dauerGrenze: lauf({ ablauf: (s) => { s.zeit(9_000_000); s.feuer('w', 'pagehide'); } }),
  groesstFall: lauf({
    pfad: '/' + 'x'.repeat(2000), referrer: 'https://' + 'h'.repeat(300) + '.example/', suche: '?utm_source=' + 'q'.repeat(500) + '&utm_medium=' + 'm'.repeat(500) + '&utm_campaign=' + 'k'.repeat(500), breite: 20000,
    ablauf: gehen,
  }),
  beaconWirft: lauf({ beacon: 'wirft', ablauf: gehen }),
  beaconFehlt: lauf({ beacon: 'fehlt', ablauf: gehen }),
  fremd: Object.fromEntries(['develop.beispiel.test', 'localhost', 'beispiel.test.example', 'xbeispiel.test', 'www.www.beispiel.test', 'beispiel.example', ''].map((host) => [host, lauf({ host, ablauf: gehen })])),
  aus: {
    dnt: lauf({ dnt: '1', ablauf: gehen }), gpc: lauf({ gpc: true, ablauf: gehen }), body: lauf({ body: { statistik: 'aus' }, ablauf: gehen }),
    ohneBody: lauf({ ohneBody: true, ablauf: gehen }), dntEgal: lauf({ dnt: '0', gpc: false, body: { statistik: 'an' }, ablauf: gehen }),
  },
  // Der Host passt immer zur Domain der Quelle: hier verhindert nur das Muster der Quelle (Panel: Quellen::DOMAIN, höchstens 56 Zeichen, mit Punkt), dass gesendet wird.
  quellen: Object.fromEntries([undefined, '', 'web:', 'web:ohnepunkt', 'web:Beispiel.Test', 'beispiel.test', 'lp:beispiel.test', 'web:beispiel.test ', 'web: beispiel.test', 'web:a..test', 'web:.test.at',
    'web:' + 'a'.repeat(54) + '.at', 'web:' + 'a'.repeat(53) + '.at', 'web:beispiel.test\n'].map((q) => [String(q), lauf({ quelle: q, host: String(q ?? 'beispiel.test').replace(/^\w+:/, '').trim().toLowerCase(), ablauf: gehen })])),
  ohneSkript: lauf({ skript: null, ablauf: gehen }),
  // Stör-Fälle: Der Zähler darf nirgends einen Fehler nach außen lassen — Einrichtung (Skript ohne Daten), Aufruf beim Sichtbarwerden, Verlassen.
  skriptOhneDaten: lauf({ skript: {}, ablauf: gehen }),
  beaconWirftBeiSichtbar: lauf({ sichtbar: false, beacon: 'wirft', ablauf: (s) => s.sichtbar(true) }),
  hoeheWirft: lauf({ hoehe: 'wirft', ablauf: gehen }),
  andereDomain: lauf({ quelle: 'web:anders.example', host: 'anders.example', pfad: '/x' }),
};
process.stdout.write(JSON.stringify(ergebnis));
