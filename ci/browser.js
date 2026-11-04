#!/usr/bin/env node
/*
 * Browsertests der Landingpage-Bausteine gegen das gebaute Musterbuch (musterbuch/, neutrales Testprofil).
 * Die Seiten kommen über die Scheinadresse http://lp.test/ direkt aus dem Ordner (wie GRIDKit gk.test); jede andere
 * Anfrage wird abgebrochen und gezählt.
 *
 *   ES_PLAYWRIGHT=/home/pawbot/projects/panel/node_modules/playwright node ci/browser.js
 *
 * Kein npm install im Endoskeleton (Schadcode-Commit eb8a34d) — Playwright kommt von außen.
 *
 * Hell und dunkel (3.70.0): Zu Beginn baut ci/musterbuch-bauen.php das Musterbuch mit dem dunklen Testprofil
 * (lp/musterbuch/_testprofil-dunkel) in einen Temp-Ordner; es liegt unter http://lp.test/dunkel/. Das PHP dafür ist
 * ES_PHP (Vorgabe php; auf server6 php8.2, dort ist php 8.5 ohne mbstring).
 */
'use strict';

const pw = require(process.env.ES_PLAYWRIGHT || '/home/pawbot/projects/panel/node_modules/playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const MUSTERBUCH = path.join(__dirname, '..', 'musterbuch');
const BILDER = path.join(__dirname, 'bilder');
const BREITEN = [320, 360, 390, 768, 1280, 1440];
// Das dunkle Musterbuch (3.70.0): jeder Baustein auch auf dunklem Kundenprofil.
const DUNKEL = fs.mkdtempSync(path.join(os.tmpdir(), 'lp-dunkel-'));
process.on('exit', () => fs.rmSync(DUNKEL, { recursive: true, force: true }));   // auch nach einem Fehler (exit 1 oder 2)
execFileSync(process.env.ES_PHP || 'php', [path.join(__dirname, 'musterbuch-bauen.php'), '--profil', path.join(__dirname, '..', 'lp', 'musterbuch', '_testprofil-dunkel'), '--ziel', DUNKEL], { stdio: 'pipe' });
const TOENE = [['hell', (name) => name], ['dunkel', (name) => (name ? `dunkel/${name}` : 'dunkel')]];
// Spione für die Statistik (3.56.0): zählen jeden Zugriff auf Cookies, Web-Speicher und IndexedDB in window.__zugriffe.
const SPION = `(() => {
  window.__zugriffe = [];
  const merke = (w) => window.__zugriffe.push(w);
  for (const m of ['getItem', 'setItem', 'removeItem', 'clear', 'key']) {
    const alt = Storage.prototype[m];
    Storage.prototype[m] = function (...a) { merke('Storage.' + m); return alt.apply(this, a); };
  }
  for (const name of ['localStorage', 'sessionStorage']) {
    const halter = Object.getOwnPropertyDescriptor(window, name) ? window : Window.prototype;
    const d = Object.getOwnPropertyDescriptor(halter, name);
    if (d && d.get) { Object.defineProperty(halter, name, { ...d, get() { merke(name); return d.get.call(this); } }); }
  }
  const c = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
  Object.defineProperty(Document.prototype, 'cookie', { configurable: true, get() { merke('cookie lesen'); return c.get.call(this); }, set(v) { merke('cookie schreiben'); c.set.call(this, v); } });
  const auf = IDBFactory.prototype.open;
  IDBFactory.prototype.open = function (...a) { merke('indexedDB'); return auf.apply(this, a); };
})();`;
const DNT = `Object.defineProperty(Navigator.prototype, 'doNotTrack', { configurable: true, get: () => '1' });`;
const GPC = `Object.defineProperty(Navigator.prototype, 'globalPrivacyControl', { configurable: true, get: () => true });`;
// sendBeacon wirft: immer, oder erst ab dem zweiten Aufruf (dann werfen formular und absenden aus dem Ereignis heraus).
const WERFENDER_BEACON = (ab) => `{ let n = 0; Navigator.prototype.sendBeacon = function () { if (n++ >= ${ab}) { throw new Error('Beacon kaputt'); } return true; }; }`;
const VERWEISER = (url) => `Object.defineProperty(Document.prototype, 'referrer', { configurable: true, get: () => ${JSON.stringify(url)} });`;
// Vorab-Laden: die Seite ist zunächst unsichtbar; window.__zeigen() macht sie sichtbar und meldet es.
const UNSICHTBAR = `(() => {
  let sicht = 'hidden';
  Object.defineProperty(Document.prototype, 'visibilityState', { configurable: true, get: () => sicht });
  window.__zeigen = () => { sicht = 'visible'; document.dispatchEvent(new Event('visibilitychange')); };
  window.__verstecken = () => { sicht = 'hidden'; document.dispatchEvent(new Event('visibilitychange')); };
})();`;
// Spion für das Verlassen (3.57.0): merkt sich, ob jeder Scroll-Hörer passiv ist, und zählt die Lesezugriffe auf scrollHeight.
const SCROLLSPION = `(() => {
  window.__scrollHoerer = [];
  window.__hoehenLesen = 0;
  const alt = EventTarget.prototype.addEventListener;
  EventTarget.prototype.addEventListener = function (art, f, o) {
    if (art === 'scroll') { window.__scrollHoerer.push(!!(o && typeof o === 'object' && o.passive)); }
    return alt.call(this, art, f, o);
  };
  const d = Object.getOwnPropertyDescriptor(Element.prototype, 'scrollHeight');
  Object.defineProperty(Element.prototype, 'scrollHeight', { ...d, get() { window.__hoehenLesen++; return d.get.call(this); } });
})();`;
const TYPEN = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.png': 'image/png', '.woff2': 'font/woff2', '.mp4': 'video/mp4' };

let bestanden = 0;
const fehler = [];
const pruefe = (bedingung, was) => { if (bedingung) { bestanden++; } else { fehler.push(was); } };

/**
 * Öffnet eine Musterbuch-Seite (optionen.suche z. B. '?anmeldung=ok#anmeldung', optionen.hoehe statt 900 px);
 * fremd sammelt alle Anfragen außerhalb von lp.test. Die Plätze-Abfrage (3.52.0) an https://beispiel.invalid/lp/plaetze
 * (Formularziel des Testprofils) beantwortet eine Attrappe: optionen.plaetze = { status, daten } oder { abbruch: true };
 * ohne Angabe 12 von 30 frei. abfragen sammelt ihre Adressen — sie zählen nicht als Fremdanfrage.
 * Die Statistik (3.56.0) meldet an das Formularziel mit /anmelden → /treffer (https://beispiel.invalid/treffer, im Panel /lp/treffer); eine Attrappe antwortet 204 und sammelt die Beacons
 * (beacons: Adresse, Methode, Inhaltstyp, Text) — sie zählen ebenfalls nicht als Fremdanfrage. Mit optionen.anmelden antwortet sie auch
 * auf das Anmeldeziel mit 204 (anmeldungen), sonst bleibt das Absenden eine Fremdanfrage. optionen.init: Skripte vor der Seite
 * (Spione, DNT), optionen.umschreiben: Änderung am HTML der Seite.
 */
async function oeffnen(browser, name, breite, optionen = {}) {
  const kontext = await browser.newContext({ viewport: { width: breite, height: optionen.hoehe || 900 }, reducedMotion: optionen.reducedMotion || 'no-preference', javaScriptEnabled: optionen.ohneJs !== true });
  const page = await kontext.newPage();
  const fremd = [];
  const abfragen = [];
  const beacons = [];
  const anmeldungen = [];
  const angefragt = [];
  for (const skript of optionen.init || []) { await kontext.addInitScript(skript); }
  await page.route('**/*', (r) => {
    const u = new URL(r.request().url());
    if (u.host === 'beispiel.invalid' && /^\/(lp\/)?treffer$/.test(u.pathname)) {
      beacons.push({ adresse: u.href, methode: r.request().method(), typ: r.request().headers()['content-type'] || '', text: r.request().postData() || '' });
      return r.fulfill({ status: 204 });
    }
    if (optionen.anmelden && u.host === 'beispiel.invalid' && /^\/(lp\/)?anmelden$/.test(u.pathname)) {
      anmeldungen.push(r.request().method());
      return r.fulfill({ status: 204 });
    }
    if (u.host === 'beispiel.invalid' && u.pathname === '/lp/plaetze') {
      abfragen.push(u.href);
      const p = optionen.plaetze || { status: 200, daten: { frei: 12, gesamt: 30, voll: false } };
      if (p.abbruch) { return r.abort(); }
      return r.fulfill({ status: p.status, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': 'http://lp.test' }, body: JSON.stringify(p.daten) });
    }
    if (u.host !== 'lp.test') { fremd.push(u.href); return r.abort(); }
    angefragt.push(decodeURIComponent(u.pathname));
    const dunkel = /^\/dunkel(\/|$)/.test(u.pathname);
    const wurzel = dunkel ? DUNKEL : MUSTERBUCH;
    let f = path.join(wurzel, decodeURIComponent(dunkel ? u.pathname.slice('/dunkel'.length) || '/' : u.pathname));
    if (f.endsWith(path.sep)) { f = path.join(f, 'index.html'); }
    if (!f.startsWith(wurzel) || !fs.existsSync(f)) { return r.fulfill({ status: 404, body: 'fehlt' }); }
    const inhalt = fs.readFileSync(f);
    return r.fulfill({ status: 200, body: optionen.umschreiben && f.endsWith('.html') ? optionen.umschreiben(inhalt.toString('utf8')) : inhalt, contentType: TYPEN[path.extname(f)] || 'application/octet-stream' });
  });
  if (optionen.uhr) { await page.clock.install({ time: new Date(optionen.uhr) }); }
  await page.goto(`http://lp.test/${name}${name && !name.endsWith('.html') ? '/' : ''}${optionen.suche || ''}`);   // pruefliste.html (3.70.0) ohne Schrägstrich
  return { page, fremd, kontext, abfragen, beacons, anmeldungen, angefragt };
}

/**
 * Kontrast jedes sichtbaren Textknotens gegen seinen tatsächlichen, deckenden Hintergrund (WCAG).
 * Mit nur (CSS-Auswahl) nur diese Elemente — und ein Befund, wenn keines davon gemessen werden konnte.
 */
function kontrastImBrowser(nur) {
  const rgb = (s) => (s.match(/[\d.]+/g) || []).map(Number);
  const hell = ([r, g, b]) => {
    const k = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * k(r) + 0.7152 * k(g) + 0.0722 * k(b);
  };
  const aus = [];
  let gemessen = 0;
  for (const el of document.querySelectorAll(nur || 'body *')) {
    const text = [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join('').trim();
    if (!text || el.closest('[hidden], script, .form-hp, .skip-link')) { continue; }
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) { continue; }
    const st = getComputedStyle(el);
    let grund = null;
    for (let e = el; e; e = e.parentElement) {
      const s = getComputedStyle(e);
      if (s.backgroundImage !== 'none') { grund = 'bild'; break; }
      const c = rgb(s.backgroundColor);
      if (c.length === 4 && c[3] === 0) { continue; }
      if (c.length === 4 && c[3] < 0.99) { grund = 'halb'; break; }
      grund = c; break;
    }
    if (!Array.isArray(grund)) { continue; }
    gemessen++;
    const vorn = rgb(st.color);
    const a = hell(vorn), b = hell(grund);
    const verhaeltnis = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    const groesse = parseFloat(st.fontSize);
    const gross = groesse >= 24 || (groesse >= 18.66 && parseInt(st.fontWeight, 10) >= 700);
    if (verhaeltnis < (gross ? 3 : 4.5)) { aus.push(`${text.slice(0, 40)} (${verhaeltnis.toFixed(2)}:1)`); }
  }
  if (nur && gemessen === 0) { aus.push(`${nur}: nicht gemessen`); }
  return aus;
}

/**
 * Kontrast im Druck (3.70.0): Der Browser druckt keine Hintergründe — jeder sichtbare Text steht auf weißem Papier. Gemessen wird deshalb
 * jede Schriftfarbe gegen Weiß, unabhängig vom Hintergrund der Seite (ein dunkles Profil hätte sonst helle Schrift auf weißem Papier).
 */
function papierKontrast() {
  const rgb = (s) => (s.match(/[\d.]+/g) || []).map(Number);
  const hell = ([r, g, b]) => {
    const k = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * k(r) + 0.7152 * k(g) + 0.0722 * k(b);
  };
  const aus = [];
  for (const el of document.querySelectorAll('body *')) {
    const text = [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join('').trim();
    if (!text || el.closest('[hidden], script, .form-hp, .skip-link')) { continue; }
    const r = el.getBoundingClientRect();
    const st = getComputedStyle(el);
    if (r.width === 0 || r.height === 0 || st.visibility === 'hidden') { continue; }
    // Deckkraft der Schrift: Alpha der Farbe mal opacity des Elements und aller Vorfahren — über Weiß verrechnet; durchsichtig = unsichtbar
    const [cr, cg, cb, ca = 1] = rgb(st.color);
    let deck = ca;
    for (let e = el; e; e = e.parentElement) { deck *= parseFloat(getComputedStyle(e).opacity); }
    if (deck <= 0.01) { aus.push(`${text.slice(0, 40)} (unsichtbar auf Papier)`); continue; }
    const verhaeltnis = 1.05 / (hell([cr, cg, cb].map((c) => c * deck + 255 * (1 - deck))) + 0.05);
    const groesse = parseFloat(st.fontSize);
    const gross = groesse >= 24 || (groesse >= 18.66 && parseInt(st.fontWeight, 10) >= 700);
    if (verhaeltnis < (gross ? 3 : 4.5)) { aus.push(`${text.slice(0, 40)} (${verhaeltnis.toFixed(2)}:1 auf Papier)`); }
  }
  return aus;
}

/**
 * Tastatur-Rundgang (3.70.0): Tab durch alle bedienbaren Elemente der Seite, bis der Fokus die Seite verlässt. Je Halt: sichtbarer
 * Fokusrahmen (oder der hereingerutschte Sprunglink), im Bild und nicht verdeckt (der Punkt in der Mitte des sichtbaren Teils
 * gehört zum Element selbst — eine klebende Leiste davor fiele hier auf).
 */
async function tastaturRundgang(page, hoechstens = 150) {
  const halte = [];
  halte.zuEnde = false;   // true, wenn der Fokus die Seite wieder verlassen hat (sonst endete der Rundgang an der Grenze)
  for (let i = 0; i < hoechstens; i++) {
    await page.keyboard.press('Tab');
    const h = await page.evaluate(() => {
      const e = document.activeElement;
      if (!e || e === document.body || e === document.documentElement) { return null; }
      const s = getComputedStyle(e);
      const r = e.getBoundingClientRect();
      const sichtbar = e.classList.contains('skip-link') ? s.top === '0px' : (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || s.boxShadow !== 'none';
      const x1 = Math.max(r.left, 0), x2 = Math.min(r.right, window.innerWidth), y1 = Math.max(r.top, 0), y2 = Math.min(r.bottom, window.innerHeight);
      const imBild = x2 - x1 >= 1 && y2 - y1 >= 1;
      const t = imBild ? document.elementFromPoint((x1 + x2) / 2, (y1 + y2) / 2) : null;
      const klasse = typeof e.className === 'string' && e.className ? '.' + e.className.trim().split(/\s+/)[0] : '';
      return { was: e.tagName.toLowerCase() + (e.id ? '#' + e.id : klasse), sichtbar, imBild, frei: !!t && (t === e || e.contains(t)) };
    });
    if (h === null) {
      if (halte.length) { halte.zuEnde = true; break; }
      continue;
    }
    halte.push(h);
  }
  return halte;
}

(async () => {
  const browser = await pw.chromium.launch();
  fs.mkdirSync(BILDER, { recursive: true });
  const seiten = fs.readdirSync(MUSTERBUCH, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name).sort();
  pruefe(seiten.length >= 16, `Musterbuch enthält alle Seiten (${seiten.length})`);

  // Jede Seite in jeder Breite, hell und dunkel (3.70.0): kein waagrechtes Scrollen, keine Fremdanfrage, Kontrast gemessen
  const dunkleSeiten = fs.readdirSync(DUNKEL, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name).sort();
  pruefe(JSON.stringify(dunkleSeiten) === JSON.stringify(seiten), `dunkles Musterbuch enthält dieselben Seiten (${dunkleSeiten.length} von ${seiten.length})`);
  for (const [ton, adresse] of TOENE) {
    for (const name of ['', 'pruefliste.html', ...seiten]) {   // Übersicht, Prüfliste (3.70.0), jede Seite
      for (const breite of BREITEN) {
        const was = `${ton} ${name || 'Übersicht'} @${breite}`;
        const { page, fremd, kontext } = await oeffnen(browser, adresse(name), breite);
        const ueber = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        pruefe(ueber <= 0, `${was}: waagrecht ${ueber}px zu breit`);
        pruefe(fremd.length === 0, `${was}: Fremdanfragen ${fremd.join(', ')}`);
        const schwach = await page.evaluate(kontrastImBrowser);
        pruefe(schwach.length === 0, `${was}: zu wenig Kontrast: ${schwach.join(' | ')}`);
        if (name && (breite === 390 || breite === 1440)) { await page.screenshot({ path: path.join(BILDER, `${name}-${ton}-${breite}.png`), fullPage: true }); }
        await kontext.close();
      }
    }
  }

  // Qualitätsboden (3.70.0) für jede Seite, hell und dunkel: Tastatur (Fokus sichtbar, im Bild, nicht verdeckt), weniger Bewegung
  // (keine laufende Animation, kein Übergang, kein weiches Scrollen) und Druck (kein Überlauf, nichts Festes oder Klebendes, Fragen
  // aufgeklappt, jeder Text lesbar auf weißem Papier; nach dem Druck wie vorher).
  for (const [ton, adresse] of TOENE) {
    for (const name of seiten) {
      for (const breite of [390, 1440]) {
        const was = `${ton} ${name} @${breite}`;
        const { page, kontext } = await oeffnen(browser, adresse(name), breite, { reducedMotion: 'reduce' });
        // erst, wenn alles geladen ist: der Bewegungs-Player setzt die Größe seiner Leinwand nach dem Laden der Bewegung — ein früher Tab-Halt rutschte sonst aus dem Bild
        await page.waitForLoadState('networkidle');
        const halte = await tastaturRundgang(page);
        pruefe(halte.length >= 2, `${was} Tastatur: Tab erreicht die Seite (${halte.length} Halte)`);
        pruefe(halte.zuEnde, `${was} Tastatur: Rundgang erreicht das Ende der Seite (nach ${halte.length} Halten noch nicht)`);
        const ohneRahmen = halte.filter((h) => !h.sichtbar).map((h) => h.was);
        pruefe(ohneRahmen.length === 0, `${was} Tastatur: Fokus nicht sichtbar an ${ohneRahmen.join(', ')}`);
        const verdeckt = halte.filter((h) => !h.imBild || !h.frei).map((h) => `${h.was}${h.imBild ? ' (verdeckt)' : ' (nicht im Bild)'}`);
        pruefe(verdeckt.length === 0, `${was} Tastatur: Fokus nicht zu sehen an ${verdeckt.join(', ')}`);
        await kontext.close();
      }
      {
        const { page, kontext } = await oeffnen(browser, adresse(name), 1280, { reducedMotion: 'reduce' });
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await page.waitForTimeout(150);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(150);
        const bewegt = await page.evaluate(() => ({
          laufend: document.getAnimations().filter((a) => a.playState === 'running').map((a) => (a.effect && a.effect.target ? a.effect.target.tagName.toLowerCase() : '?')),
          // in Sekunden (base.css setzt „weniger Bewegung“ als 0.01ms); wie unten bei „keine Übergänge“ gilt erst über 1 ms als Übergang
          uebergang: [...document.querySelectorAll('*')].filter((e) => getComputedStyle(e).transitionDuration.split(',').some((d) => parseFloat(d) * (d.includes('ms') ? 0.001 : 1) > 0.001)).map((e) => e.tagName.toLowerCase() + (typeof e.className === 'string' && e.className ? '.' + e.className.trim().split(/\s+/)[0] : '')).slice(0, 5),
          weich: getComputedStyle(document.documentElement).scrollBehavior,
        }));
        pruefe(bewegt.laufend.length === 0, `${ton} ${name} weniger Bewegung: laufende Animation an ${bewegt.laufend.join(', ')}`);
        pruefe(bewegt.uebergang.length === 0, `${ton} ${name} weniger Bewegung: Übergang an ${bewegt.uebergang.join(', ')}`);
        pruefe(bewegt.weich !== 'smooth', `${ton} ${name} weniger Bewegung: weiches Scrollen`);
        await kontext.close();
      }
      {
        // „weniger Bewegung“: base.css blendet Linkfarben 0,2 s über — beim Umschalten auf Druck stünde sonst die Farbe mitten im Übergang
        // (beim echten Drucken gibt es keinen Übergang)
        // 718 px: die bedruckbare Breite von A4 bei 96 dpi und üblichem Rand
        const { page, kontext } = await oeffnen(browser, adresse(name), 718, { reducedMotion: 'reduce' });
        const vorher = await page.evaluate(() => [...document.querySelectorAll('details')].map((d) => d.open));
        await page.emulateMedia({ media: 'print' });
        await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
        // Beim Umschalten laufen Übergänge an (base.css: auch bei „weniger Bewegung“ 0,01 ms, am <body>) — erst danach messen, wie beim echten Druck
        await page.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => null))));
        const druck = await page.evaluate(() => ({
          ueber: document.documentElement.scrollWidth - window.innerWidth,
          fest: [...document.querySelectorAll('body *')].filter((e) => ['fixed', 'sticky'].includes(getComputedStyle(e).position) && e.getBoundingClientRect().height > 0).map((e) => e.tagName.toLowerCase() + '.' + e.className),
          zu: [...document.querySelectorAll('details')].filter((d) => !d.open).length,
          // die Bewegung malt hell auf den Grund der Leinwand — der muss gedruckt werden (sonst verschwindet die Figur)
          leinwand: [...document.querySelectorAll('canvas')].filter((c) => c.getBoundingClientRect().width > 0).map((c) => {
            const st = getComputedStyle(c);
            const a = (st.backgroundColor.match(/[\d.]+/g) || []).map(Number);
            return { deckend: a.length === 3 || (a.length === 4 && a[3] > 0.99), exakt: st.printColorAdjust === 'exact' || st.webkitPrintColorAdjust === 'exact' };
          }),
        }));
        pruefe(druck.ueber <= 0, `${ton} ${name} Druck: waagrecht ${druck.ueber}px zu breit`);
        pruefe(druck.fest.length === 0, `${ton} ${name} Druck: feste oder klebende Elemente ${druck.fest.join(', ')}`);
        pruefe(druck.zu === 0, `${ton} ${name} Druck: ${druck.zu} Fragen zugeklappt`);
        pruefe(druck.leinwand.every((l) => l.deckend && l.exakt), `${ton} ${name} Druck: Leinwand ohne gedruckten Grund ${JSON.stringify(druck.leinwand)}`);
        const blass = await page.evaluate(papierKontrast);
        pruefe(blass.length === 0, `${ton} ${name} Druck: auf Papier zu blass: ${blass.join(' | ')}`);
        if (ton === 'dunkel' || name === 'faq--standard') { await page.screenshot({ path: path.join(BILDER, `${name}-${ton}-druck.png`), fullPage: true }); }
        await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
        await page.emulateMedia({ media: 'screen' });
        const nachher = await page.evaluate(() => [...document.querySelectorAll('details')].map((d) => d.open));
        pruefe(JSON.stringify(nachher) === JSON.stringify(vorher), `${ton} ${name} Druck: nach dem Druck aufgeklappt wie vorher (${nachher} statt ${vorher})`);
        await kontext.close();
      }
    }
  }

  // Tastatur: Sprunglink zuerst, jeder Fokus sichtbar, Honigtopf nie im Fokus
  {
    const { page, kontext } = await oeffnen(browser, 'anmeldung--karte', 1280);
    const besucht = [];
    for (let i = 0; i < 25; i++) {
      await page.keyboard.press('Tab');
      if (await page.evaluate(() => document.activeElement.classList.contains('skip-link'))) {
        await page.waitForTimeout(300);   // der Sprunglink rutscht mit 0,2 s Übergang herein
      }
      besucht.push(await page.evaluate(() => {
        const e = document.activeElement;
        const s = getComputedStyle(e);
        // Der Sprunglink wird durch Hereinrutschen sichtbar (base.css), alles andere durch einen Fokusrahmen.
        const sichtbar = e.classList.contains('skip-link') ? s.top === '0px' : s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0;
        return { id: e.id, klasse: e.className, name: e.getAttribute('name'), draussen: e === document.body, sichtbar };
      }));
    }
    pruefe(besucht[0].klasse === 'skip-link', `Sprunglink ist der erste Tab-Halt (${besucht[0].klasse})`);
    const innen = besucht.filter((b) => !b.draussen);
    pruefe(innen.length >= 8, `Tab erreicht die Bedienelemente (${innen.length})`);
    pruefe(innen.every((b) => b.sichtbar), `Fokus überall sichtbar: ${innen.filter((b) => !b.sichtbar).map((b) => b.id || b.klasse).join(', ')}`);
    pruefe(!besucht.some((b) => b.name === 'website'), 'Honigtopf wird nie fokussiert');

    // Formular leer absenden → Fehler am ersten Feld, Fokus dort
    const vorher = await page.evaluate(() => document.getElementById('lp-f-vorname').hasAttribute('aria-describedby'));
    pruefe(!vorher, 'vor dem Absenden keine Fehlerbeschreibung am Feld');
    await page.click('.lp-formular button[type="submit"]');
    const leer = await page.evaluate(() => ({ fokus: document.activeElement.id, ungueltig: [...document.querySelectorAll('[aria-invalid="true"]')].map((e) => e.name), fehlerSichtbar: !document.getElementById('lp-f-vorname-fehler').hidden, beschrieben: document.getElementById('lp-f-vorname').getAttribute('aria-describedby') }));
    pruefe(leer.beschrieben === 'lp-f-vorname-fehler', `Fehlertext als Beschreibung verknüpft (${leer.beschrieben})`);
    pruefe(leer.fokus === 'lp-f-vorname', `Fokus auf dem ersten Fehlerfeld (${leer.fokus})`);
    pruefe(['vorname', 'email', 'einwilligung'].every((n) => leer.ungueltig.includes(n)), `aria-invalid an allen Pflichtfeldern (${leer.ungueltig})`);
    pruefe(leer.fehlerSichtbar, 'Fehlertext sichtbar');
    pruefe(!leer.ungueltig.includes('newsletter'), `das freiwillige Newsletter-Häkchen wird nie als ungültig markiert (${leer.ungueltig})`);
    await page.fill('#lp-f-vorname', 'Anna');
    await page.fill('#lp-f-email', 'keine-adresse');
    await page.click('.lp-formular button[type="submit"]');
    pruefe(await page.evaluate(() => document.activeElement.id) === 'lp-f-email', 'ungültige E-Mail wird erkannt');
    await page.fill('#lp-f-email', 'anna@beispiel.invalid');
    await page.check('#lp-f-einwilligung');
    await page.evaluate(() => document.querySelector('.lp-formular').addEventListener('submit', (e) => e.preventDefault()));
    await page.click('.lp-formular button[type="submit"]');
    pruefe(await page.evaluate(() => document.querySelectorAll('[aria-invalid="true"]').length) === 0, 'vollständig ausgefüllt: keine Fehler');
    pruefe(await page.evaluate(() => !document.getElementById('lp-f-vorname').hasAttribute('aria-describedby')), 'nach der Korrektur keine Fehlerbeschreibung mehr');
    pruefe(await page.evaluate(() => !document.getElementById('lp-f-newsletter').checked), 'das Newsletter-Häkchen blieb dabei leer — Absenden braucht es nicht');
    await kontext.close();
  }

  // Zwei Häkchen (3.55.0): Pflicht-Einwilligung und freiwilliger Newsletter — sichtbar, bedienbar, nie gekoppelt; ohne "newsletter" im Bauplan nur eines
  for (const breite of [360, 390, 1280]) {
    const { page, kontext } = await oeffnen(browser, 'anmeldung--karte', breite);
    const lage = await page.evaluate(() => {
      const ein = document.getElementById('lp-f-einwilligung'), nl = document.getElementById('lp-f-newsletter');
      const sichtbar = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
      const label = (e) => document.querySelector(`label[for="${e.id}"]`);
      return {
        beide: !!ein && !!nl && sichtbar(ein) && sichtbar(nl) && sichtbar(label(ein)) && sichtbar(label(nl)),
        einPflicht: !!ein && ein.required && !ein.checked, nlFrei: !!nl && !nl.required && !nl.checked,
        nlUnterEin: !!ein && !!nl && nl.getBoundingClientRect().top > ein.getBoundingClientRect().top,
        freiwillig: !!nl && /\(freiwillig\)/.test(label(nl).textContent),
        nlText: nl ? label(nl).textContent.replace(/\s+/g, ' ').trim() : '',
        kasten: nl ? nl.getBoundingClientRect().width : 0,
      };
    });
    pruefe(lage.beide, `@${breite}: beide Häkchen samt Beschriftung sichtbar`);
    pruefe(lage.einPflicht && lage.nlFrei, `@${breite}: Einwilligung Pflicht, Newsletter freiwillig, beide leer`);
    pruefe(lage.nlUnterEin, `@${breite}: Newsletter-Häkchen steht unter der Pflicht-Einwilligung`);
    pruefe(lage.freiwillig, `@${breite}: Newsletter als „(freiwillig)“ beschriftet`);
    pruefe(lage.nlText.startsWith('Ja, ich möchte zusätzlich Neuigkeiten der Beispiel Akademie per E-Mail bekommen. Jederzeit abbestellbar.'), `@${breite}: Wortlaut (${lage.nlText})`);
    pruefe(lage.kasten >= 18, `@${breite}: Häkchen groß genug (${lage.kasten}px)`);
    // Absenden nur mit Häkchen 1: alles ausgefüllt, Newsletter leer → kein Fehler; Häkchen 1 leer → Fehler dort, auch wenn Newsletter angehakt ist
    await page.evaluate(() => document.querySelector('.lp-formular').addEventListener('submit', (e) => e.preventDefault()));
    await page.fill('#lp-f-vorname', 'Anna');
    await page.fill('#lp-f-email', 'anna@beispiel.invalid');
    await page.check('#lp-f-newsletter');
    await page.click('.lp-formular button[type="submit"]');
    const nurNl = await page.evaluate(() => ({ fokus: document.activeElement.id, ungueltig: [...document.querySelectorAll('[aria-invalid="true"]')].map((e) => e.name) }));
    pruefe(nurNl.ungueltig.join() === 'einwilligung' && nurNl.fokus === 'lp-f-einwilligung', `@${breite}: Newsletter allein genügt nicht — Fehler an der Pflicht-Einwilligung (${nurNl.ungueltig}, Fokus ${nurNl.fokus})`);
    pruefe(await page.evaluate(() => !document.getElementById('lp-f-einwilligung-fehler').hidden), `@${breite}: Fehlertext der Pflicht-Einwilligung sichtbar`);
    await page.uncheck('#lp-f-newsletter');
    await page.check('#lp-f-einwilligung');
    await page.click('.lp-formular button[type="submit"]');
    pruefe(await page.evaluate(() => document.querySelectorAll('[aria-invalid="true"]').length) === 0, `@${breite}: mit Häkchen 1 und ohne Newsletter: keine Fehler`);
    // Tastatur: das Newsletter-Häkchen ist per Tab erreichbar und mit der Leertaste schaltbar
    await page.focus('#lp-f-einwilligung');
    const halte = [];
    for (let i = 0; i < 3 && halte[halte.length - 1] !== 'lp-f-newsletter'; i++) {
      await page.keyboard.press('Tab');
      halte.push(await page.evaluate(() => document.activeElement.id || document.activeElement.tagName.toLowerCase()));
    }
    pruefe(halte.join(' → ') === 'a → lp-f-newsletter', `@${breite}: Tab von der Einwilligung über den Datenschutz-Link zum Newsletter-Häkchen (${halte.join(' → ')})`);
    await page.keyboard.press('Space');
    pruefe(await page.evaluate(() => document.getElementById('lp-f-newsletter').checked), `@${breite}: Leertaste hakt den Newsletter an`);
    await page.keyboard.press('Space');
    pruefe(await page.evaluate(() => !document.getElementById('lp-f-newsletter').checked), `@${breite}: Leertaste nimmt den Haken wieder weg`);
    if (breite !== 390) {
      await page.emulateMedia({ colorScheme: 'dark' });
      const schwachDunkel = await page.evaluate(kontrastImBrowser, '.lp-einwilligung label, .lp-einwilligung label *');
      pruefe(schwachDunkel.length === 0, `@${breite} dunkel: Kontrast der Häkchen-Texte: ${schwachDunkel.join(' | ')}`);
      await page.screenshot({ path: path.join(BILDER, `anmeldung--karte-${breite}-zwei-haekchen-dunkel.png`), fullPage: true });
      await page.emulateMedia({ colorScheme: 'light' });
    }
    await page.screenshot({ path: path.join(BILDER, `anmeldung--karte-${breite}-zwei-haekchen.png`), fullPage: true });
    await kontext.close();
  }
  {
    const { page, kontext } = await oeffnen(browser, 'anmeldung--breit', 390);
    pruefe(await page.evaluate(() => !document.getElementById('lp-f-newsletter') && !document.querySelector('[name="newsletter_kurz"]') && !!document.getElementById('lp-f-einwilligung')), 'ohne "newsletter" im Bauplan: nur die Pflicht-Einwilligung, keine zweite Kurzsumme');
    await kontext.close();
  }

  // Ohne JavaScript: der Browser prüft die Pflichtfelder selbst, leeres Absenden schickt nichts ab
  {
    const { page, fremd, kontext } = await oeffnen(browser, 'anmeldung--karte', 1280, { ohneJs: true });
    await page.click('.lp-formular button[type="submit"]');
    await page.waitForTimeout(300);
    pruefe(fremd.length === 0 && page.url() === 'http://lp.test/anmeldung--karte/', `ohne JavaScript nichts abgeschickt (${fremd.join(', ')})`);
    pruefe(await page.evaluate(() => document.getElementById('lp-f-vorname').matches(':invalid')), 'ohne JavaScript meldet der Browser das Pflichtfeld');
    await kontext.close();
  }

  // Ohne JavaScript nach dem Rücksprung: keine Meldung (die zeigt nur lp.js), aber der <noscript>-Hinweis erklärt den
  // Ablauf, und die Sprungmarke #anmeldung bringt das Formular ins Bild (ohne weiches Scrollen, s. o.).
  for (const seite of ['anmeldung--karte', 'anmeldung--breit']) {
    const { page, kontext } = await oeffnen(browser, seite, 390, { ohneJs: true, suche: '?anmeldung=ok#anmeldung', hoehe: 300, reducedMotion: 'reduce' });
    const ist = await page.evaluate(() => {
      const h = document.querySelector('.lp-formular noscript .lp-anmeldung-hinweis');
      return { hinweis: h !== null && h.getClientRects().length > 0 ? h.textContent : '', scroll: scrollY, anker: document.getElementById('anmeldung') !== null };
    });
    pruefe(ist.hinweis.includes('Nach dem Absenden bekommst du eine E-Mail'), `${seite} ohne JavaScript: Hinweis zum Ablauf sichtbar („${ist.hinweis}“)`);
    pruefe(ist.anker && ist.scroll > 0, `${seite} ohne JavaScript: Sprungmarke #anmeldung wirkt (scrollY ${ist.scroll})`);
    const schwach = await page.evaluate(kontrastImBrowser, '.lp-formular noscript .lp-anmeldung-hinweis');
    pruefe(schwach.length === 0, `${seite} ohne JavaScript: Kontrast des Hinweises: ${schwach.join(' | ')}`);
    await kontext.close();
  }
  {
    const { page, kontext } = await oeffnen(browser, 'anmeldung--karte', 390);
    pruefe(await page.evaluate(() => [...document.querySelectorAll('.lp-anmeldung-hinweis')].every((e) => e.getClientRects().length === 0)), 'mit JavaScript kein <noscript>-Hinweis');
    await kontext.close();
  }

  // Rückmeldung der Weiterleitung (?anmeldung=…): passende Meldung sichtbar und fokussiert, Felder nur bei „fehler“;
  // unbekannte Werte und die Seite ohne Parameter bleiben wie bisher. Kontrast der sichtbaren Meldung gemessen.
  // Nur 300 px hoch, damit das Formular unter der ersten Bildschirmhöhe liegt: Ins Bild bringt die Meldung allein der
  // Fokus (focus() scrollt) — die Sprungmarke #anmeldung nimmt lp.js vor dem Sprung aus der Adresse. „Weniger
  // Bewegung“, weil base.css sonst weich scrollt und die Lage beim Laden noch nicht feststeht.
  // Datenschutz (27.09.2026): Nach außen gibt es nur ok | fehler (seit 3.52.0 dazu voll, das nicht an der Adresse
  // hängt). Ein „bereits“ (älterer Stand der Weiterleitungsstelle) zeigt genau dieselbe Seite wie „ok“ — sonst ließe sich
  // an einer fremden Adresse die Anmeldung ablesen. Gemessen wird erst nach der Plätze-Abfrage (networkidle): Sonst
  // stünde im Seitentext je nach Zeitpunkt der feste Text oder der Stand der Attrappe, und der Vergleich wackelte.
  {
    const MELDUNGEN = ['lp-anmeldung-danke', 'lp-anmeldung-fehler'];
    const ZUSATZ = 'Falls du schon angemeldet warst, bekommst du keine neue E-Mail.';
    const faelle = [['ok', 'lp-anmeldung-danke', false], ['bereits', 'lp-anmeldung-danke', false], ['fehler', 'lp-anmeldung-fehler', true],
      ['unsinn', null, true], ['constructor', null, true], [null, null, true]];
    for (const seite of ['anmeldung--karte', 'anmeldung--breit']) {
      const seitentext = {};
      for (const [zustand, klasse, felder] of faelle) {
        const suche = zustand === null ? '' : `?anmeldung=${zustand}#anmeldung`;
        const { page, kontext } = await oeffnen(browser, seite, 390, { suche, hoehe: 300, reducedMotion: 'reduce' });
        await page.waitForLoadState('networkidle');
        const ist = await page.evaluate((klassen) => {
          const fs = document.querySelector('.lp-formular fieldset');
          const m = klassen.map((k) => document.querySelector(`.${k}`)).find((e) => e !== null && e.getClientRects().length > 0);
          const r = m ? m.getBoundingClientRect() : null;
          return {
            lage: r ? { oben: Math.round(r.top), unten: Math.round(r.bottom), hoehe: innerHeight, seitenoben: Math.round(r.top + scrollY) } : null,
            sichtbar: klassen.filter((k) => { const e = document.querySelector(`.${k}`); return e !== null && e.getClientRects().length > 0; }),
            fokus: document.activeElement === document.body ? 'body' : document.activeElement.className,
            felder: fs.getClientRects().length > 0,
            adresse: location.search + location.hash,
            durchgelaufen: document.querySelector('.lp-formular').noValidate,   // lp.js ist nicht abgebrochen
            meldung: m ? m.innerText : '',
            text: document.body.innerText,                                    // alles, was man sieht
            bereits: document.querySelector('.lp-anmeldung-bereits') !== null,
          };
        }, MELDUNGEN);
        const fall = `${seite}${suche || ' ohne Parameter'}`;
        seitentext[zustand] = ist.text;
        pruefe(!ist.bereits, `${fall}: keine eigene Meldung „bereits angemeldet“ im Dokument`);
        if (klasse === 'lp-anmeldung-danke') {
          pruefe(ist.meldung.includes(ZUSATZ), `${fall}: Dankesmeldung mit Zusatzsatz („${ist.meldung}“)`);
        }
        pruefe(JSON.stringify(ist.sichtbar) === JSON.stringify(klasse ? [klasse] : []), `${fall}: sichtbare Meldung ${ist.sichtbar.join(', ') || 'keine'} (erwartet ${klasse || 'keine'})`);
        pruefe(ist.fokus === (klasse || 'body'), `${fall}: Fokus auf ${ist.fokus} (erwartet ${klasse || 'body'})`);
        pruefe(ist.felder === felder, `${fall}: Felder ${ist.felder ? 'sichtbar' : 'ausgeblendet'} (erwartet ${felder ? 'sichtbar' : 'ausgeblendet'})`);
        pruefe(ist.adresse === (klasse ? '' : suche), `${fall}: Adresse danach „${ist.adresse}“ (Teilen oder Neuladen zeigt wieder das Formular)`);
        pruefe(ist.durchgelaufen, `${fall}: lp.js läuft durch (Formularprüfung aktiv)`);
        if (klasse && ist.lage) {
          const { oben, unten, hoehe, seitenoben } = ist.lage;
          pruefe(seitenoben > hoehe, `${fall}: Meldung liegt unter der ersten Bildschirmhöhe (${seitenoben} px) — sonst prüft „im Bild“ nichts`);
          pruefe(oben >= 0 && unten <= hoehe, `${fall}: Meldung im sichtbaren Bereich (${oben}–${unten} px bei ${hoehe} px Höhe)`);
        }
        if (klasse) {
          // Seit 3.54.4 trägt die Danke-Meldung ihren Text in zwei Spannen (der <p> selbst hat keinen eigenen Textknoten mehr): gemessen werden die Spannen.
          const ziel = klasse === 'lp-anmeldung-danke' ? '.lp-anmeldung-danke-text, .lp-anmeldung-danke-nachsatz' : `.${klasse}`;
          const schwach = await page.evaluate(kontrastImBrowser, ziel);
          pruefe(schwach.length === 0, `${fall}: Kontrast der Meldung: ${schwach.join(' | ')}`);
        }
        await kontext.close();
      }
      pruefe(seitentext.ok === seitentext.bereits && seitentext.ok.includes(ZUSATZ), `${seite}: ok und bereits im sichtbaren Text nicht unterscheidbar`);
    }
  }

  // Plätze (3.52.0): mit Skript der Stand des Panels, bei voll ohne Felder; bei Drosselung, Netzfehler oder ohne Skript
  // der feste Text; der Rücksprung ?anmeldung=voll zeigt und fokussiert die Meldung.
  {
    const FEST = 'Begrenzt auf 30 Plätze.';
    const faelle = [
      ['frei', { status: 200, daten: { frei: 12, gesamt: 30, voll: false } }, 'Noch 12 von 30 Plätzen frei', true],
      ['voll', { status: 200, daten: { frei: 0, gesamt: 30, voll: true } }, 'Ausgebucht', false],
      ['gedrosselt', { status: 429, daten: { fehler: 'grenze' } }, FEST, true],
      ['Netzfehler', { abbruch: true }, FEST, true],
    ];
    for (const [fall, plaetze, text, felder] of faelle) {
      const { page, fremd, abfragen, kontext } = await oeffnen(browser, 'anmeldung--karte', 390, { plaetze });
      await page.waitForLoadState('networkidle');
      const ist = await page.evaluate(() => ({ text: document.querySelector('.lp-anmeldung-plaetze').textContent.trim(), felder: document.querySelector('.lp-formular fieldset').getClientRects().length > 0 }));
      pruefe(ist.text === text, `Plätze ${fall}: Text „${ist.text}“ (erwartet „${text}“)`);
      pruefe(ist.felder === felder, `Plätze ${fall}: Felder ${ist.felder ? 'sichtbar' : 'ausgeblendet'}`);
      pruefe(abfragen.length === 1 && abfragen[0] === 'https://beispiel.invalid/lp/plaetze?liste=beispiel-abend', `Plätze ${fall}: genau eine Abfrage mit der Liste (${abfragen.join(', ')})`);
      pruefe(fremd.length === 0, `Plätze ${fall}: keine Fremdanfrage (${fremd.join(', ')})`);
      await kontext.close();
    }
    {
      const { page, abfragen, kontext } = await oeffnen(browser, 'anmeldung--karte', 390, { ohneJs: true });
      const text = await page.evaluate(() => document.querySelector('.lp-anmeldung-plaetze').textContent.trim());
      pruefe(text === FEST && abfragen.length === 0, `Plätze ohne JavaScript: fester Text, keine Abfrage („${text}“, ${abfragen.length})`);
      await kontext.close();
    }
    {
      const { page, kontext } = await oeffnen(browser, 'anmeldung--karte', 390, { suche: '?anmeldung=voll#anmeldung', hoehe: 300, reducedMotion: 'reduce' });
      const ist = await page.evaluate(() => ({ sichtbar: !document.querySelector('.lp-anmeldung-voll').hidden, fokus: document.activeElement.className,
        felder: document.querySelector('.lp-formular fieldset').getClientRects().length > 0, adresse: location.search + location.hash }));
      pruefe(ist.sichtbar && ist.fokus === 'lp-anmeldung-voll' && !ist.felder && ist.adresse === '', `?anmeldung=voll: Meldung sichtbar und fokussiert, Felder weg, Adresse sauber (${JSON.stringify(ist)})`);
      const schwach = await page.evaluate(kontrastImBrowser, '.lp-anmeldung-voll');
      pruefe(schwach.length === 0, `?anmeldung=voll: Kontrast der Meldung: ${schwach.join(' | ')}`);
      await kontext.close();
    }
    // Rückmeldung und Plätze-Stand widersprechen sich nie: /lp/plaetze ist im Panel bis 60 s zwischengespeichert, /lp/anmelden
    // zählt live — beim letzten Platz kommt „voll“ zurück, während die Abfrage noch „1 frei“ meldet. Ohne Felder (ok, voll)
    // fragt die Seite nicht und zeigt keine Zahl; nach „fehler“ fragt sie, und ist die Liste voll, ersetzt „ausgebucht“ die
    // Aufforderung, es noch einmal zu versuchen.
    const RUECK = ['lp-anmeldung-danke', 'lp-anmeldung-fehler', 'lp-anmeldung-voll'];
    const rueckfaelle = [
      ['voll', { frei: 1, gesamt: 30, voll: false }, 'lp-anmeldung-voll', false, null, 0],
      ['ok', { frei: 0, gesamt: 30, voll: true }, 'lp-anmeldung-danke', false, null, 0],
      ['fehler', { frei: 0, gesamt: 30, voll: true }, 'lp-anmeldung-voll', false, null, 1],
      ['fehler', { frei: 12, gesamt: 30, voll: false }, 'lp-anmeldung-fehler', true, 'Noch 12 von 30 Plätzen frei', 1],
    ];
    for (const [zustand, daten, klasse, felder, anzeige, anzahl] of rueckfaelle) {
      const fall = `?anmeldung=${zustand} mit Stand ${JSON.stringify(daten)}`;
      const { page, abfragen, kontext } = await oeffnen(browser, 'anmeldung--karte', 390, { suche: `?anmeldung=${zustand}#anmeldung`, plaetze: { status: 200, daten } });
      await page.waitForLoadState('networkidle');
      const ist = await page.evaluate((klassen) => {
        const a = document.querySelector('.lp-anmeldung-plaetze');
        return {
          sichtbar: klassen.filter((k) => document.querySelector(`.${k}`).getClientRects().length > 0),
          fokus: document.activeElement.className,
          felder: document.querySelector('.lp-formular fieldset').getClientRects().length > 0,
          anzeige: a.getClientRects().length > 0 ? a.textContent.trim() : null,
        };
      }, RUECK);
      pruefe(JSON.stringify(ist.sichtbar) === JSON.stringify([klasse]) && ist.fokus === klasse, `${fall}: genau die Meldung ${klasse} sichtbar und fokussiert (${JSON.stringify(ist)})`);
      pruefe(ist.felder === felder, `${fall}: Felder ${ist.felder ? 'sichtbar' : 'ausgeblendet'}`);
      pruefe(ist.anzeige === anzeige, `${fall}: Plätze-Anzeige „${ist.anzeige}“ (erwartet ${anzeige === null ? 'keine' : `„${anzeige}“`})`);
      pruefe(abfragen.length === anzahl, `${fall}: ${abfragen.length} Abfrage(n) (erwartet ${anzahl})`);
      await kontext.close();
    }

    // Anmeldefrist abgelaufen (3.54.5): ?anmeldung=fehler&grund=geschlossen zeigt „geschlossen“ statt des Fehlertexts, ohne Felder und ohne Zahl.
    // Nur dieser eine Wert wirkt; jeder andere grund (und grund oder ?anmeldung=geschlossen allein) ändert nichts. Eine Seite aus einer älteren Marke
    // (ohne den Absatz) zeigt den bisherigen Fehlertext. Der Stand des Panels sagt überall „12 von 30 frei“ — ein Leck würde die Zahl sichtbar machen.
    const MELDUNGEN = [...RUECK, 'lp-anmeldung-geschlossen'];
    const ohneAbsatz = (html) => html.replace(/<p class="lp-anmeldung-geschlossen"[^>]*>.*?<\/p>/s, '');
    const frist = [
      // [Name, Suche, Umbau, sichtbare Meldung, Felder, Plätze-Anzeige, Abfragen, Adresse danach]
      ['geschlossen', '?anmeldung=fehler&grund=geschlossen#anmeldung', null, 'lp-anmeldung-geschlossen', false, null, 0, ''],
      ['anderer Wert', '?anmeldung=fehler&grund=quatsch#anmeldung', null, 'lp-anmeldung-fehler', true, 'Noch 12 von 30 Plätzen frei', 1, ''],
      ['Wert in Großschrift', '?anmeldung=fehler&grund=GESCHLOSSEN#anmeldung', null, 'lp-anmeldung-fehler', true, 'Noch 12 von 30 Plätzen frei', 1, ''],
      ['grund ohne fehler', '?anmeldung=voll&grund=geschlossen#anmeldung', null, 'lp-anmeldung-voll', false, null, 0, ''],
      ['?anmeldung=geschlossen allein', '?anmeldung=geschlossen#anmeldung', null, null, true, 'Noch 12 von 30 Plätzen frei', 1, '?anmeldung=geschlossen#anmeldung'],
      ['grund allein', '?grund=geschlossen#anmeldung', null, null, true, 'Noch 12 von 30 Plätzen frei', 1, '?grund=geschlossen#anmeldung'],
      ['Seite ohne den Absatz', '?anmeldung=fehler&grund=geschlossen#anmeldung', ohneAbsatz, 'lp-anmeldung-fehler', true, 'Noch 12 von 30 Plätzen frei', 1, ''],
    ];
    for (const [fall, suche, umbau, klasse, felder, anzeige, anzahl, adresse] of frist) {
      const { page, abfragen, kontext } = await oeffnen(browser, 'anmeldung--karte', 390, { suche, umschreiben: umbau || undefined, hoehe: 300, reducedMotion: 'reduce', plaetze: { status: 200, daten: { frei: 12, gesamt: 30, voll: false } } });
      await page.waitForLoadState('networkidle');
      const ist = await page.evaluate((klassen) => {
        const a = document.querySelector('.lp-anmeldung-plaetze');
        return {
          sichtbar: klassen.filter((k) => (document.querySelector(`.${k}`)?.getClientRects().length ?? 0) > 0),
          fokus: document.activeElement.className,
          felder: document.querySelector('.lp-formular fieldset').getClientRects().length > 0,
          anzeige: a.getClientRects().length > 0 ? a.textContent.trim() : null,
          adresse: location.search + location.hash,
        };
      }, MELDUNGEN);
      const name = `Frist ${fall} (${suche})`;
      pruefe(JSON.stringify(ist.sichtbar) === JSON.stringify(klasse ? [klasse] : []) && (klasse === null || ist.fokus === klasse), `${name}: sichtbar ${JSON.stringify(ist.sichtbar)}, Fokus ${ist.fokus}`);
      pruefe(ist.felder === felder, `${name}: Felder ${ist.felder ? 'sichtbar' : 'ausgeblendet'}`);
      pruefe(ist.anzeige === anzeige, `${name}: Plätze-Anzeige „${ist.anzeige}“ (erwartet ${anzeige === null ? 'keine' : `„${anzeige}“`})`);
      pruefe(abfragen.length === anzahl, `${name}: ${abfragen.length} Abfrage(n) (erwartet ${anzahl})`);
      pruefe(ist.adresse === adresse, `${name}: Adresse danach „${ist.adresse}“ (erwartet „${adresse}“)`);
      await kontext.close();
    }
    {
      // Optik wie „voll“ (eine CSS-Regel) und lesbar: Wortlaut, Kontrast, Umbruch bei Handybreite ohne waagrechtes Scrollen.
      const { page, kontext } = await oeffnen(browser, 'anmeldung--karte', 320, { suche: '?anmeldung=fehler&grund=geschlossen#anmeldung', hoehe: 300, reducedMotion: 'reduce' });
      const ist = await page.evaluate(() => {
        const stil = (el) => { const c = getComputedStyle(el); return ['borderTopWidth', 'borderTopColor', 'borderTopLeftRadius', 'paddingTop', 'paddingLeft', 'fontWeight', 'fontSize', 'color', 'marginTop'].map((k) => c[k]).join('|'); };
        const zu = document.querySelector('.lp-anmeldung-geschlossen'), voll = document.querySelector('.lp-anmeldung-voll');
        voll.hidden = false;
        const gleich = stil(zu) === stil(voll);
        voll.hidden = true;
        return { gleich, text: zu.textContent.trim(), breit: document.documentElement.scrollWidth > document.documentElement.clientWidth };
      });
      pruefe(ist.gleich, 'Frist geschlossen: gleiche Optik wie „voll“ (Rahmen, Abstand, Schrift, Farbe)');
      pruefe(ist.text === 'Die Anmeldung ist im Moment geschlossen. Das liegt nicht an deinen Angaben. Bitte melde dich direkt bei uns.', `Frist geschlossen: Wortlaut „${ist.text}“`);
      pruefe(!ist.breit, 'Frist geschlossen: bei 320 px kein waagrechtes Scrollen');
      const schwach = await page.evaluate(kontrastImBrowser, '.lp-anmeldung-geschlossen');
      pruefe(schwach.length === 0, `Frist geschlossen: Kontrast der Meldung: ${schwach.join(' | ')}`);
      await kontext.close();
    }
  }

  // Häufige Fragen per Tastatur
  {
    const { page, kontext } = await oeffnen(browser, 'faq--standard', 1280);
    await page.focus('.lp-faq-frage summary');
    await page.keyboard.press('Enter');
    pruefe(await page.evaluate(() => document.querySelector('.lp-faq-frage').open), 'Enter öffnet die Antwort');
    await page.keyboard.press('Space');
    pruefe(await page.evaluate(() => !document.querySelector('.lp-faq-frage').open), 'Leertaste schließt sie wieder');
    await kontext.close();
  }

  // Video: bis zum Klick keine Anfrage, danach genau youtube-nocookie
  {
    const { page, fremd, kontext } = await oeffnen(browser, 'video--breit', 1280);
    pruefe(fremd.length === 0 && await page.$('iframe') === null, 'vor dem Klick kein iframe und keine Anfrage');
    await page.click('.lp-video-start');
    await page.waitForSelector('.lp-video-rahmen iframe');
    const f = await page.evaluate(() => { const i = document.querySelector('.lp-video-rahmen iframe'); return { src: i.src, titel: i.title, fokus: document.activeElement === i }; });
    pruefe(f.src.startsWith('https://www.youtube-nocookie.com/embed/AbCdEfGhIjk'), `iframe lädt youtube-nocookie (${f.src})`);
    pruefe(f.titel === 'Kurz erklärt: Worum geht es?', 'iframe hat einen Titel');
    pruefe(f.fokus, 'Fokus wandert ins Video');
    await page.waitForTimeout(300);
    pruefe(fremd.length > 0 && fremd.every((u) => u.startsWith('https://www.youtube-nocookie.com/')), `nach dem Klick nur youtube-nocookie (${[...new Set(fremd.map((u) => new URL(u).host))]})`);
    await kontext.close();
  }

  // Kopfbereich mit Video (3.61.0): Startbild mit Play-Knopf, erst der Klick lädt youtube-nocookie, das Video sitzt 16:9 im Rahmen
  for (const breite of [1440, 390]) {
    const { page, fremd, kontext } = await oeffnen(browser, 'hero--bild-rechts', breite);
    pruefe(fremd.length === 0 && await page.$('iframe') === null, `Kopfbereich-Video (${breite}): vor dem Klick kein iframe und keine Anfrage`);
    const vor = await page.evaluate(() => {
      const r = document.querySelector('.lp-hero-medien .lp-video-rahmen').getBoundingClientRect();
      const k = document.querySelector('.lp-hero-medien .lp-video-start');
      return { b: r.width, h: r.height, rechts: r.right, links: r.left, breiteSeite: document.documentElement.scrollWidth, play: getComputedStyle(k, '::before').width, rund: getComputedStyle(k, '::before').borderTopLeftRadius,
        bild: document.querySelectorAll('img.lp-hero-bild').length, hinweis: document.querySelector('.lp-hero-medien .lp-video-link a').href };
    });
    pruefe(Math.abs(vor.b / vor.h - 16 / 9) < 0.02, `Kopfbereich-Video (${breite}): Rahmen 16:9 (${Math.round(vor.b)} × ${Math.round(vor.h)})`);
    pruefe(vor.links >= 0 && vor.rechts <= breite + 0.5 && vor.breiteSeite <= breite, `Kopfbereich-Video (${breite}): Rahmen im Fenster, kein Seitwärts-Scrollen (${Math.round(vor.links)}–${Math.round(vor.rechts)}, Seite ${vor.breiteSeite})`);
    pruefe(parseFloat(vor.play) >= 48 && parseFloat(vor.rund) > 0, `Kopfbereich-Video (${breite}): Play-Zeichen steht auf dem Startbild (${vor.play})`);
    pruefe(vor.bild === 0 && vor.hinweis === 'https://www.youtube.com/watch?v=AbCdEfGhIjk', 'Kopfbereich-Video: kein loses Bild daneben, Ersatzlink zu YouTube');
    await page.focus('.lp-hero-medien .lp-video-start');
    await page.keyboard.press('Enter');
    await page.waitForSelector('.lp-hero-medien .lp-video-rahmen iframe');
    const f = await page.evaluate(() => {
      const i = document.querySelector('.lp-hero-medien .lp-video-rahmen iframe');
      const r = document.querySelector('.lp-hero-medien .lp-video-rahmen').getBoundingClientRect();
      const q = i.getBoundingClientRect();
      return { src: i.src, titel: i.title, fokus: document.activeElement === i, b: q.width, h: q.height, dx: Math.abs(q.left - r.left) + Math.abs(q.right - r.right), dy: Math.abs(q.top - r.top) + Math.abs(q.bottom - r.bottom), knopf: document.querySelectorAll('.lp-video-start').length };
    });
    pruefe(f.src.startsWith('https://www.youtube-nocookie.com/embed/AbCdEfGhIjk'), `Kopfbereich-Video (${breite}): iframe lädt youtube-nocookie (${f.src})`);
    pruefe(f.titel === 'Gesund durch den Winter' && f.fokus && f.knopf === 0, `Kopfbereich-Video (${breite}): iframe mit Titel, Fokus im Video, Startknopf ersetzt`);
    pruefe(f.dx < 1 && f.dy < 1 && Math.abs(f.b / f.h - 16 / 9) < 0.02, `Kopfbereich-Video (${breite}): Video füllt den 16:9-Rahmen (${Math.round(f.b)} × ${Math.round(f.h)})`);
    await page.waitForTimeout(300);
    pruefe(fremd.length > 0 && fremd.every((u) => u.startsWith('https://www.youtube-nocookie.com/')), `Kopfbereich-Video (${breite}): nach dem Klick nur youtube-nocookie`);
    await kontext.close();
  }
  {
    const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 390, { ohneJs: true });
    const o = await page.evaluate(() => ({ knopf: document.querySelectorAll('.lp-video-start').length, link: document.querySelector('.lp-hero-medien .lp-video-link a').href, sichtbar: document.querySelector('.lp-hero-medien .lp-video-link a').getBoundingClientRect().height > 0 }));
    pruefe(o.knopf === 1 && o.link === 'https://www.youtube.com/watch?v=AbCdEfGhIjk' && o.sichtbar, 'Kopfbereich-Video ohne JavaScript: Link zu YouTube bleibt sichtbar');
    await kontext.close();
  }

  // Eigene Videodatei (3.62.0): Startbild und Bedienleiste, kein Autoplay, vor dem Start keine Anfrage an die Datei, 16:9 im Rahmen, kein Seitwärts-Scrollen
  // (Playwrights Chromium spielt kein H.264 ab — geprüft wird deshalb der Aufbau, nicht das Abspielen)
  for (const [name, breite] of [['hero--bild-rechts--video-datei', 1440], ['hero--bild-rechts--video-datei', 390], ['video--breit--datei', 1440], ['video--breit--datei', 390]]) {
    const { page, fremd, kontext, angefragt } = await oeffnen(browser, name, breite);
    const id = `Videodatei ${name} (${breite})`;
    await page.waitForLoadState('load');
    const v = await page.evaluate(() => {
      const e = document.querySelector('video.lp-video-datei');
      const r = e.getBoundingClientRect();
      const rahmen = e.parentElement.getBoundingClientRect();
      const link = document.querySelector('.lp-video-link a');
      return { controls: e.controls, autoplay: e.autoplay, muted: e.defaultMuted, loop: e.loop, paused: e.paused, preload: e.getAttribute('preload'), poster: e.getAttribute('poster'), aria: e.getAttribute('aria-label'), quelle: e.querySelector('source').getAttribute('src'),
        b: r.width, h: r.height, dx: Math.abs(r.left - rahmen.left) + Math.abs(r.right - rahmen.right), links: r.left, rechts: r.right, seite: document.documentElement.scrollWidth, fit: getComputedStyle(e).objectFit,
        iframes: document.querySelectorAll('iframe').length, startknoepfe: document.querySelectorAll('.lp-video-start').length, link: link ? link.getAttribute('href') : '', linkH: link ? link.getBoundingClientRect().height : 0 };
    });
    pruefe(v.controls && !v.autoplay && !v.muted && !v.loop && v.paused && v.preload === 'none', `${id}: Bedienleiste da, kein Autoplay, kein muted, kein loop, pausiert, preload none (${JSON.stringify([v.controls, v.autoplay, v.muted, v.loop, v.paused, v.preload])})`);
    pruefe(angefragt.some((p) => p.endsWith(v.poster)) && !angefragt.some((p) => p.endsWith('.mp4')), `${id}: Startbild geladen, vor dem Start keine Anfrage an die Videodatei (${angefragt.filter((p) => /\.(mp4|webp)$/.test(p))})`);
    pruefe(Math.abs(v.b / v.h - 16 / 9) < 0.02 && v.dx < 1 && v.fit === 'contain', `${id}: füllt den 16:9-Rahmen, object-fit contain (${Math.round(v.b)} × ${Math.round(v.h)}, ${v.fit})`);
    pruefe(v.links >= 0 && v.rechts <= breite + 0.5 && v.seite <= breite, `${id}: im Fenster, kein Seitwärts-Scrollen (${Math.round(v.links)}–${Math.round(v.rechts)}, Seite ${v.seite})`);
    pruefe(/^vid\/eigen-[0-9a-f]{16}\.mp4$/.test(v.quelle) && v.link === v.quelle && v.linkH > 0 && v.aria.startsWith('Video: '), `${id}: Quelle = Ersatzlink, sichtbar, Textalternative (${v.quelle}, ${v.aria})`);
    pruefe(v.iframes === 0 && v.startknoepfe === 0 && fremd.length === 0, `${id}: kein iframe, kein Startknopf, keine Fremdanfrage (${fremd})`);
    await page.keyboard.press('Tab');
    await page.focus('video.lp-video-datei');
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    const fokus = await page.evaluate(() => { const e = document.querySelector('video.lp-video-datei'); const s = getComputedStyle(e); return { auf: document.activeElement === e, breite: parseFloat(s.outlineWidth), art: s.outlineStyle }; });
    pruefe(fokus.auf && fokus.breite >= 2 && fokus.art !== 'none', `${id}: per Tastatur erreichbar, sichtbarer Fokusring (${JSON.stringify(fokus)})`);
    await kontext.close();
  }

  // Termine mit gestellter Uhr: vergangene ausgeblendet, ohne Termin der Ersatztext
  {
    let { page, kontext } = await oeffnen(browser, 'termin--karte', 1280, { uhr: '2099-11-01T12:00:00+01:00' });
    const zwischen = await page.evaluate(() => ({ sichtbar: [...document.querySelectorAll('.lp-termin-eintrag')].filter((e) => !e.hidden).length, leer: document.querySelector('.lp-termin-leer').hidden }));
    pruefe(zwischen.sichtbar === 1 && zwischen.leer, `ein vergangener Termin ausgeblendet (${JSON.stringify(zwischen)})`);
    await kontext.close();
    ({ page, kontext } = await oeffnen(browser, 'termin--karte', 1280, { uhr: '2100-01-01T00:00:00+01:00' }));
    const danach = await page.evaluate(() => ({ leer: !document.querySelector('.lp-termin-leer').hidden, liste: document.querySelector('.lp-termin-liste').hidden, ort: document.querySelector('.lp-termin-ort').hidden, preis: document.querySelector('.lp-termin-preis').hidden }));
    pruefe(danach.leer && danach.liste && danach.ort && danach.preis, `alle vorbei: Ersatztext statt Liste, Ort und Preis (${JSON.stringify(danach)})`);
    await kontext.close();
  }

  // Statistik (3.56.0): Beacon an /lp/treffer — nur die Ereignisse des Vertrags, anonym, ohne Cookies und ohne Web-Speicher
  {
    const ausfuellen = async (page) => {
      await page.fill('#lp-f-vorname', 'Anna');
      await page.fill('#lp-f-email', 'anna@beispiel.invalid');
      await page.check('#lp-f-einwilligung');
    };
    const warten = (page) => page.waitForTimeout(300);
    const art = (b) => b.map((x) => JSON.parse(x.text).art);
    const gehen = (page) => page.evaluate(() => { window.dispatchEvent(new PageTransitionEvent('pagehide', { persisted: false })); });

    // Ablauf: aufruf beim Laden, formular beim ersten Fokus (nur einmal), absenden nur bei echtem Absenden (nur einmal)
    {
      const { page, fremd, kontext, beacons, anmeldungen } = await oeffnen(browser, 'anmeldung--karte', 390, { anmelden: true, init: [SPION], suche: '?utm_source=Insta%20gram%3Cb%3E&utm_medium=social&utm_campaign=' + 'a'.repeat(100) });
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf', `Statistik: beim Laden genau ein aufruf (${art(beacons)})`);
      const liste = await page.evaluate(() => document.querySelector('input[name="liste"]').value);
      const a = beacons[0] ? JSON.parse(beacons[0].text) : {};
      pruefe(a.v === 2 && a.liste === liste && a.liste !== '' && a.pfad === '/anmeldung--karte/' && a.ref === '' && a.breite === 390, `Statistik: Felder des aufruf (${beacons[0] && beacons[0].text})`);
      pruefe(a.utm && a.utm.quelle === 'Insta gramb' && a.utm.medium === 'social' && a.utm.kampagne === 'a'.repeat(60), `Statistik: UTM bereinigt und auf 60 Zeichen gekürzt (${JSON.stringify(a.utm)})`);
      pruefe(Object.keys(a).sort().join() === 'art,breite,liste,pfad,ref,utm,v' && Object.keys(a.utm || {}).sort().join() === 'kampagne,medium,quelle', 'Statistik: genau die Felder des Vertrags');
      pruefe(beacons[0] && beacons[0].adresse === 'https://beispiel.invalid/treffer', `Statistik: Adresse aus dem Formularziel abgeleitet (${beacons[0] && beacons[0].adresse})`);
      pruefe(beacons[0] && beacons[0].methode === 'POST' && beacons[0].typ.startsWith('text/plain'), `Statistik: POST als text/plain, einfache Anfrage ohne Preflight (${beacons[0] && beacons[0].typ})`);
      pruefe(beacons.every((b) => b.text.length < 1000 && Object.values(JSON.parse(b.text)).every((w) => JSON.stringify(w).length < 400)), 'Statistik: Nachricht unter 1 KB, jedes Feld unter 400 Zeichen');

      await page.evaluate(() => document.getElementById('lp-f-website').focus());
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf', `Statistik: Fokus im Lockfeld zählt nicht als formular (${art(beacons)})`);
      await page.focus('#lp-f-vorname');
      await page.focus('#lp-f-email');
      await page.click('#lp-f-einwilligung');
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf,formular', `Statistik: formular einmal beim ersten Fokus (${art(beacons)})`);

      await page.click('.lp-formular button[type="submit"]');
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf,formular', `Statistik: ein Absenden, das die Prüfung abweist, ist noch kein absenden (${art(beacons)})`);
      await ausfuellen(page);
      let vorher = anmeldungen.length;
      await page.click('.lp-formular button[type="submit"]');
      await warten(page);
      await page.click('.lp-formular button[type="submit"]');
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf,formular,absenden', `Statistik: absenden genau einmal (${art(beacons)})`);
      pruefe(anmeldungen.length - vorher === 2 && anmeldungen.every((m) => m === 'POST'), `Statistik: das vorhandene Absenden läuft unverändert (${anmeldungen})`);

      const spur = await page.evaluate(() => ({ zugriffe: window.__zugriffe.slice(), cookie: document.cookie }));
      pruefe(spur.zugriffe.length === 0 && spur.cookie === '' && (await kontext.cookies()).length === 0, `Statistik: keine Cookies, kein Web-Speicher, kein IndexedDB (${spur.zugriffe})`);
      await page.evaluate(() => { localStorage.getItem('probe'); });
      pruefe((await page.evaluate(() => window.__zugriffe.length)) > 0, 'Statistik: der Speicher-Spion schlägt an (Gegenprobe der Messung)');
      pruefe(fremd.length === 0, `Statistik: keine Fremdanfrage (${fremd.join(', ')})`);
      await kontext.close();
    }

    // Im Panel heißt das Ziel …/lp/anmelden: daraus wird …/lp/treffer (gleicher Host)
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { umschreiben: (h) => h.replace('beispiel.invalid/anmelden"', 'beispiel.invalid/lp/anmelden"') });
      await warten(page);
      pruefe(beacons.length === 1 && beacons[0].adresse === 'https://beispiel.invalid/lp/treffer', `Statistik: /lp/anmelden → /lp/treffer (${beacons.map((b) => b.adresse)})`);
      await kontext.close();
    }

    // Verweiser nur als Host (kein Pfad, keine Anfrage), der eigene Host zählt als direkt
    for (const [verweiser, erwartet] of [['https://verweis.beispiel.invalid/p/AbC/?x=1&y=2#z', 'verweis.beispiel.invalid'], ['http://lp.test/anderes/?x=1', ''], ['', '']]) {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [VERWEISER(verweiser)] });
      await warten(page);
      const ref = beacons[0] ? JSON.parse(beacons[0].text).ref : null;
      pruefe(ref === erwartet && !(beacons[0] && beacons[0].text.includes('AbC')), `Statistik: Verweiser „${verweiser}“ → „${erwartet}“ (${ref})`);
      await kontext.close();
    }

    // Vorab-Laden: unsichtbar kein Beacon, sobald sichtbar genau einer (auch bei mehrfachem Wechsel)
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [UNSICHTBAR] });
      await warten(page);
      pruefe(beacons.length === 0, `Statistik: solange die Seite unsichtbar ist, nichts (${beacons.length})`);
      await page.evaluate(() => { window.__zeigen(); window.__zeigen(); });
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf', `Statistik: sichtbar → genau ein aufruf (${art(beacons)})`);
      await kontext.close();
    }

    // Verzicht: Do Not Track, Global Privacy Control, Abmeldung am body, Seite ohne Formular, Formular ohne Statistik-Ziel
    const ohneAction = (html) => html.replace(/ action="[^"]*"/, '');
    const fremdesZiel = (html) => html.replace(/\/anmelden"/, '/andere/stelle"');
    for (const [was, name, optionen] of [
      ['Do Not Track', 'anmeldung--karte', { init: [DNT] }],
      ['Global Privacy Control', 'anmeldung--karte', { init: [GPC] }],
      ['data-lp-statistik="aus"', 'anmeldung--karte', { umschreiben: (h) => h.replace('<body class="lp-seite"', '<body class="lp-seite" data-lp-statistik="aus"') }],
      ['Seite ohne Anmeldeformular', 'faq--standard', {}],
      ['Formular ohne Ziel', 'anmeldung--karte', { umschreiben: ohneAction }],
      ['Formular mit fremdem Ziel (nicht …/anmelden)', 'anmeldung--karte', { umschreiben: fremdesZiel }],
    ]) {
      const { page, fremd, kontext, beacons } = await oeffnen(browser, name, 390, optionen);
      if (name === 'anmeldung--karte') {
        await page.focus('#lp-f-vorname');
        await ausfuellen(page);
        await page.evaluate(() => document.querySelector('.lp-formular').addEventListener('submit', (e) => e.preventDefault()));
        await page.click('.lp-formular button[type="submit"]');
      }
      await gehen(page);
      await warten(page);
      pruefe(beacons.length === 0, `Statistik: ${was} → kein Beacon, auch kein verlassen (${beacons.length})`);
      pruefe(fremd.length === 0, `Statistik: ${was} → auch keine Anfrage an eine andere Adresse (${fremd.join(', ')})`);
      await kontext.close();
    }

    // Ein werfender sendBeacon darf weder Fehler in der Seite noch das Absenden stören
    for (const ab of [0, 1]) {
      const { page, kontext, anmeldungen } = await oeffnen(browser, 'anmeldung--karte', 390, { anmelden: true, init: [WERFENDER_BEACON(ab)] });
      const fehler = [];
      page.on('pageerror', (e) => fehler.push(e.message));
      await page.focus('#lp-f-vorname');
      await ausfuellen(page);
      const abgebrochen = await page.evaluate(() => new Promise((ok) => {
        document.addEventListener('submit', (e) => setTimeout(() => ok(e.defaultPrevented), 0));
        document.querySelector('.lp-formular button[type="submit"]').click();
      }));
      await gehen(page);
      await warten(page);
      pruefe(abgebrochen === false && anmeldungen.length === 1, `Statistik: sendBeacon wirft ab Aufruf ${ab + 1} — das Absenden läuft trotzdem (abgebrochen: ${abgebrochen}, Anmeldungen: ${anmeldungen.length})`);
      pruefe(fehler.length === 0, `Statistik: sendBeacon wirft ab Aufruf ${ab + 1} — kein Fehler in der Seite (${fehler.join(' | ')})`);
      await kontext.close();
    }

    // Rückkehr von der Weiterleitungsstelle (?anmeldung=ok): die Seite wurde schon gezählt, kein zweiter aufruf
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { suche: '?anmeldung=ok#anmeldung' });
      await warten(page);
      await gehen(page);
      await warten(page);
      pruefe(beacons.length === 0, `Statistik: Rückkehr nach der Anmeldung zählt keinen zweiten aufruf und kein verlassen (${beacons.length})`);
      await kontext.close();
    }

    // Zusammenspiel (3.58.0): „geschlossen“ (3.54.5), zweites Häkchen (3.55.0) und Beacon (3.56.0/3.57.0) auf derselben Seite. anmeldung--karte trägt beide Häkchen.
    // Rückkehr mit grund=geschlossen: bleibt bei „geschlossen“, die Felder mit dem zweiten Häkchen sind weg, kein Beacon (die Seite ist schon gezählt), keine Plätze-Abfrage.
    // Seite mit Formular: aufruf, formular, beim Verlassen verlassen — aber kein absenden, solange nichts abgesendet wurde (auch nicht beim Versuch mit leeren Pflichtfeldern).
    {
      const { page, kontext, beacons, abfragen, fremd } = await oeffnen(browser, 'anmeldung--karte', 390, { suche: '?anmeldung=fehler&grund=geschlossen#anmeldung', hoehe: 300, reducedMotion: 'reduce' });
      await warten(page);
      await gehen(page);
      await warten(page);
      const ist = await page.evaluate(() => ({
        sichtbar: ['lp-anmeldung-geschlossen', 'lp-anmeldung-fehler', 'lp-anmeldung-danke', 'lp-anmeldung-voll'].filter((k) => document.querySelector(`.${k}`).getClientRects().length > 0),
        fokus: document.activeElement.className,
        haken: document.querySelectorAll('#lp-f-newsletter').length,
        hakenSichtbar: document.querySelector('#lp-f-newsletter').getClientRects().length > 0,
        felder: document.querySelector('.lp-formular fieldset').getClientRects().length > 0,
      }));
      pruefe(JSON.stringify(ist.sichtbar) === '["lp-anmeldung-geschlossen"]' && ist.fokus === 'lp-anmeldung-geschlossen', `Zusammenspiel: mit zweitem Häkchen im Bauplan bleibt es bei „geschlossen“ (${ist.sichtbar}, Fokus ${ist.fokus})`);
      pruefe(ist.haken === 1 && !ist.hakenSichtbar && !ist.felder, `Zusammenspiel: das zweite Häkchen steht im Formular, ist aber mit den Feldern ausgeblendet (${ist.haken}, sichtbar ${ist.hakenSichtbar})`);
      pruefe(beacons.length === 0, `Zusammenspiel: Rückkehrseite „geschlossen“ sendet nichts, auch kein verlassen (${art(beacons)})`);
      pruefe(abfragen.length === 0 && fremd.length === 0, `Zusammenspiel: „geschlossen“ ohne Plätze-Abfrage und ohne Fremdanfrage (${abfragen.length}, ${fremd.join(', ')})`);
      await kontext.close();
    }
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, {});
      await warten(page);
      const vorher = await page.evaluate(() => ({ geschlossen: document.querySelector('.lp-anmeldung-geschlossen').getClientRects().length > 0, felder: document.querySelector('.lp-formular fieldset').getClientRects().length > 0 }));
      await page.check('#lp-f-newsletter');
      await page.click('.lp-formular button[type="submit"]');
      await gehen(page);
      await warten(page);
      pruefe(!vorher.geschlossen && vorher.felder, 'Zusammenspiel: ohne Rückmeldung zeigt die Seite das Formular, „geschlossen“ bleibt versteckt');
      pruefe(art(beacons).join() === 'aufruf,formular,verlassen', `Zusammenspiel: zweites Häkchen angehakt, Absenden an leeren Pflichtfeldern gescheitert → aufruf, formular, verlassen, kein absenden (${art(beacons)})`);
      await kontext.close();
    }

    // Verlassen (3.57.0, mehrfach seit 3.65.0): bei jedem Verstecken eine Meldung mit der sichtbaren Zeit seit dem letzten Senden (ganze Sekunden, mindestens 1, zusammen höchstens 3600) und der weitesten Scrolltiefe
    const verlassene = (b) => b.map((x) => JSON.parse(x.text)).filter((m) => m.art === 'verlassen');
    const TIEFEN = [0, 25, 50, 75, 100];

    // Rahmen wie bei den anderen Arten; pagehide und Verstecken des Tabs zusammen senden genau eine Meldung; kein Speicher
    {
      const { page, fremd, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [UNSICHTBAR, SPION], uhr: '2026-10-27T10:00:00', hoehe: 400, suche: '?utm_source=mail' });
      await warten(page);
      await page.evaluate(() => window.__zeigen());
      await page.clock.fastForward(12000);
      await gehen(page);
      await page.evaluate(() => window.__verstecken());
      await gehen(page);
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf,verlassen', `Verlassen: pagehide und Verstecken senden genau eine Meldung (${art(beacons)})`);
      const m = verlassene(beacons)[0] || {};
      const liste = await page.evaluate(() => document.querySelector('input[name="liste"]').value);
      pruefe(Object.keys(m).sort().join() === 'art,breite,dauer,liste,pfad,ref,tiefe,utm,v', `Verlassen: genau die Felder des Vertrags (${Object.keys(m).sort()})`);
      pruefe(m.v === 2 && m.liste === liste && m.liste !== '' && m.pfad === '/anmeldung--karte/' && m.ref === '' && m.breite === 390 && m.utm && m.utm.quelle === 'mail', `Verlassen: derselbe Rahmen wie aufruf (${beacons[1] && beacons[1].text})`);
      pruefe(Number.isInteger(m.dauer) && m.dauer >= 12 && m.dauer <= 14, `Verlassen: 12 Sekunden sichtbar → dauer 12 (${m.dauer})`);
      pruefe(TIEFEN.includes(m.tiefe), `Verlassen: tiefe aus 0|25|50|75|100 (${m.tiefe})`);
      pruefe(beacons[1] && beacons[1].adresse === 'https://beispiel.invalid/treffer' && beacons[1].methode === 'POST' && beacons[1].typ.startsWith('text/plain'), `Verlassen: POST als text/plain an dieselbe Adresse (${beacons[1] && beacons[1].adresse})`);
      pruefe(beacons.every((b) => b.text.length < 1000 && Object.values(JSON.parse(b.text)).every((w) => JSON.stringify(w).length < 400)), 'Verlassen: Nachricht unter 1 KB, jedes Feld unter 400 Zeichen');
      // Rückkehr aus dem Zurück-Zwischenspeicher: kein neuer Seitenaufruf (kein zweiter aufruf), aber die 5 Sekunden danach kommen als zweites verlassen (Delta, nicht 17)
      await page.evaluate(() => { window.__zeigen(); window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })); });
      await page.clock.fastForward(5000);
      await gehen(page);
      await page.evaluate(() => window.__verstecken());
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf,verlassen,verlassen', `Verlassen: Rückkehr aus dem Zwischenspeicher zählt keinen neuen aufruf, aber ein zweites verlassen (${art(beacons)})`);
      const zweites = verlassene(beacons)[1] || {};
      pruefe(zweites.dauer >= 5 && zweites.dauer <= 7 && zweites.v === 2, `Verlassen: das zweite verlassen trägt nur die 5 Sekunden seit dem letzten Senden (${zweites.dauer})`);
      const spur = await page.evaluate(() => ({ zugriffe: window.__zugriffe.slice(), cookie: document.cookie }));
      pruefe(spur.zugriffe.length === 0 && spur.cookie === '' && (await kontext.cookies()).length === 0, `Verlassen: keine Cookies, kein Web-Speicher, kein IndexedDB (${spur.zugriffe})`);
      await page.evaluate(() => { sessionStorage.getItem('probe'); });
      pruefe((await page.evaluate(() => window.__zugriffe.length)) > 0, 'Verlassen: der Speicher-Spion schlägt an (Gegenprobe der Messung)');
      pruefe(fremd.length === 0, `Verlassen: keine Fremdanfrage (${fremd.join(', ')})`);
      await kontext.close();
    }

    // Sichtbare Zeit statt Wanduhrzeit: was im Hintergrund-Tab vergeht, zählt nicht; später Wechsel ändert die gesendete Zeit nicht
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [UNSICHTBAR], uhr: '2026-10-27T10:00:00' });
      await warten(page);
      await page.clock.fastForward(20000);
      await page.evaluate(() => window.__zeigen());
      await page.clock.fastForward(7000);
      await page.evaluate(() => window.__verstecken());
      await warten(page);
      const m = verlassene(beacons)[0] || {};
      pruefe(m.dauer >= 7 && m.dauer <= 9, `Verlassen: 20 s im Hintergrund zählen nicht, 7 s sichtbar → dauer 7 (${m.dauer})`);
      await page.evaluate(() => window.__zeigen());
      await page.clock.fastForward(5000);
      await gehen(page);
      await page.evaluate(() => window.__verstecken());
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf,verlassen,verlassen', `Verlassen: nach dem Zurückkehren ein zweites verlassen, kein neuer aufruf, kein da (${art(beacons)})`);
      const d2 = (verlassene(beacons)[1] || {}).dauer;
      pruefe(d2 >= 5 && d2 <= 7, `Verlassen: das zweite verlassen zählt nur die 5 Sekunden seit dem letzten Senden (${d2})`);
      await kontext.close();
    }

    // „da“ (v2): nach über 60 s Abwesenheit meldet sich die zurückgekehrte Seite einmal — ohne Zahlen, ohne neuen aufruf; nach kürzerer Abwesenheit nicht
    for (const [weg, erwartet] of [[30000, 'aufruf,verlassen,verlassen'], [61000, 'aufruf,verlassen,da,verlassen']]) {
      const { page, kontext, beacons, fremd } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [UNSICHTBAR], uhr: '2026-10-30T10:00:00', hoehe: 400 });
      await warten(page);
      await page.evaluate(() => window.__zeigen());
      await page.clock.fastForward(4000);
      await page.evaluate(() => window.__verstecken());
      await page.clock.fastForward(weg);
      await page.evaluate(() => { window.__zeigen(); window.__zeigen(); });
      await page.clock.fastForward(3000);
      await page.evaluate(() => window.__verstecken());
      await warten(page);
      pruefe(art(beacons).join() === erwartet, `Da: ${weg / 1000} s weg → ${erwartet} (${art(beacons)})`);
      if (weg > 60000) {   // eine zweite lange Abwesenheit meldet sich wieder (da ist nicht „einmal je Seitenaufruf“)
        await page.clock.fastForward(62000);
        await page.evaluate(() => window.__zeigen());
        await warten(page);
        pruefe(art(beacons).join() === erwartet + ',da', `Da: nach einer zweiten Abwesenheit von 62 s ein zweites da (${art(beacons)})`);
      }
      const da = beacons.map((b) => JSON.parse(b.text)).find((m) => m.art === 'da');
      if (weg > 60000) {
        pruefe(da && da.v === 2 && !('dauer' in da) && !('tiefe' in da) && da.liste !== '' && da.pfad === '/anmeldung--karte/', `Da: Rahmen wie aufruf, ohne dauer und tiefe (${JSON.stringify(da)})`);
        const letztes = verlassene(beacons)[1] || {};
        pruefe(letztes.dauer >= 3 && letztes.dauer <= 5, `Da: das verlassen danach zählt nur die 3 Sekunden seit der Rückkehr (${letztes.dauer})`);
      }
      pruefe(beacons.every((b) => b.text.length <= 2048) && fremd.length === 0, 'Da: jede Nachricht höchstens 2 KB, keine Fremdanfrage');
      await kontext.close();
    }

    // Die Rückkehrseite einer Anmeldung (?anmeldung=…) zählt nichts: auch kein da, wenn man nach langer Pause zurückkommt
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [UNSICHTBAR], uhr: '2026-10-30T10:00:00', suche: '?anmeldung=ok' });
      await warten(page);
      await page.evaluate(() => window.__zeigen());
      await page.clock.fastForward(5000);
      await page.evaluate(() => window.__verstecken());
      await page.clock.fastForward(90000);
      await page.evaluate(() => window.__zeigen());
      await page.evaluate(() => window.__verstecken());
      await warten(page);
      pruefe(beacons.length === 0, `Da: die Rückkehrseite einer Anmeldung sendet nichts, auch kein da (${art(beacons)})`);
      await kontext.close();
    }

    // Nie sichtbar gewesen (Vorab-Laden, Hintergrund-Tab): kein aufruf, also auch kein verlassen
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [UNSICHTBAR] });
      await warten(page);
      await gehen(page);
      await page.evaluate(() => window.__verstecken());
      await warten(page);
      pruefe(beacons.length === 0, `Verlassen: nie sichtbar → nichts (${beacons.length})`);
      await kontext.close();
    }

    // Obergrenze 3600 Sekunden; sofortiges Verlassen ist 0
    for (const [verstrichen, bereich, was] of [[2 * 3600 * 1000, [3600, 3600], 'zwei Stunden → 3600'], [0, [0, 1], 'sofort → 0']]) {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [UNSICHTBAR], uhr: '2026-10-27T10:00:00' });
      await warten(page);
      await page.evaluate(() => window.__zeigen());
      if (verstrichen) { await page.clock.fastForward(verstrichen); }
      await gehen(page);
      await warten(page);
      const d = (verlassene(beacons)[0] || {}).dauer;
      pruefe(d >= bereich[0] && d <= bereich[1], `Verlassen: dauer ${was} (${d})`);
      await kontext.close();
    }

    // Scrolltiefe: unterer Rand des Sichtfensters im Verhältnis zur Seitenhöhe, gerundet auf 25 %; die weiteste Stelle bleibt, auch wenn man zurückscrollt
    for (const [anteil, zurueck, erwartet] of [[0, false, 0], [0.3, false, 25], [0.5, false, 50], [0.5, true, 50], [0.8, false, 75], [1, false, 100]]) {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { hoehe: 400 });
      await page.evaluate(() => { document.body.style.paddingBottom = '6000px'; });
      const hoehe = await page.evaluate(() => document.documentElement.scrollHeight);
      const nach = async (y) => { await page.evaluate((oben) => window.scrollTo({ top: oben, behavior: 'instant' }), y); await warten(page); };
      if (anteil > 0) { await nach(Math.max(0, anteil * hoehe - 400)); }
      if (zurueck) { await nach(0); }
      await gehen(page);
      await warten(page);
      const m = verlassene(beacons)[0] || {};
      pruefe(m.tiefe === erwartet, `Verlassen: ${anteil * 100} % der Seite gesehen${zurueck ? ', dann zurück nach oben' : ''} → tiefe ${erwartet} (${m.tiefe}, Seitenhöhe ${hoehe})`);
      await kontext.close();
    }

    // Eine Seite, die ganz ins Fenster passt, gilt als bis unten gesehen
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 1280, { hoehe: 20000 });
      await gehen(page);
      await warten(page);
      pruefe((verlassene(beacons)[0] || {}).tiefe === 100, `Verlassen: Seite passt ins Fenster → tiefe 100 (${(verlassene(beacons)[0] || {}).tiefe})`);
      await kontext.close();
    }

    // Der Scroll-Zähler bremst die Seite nicht: passiver Hörer, beim Scrollen keine Abfrage der Seitenhöhe (nur einmal beim Verlassen)
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, { init: [SCROLLSPION], hoehe: 400 });
      await page.evaluate(() => { document.body.style.paddingBottom = '6000px'; });
      await warten(page);
      const hoerer = await page.evaluate(() => window.__scrollHoerer.slice());
      pruefe(hoerer.length > 0 && hoerer.every((p) => p === true), `Verlassen: jeder Scroll-Hörer ist passiv (${hoerer})`);
      const vor = await page.evaluate(() => window.__hoehenLesen);
      for (let i = 1; i <= 15; i++) {
        await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), i * 200);
        await page.waitForTimeout(40);
      }
      const waehrend = await page.evaluate(() => window.__hoehenLesen);
      pruefe(waehrend === vor, `Verlassen: beim Scrollen keine Abfrage der Seitenhöhe (${vor} → ${waehrend})`);
      await gehen(page);
      await warten(page);
      const danach = await page.evaluate(() => window.__hoehenLesen);
      pruefe(danach - waehrend <= 1 && verlassene(beacons).length === 1, `Verlassen: beim Verlassen höchstens eine Abfrage der Seitenhöhe (${danach - waehrend})`);
      await kontext.close();
    }

    // Echtes Verlassen: Navigation zu einer anderen Seite sendet genau ein verlassen (pagehide und Verstecken feuern beide)
    {
      const { page, kontext, beacons } = await oeffnen(browser, 'anmeldung--karte', 390, {});
      await warten(page);
      await page.goto('http://lp.test/faq--standard/');
      for (let i = 0; i < 10 && !art(beacons).includes('verlassen'); i++) { await warten(page); } // unter Last kommt der Beacon später als 300 ms
      await warten(page);
      pruefe(art(beacons).join() === 'aufruf,verlassen', `Verlassen: Navigation weg von der Seite sendet genau ein verlassen (${art(beacons)})`);
      await kontext.close();
    }
  }

  // Titelgröße (3.59.0): Der Titel des Kopfbereichs wird mit der Textmenge kleiner — beim Tippen ohne Neuladen, aus den Schwellen im
  // Quelltext (nicht aus einer zweiten Liste im Skript), und ohne Skript aus der Stufe im HTML. Die Dachzeile darf zwei Zeilen füllen.
  {
    const wort = 'Gesünder leben ';
    const titelMit = (n) => wort.repeat(12).slice(0, n - 1) + 'x';
    const dachMit = (n) => 'Tier und Mensch: gesunde Ernährung = gesundes Leben – ein Blick hinter die Kulissen der Lebensmittelindustrie und ihrer Werbung'.slice(0, n).trimEnd();
    const messen = () => {
      const h = document.querySelector('.lp-hero-titel');
      const probe = document.createElement('div');
      probe.style.fontSize = 'var(--fs-xl)'; probe.style.position = 'absolute'; probe.style.visibility = 'hidden';
      document.body.append(probe);
      const unten = parseFloat(getComputedStyle(probe).fontSize);
      probe.remove();
      const st = getComputedStyle(h);
      return { px: parseFloat(st.fontSize), unten, stufe: h.dataset.lpGroesse, zeilen: Math.round(h.getBoundingClientRect().height / parseFloat(st.lineHeight)),
        ueberlauf: h.scrollWidth - h.clientWidth, seite: document.documentElement.scrollWidth - window.innerWidth };
    };
    for (const schema of ['light', 'dark']) {
      for (const breite of [1440, 1024, 390]) {
        const wo = `Titelgröße @${breite} ${schema}`;
        const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', breite);
        await page.evaluate((s) => { if (s === 'dark') { document.documentElement.dataset.theme = 'dark'; } }, schema); // dunkel ist data-theme="dark", nicht prefers-color-scheme
        const gemessen = {};
        for (const n of [20, 45, 80, 140]) {
          await page.evaluate((t) => { document.querySelector('.lp-hero-titel').textContent = t; }, titelMit(n));
          gemessen[n] = await page.evaluate(messen);
          pruefe(gemessen[n].ueberlauf <= 0 && gemessen[n].seite <= 0, `${wo}, ${n} Zeichen: Titel sprengt die Spalte oder die Seite (${gemessen[n].ueberlauf}/${gemessen[n].seite}px)`);
          pruefe(gemessen[n].px >= gemessen[n].unten - 0.01, `${wo}, ${n} Zeichen: ${gemessen[n].px}px unter der Untergrenze --fs-xl (${gemessen[n].unten}px)`);
          pruefe(gemessen[n].zeilen <= 3 || n === 140, `${wo}, ${n} Zeichen: ${gemessen[n].zeilen} Zeilen`);
        }
        pruefe(gemessen[20].stufe === '0' && gemessen[45].stufe === '1' && gemessen[80].stufe === '3' && gemessen[140].stufe === '3',
          `${wo}: Stufen beim Tippen ${[20, 45, 80, 140].map((n) => gemessen[n].stufe)} statt 0,1,3,3 (ohne Neuladen)`);
        pruefe(gemessen[20].px > gemessen[45].px && gemessen[45].px > gemessen[80].px, `${wo}: Schrift wird mit mehr Text kleiner (${[20, 45, 80].map((n) => gemessen[n].px.toFixed(1))})`);
        pruefe(gemessen[140].px === gemessen[80].px, `${wo}: nach der letzten Stufe nicht weiter kleiner`);
        const hell = await page.evaluate(kontrastImBrowser, '.lp-hero-titel');
        pruefe(hell.length === 0, `${wo}: Kontrast des Titels ${hell.join(' | ')}`);
        await kontext.close();
      }
    }
    // Echtes Tippen (Tastatur) statt Textwechsel: contenteditable wie im Bild bearbeiten; Escape-Weg: Text zurücksetzen zieht die Stufe zurück
    {
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 1280);
      const titel = page.locator('.lp-hero-titel');
      const vorher = await titel.textContent();
      await titel.evaluate((e) => { e.setAttribute('contenteditable', 'plaintext-only'); });
      await titel.click();
      await page.keyboard.press('Control+A');
      await page.keyboard.type(titelMit(60));
      pruefe(await titel.getAttribute('data-lp-groesse') === '2', 'Tippen: 60 Zeichen ergeben Stufe 2');
      await page.keyboard.press('Control+A'); await page.keyboard.press('Backspace'); await page.keyboard.type('Kurz');
      pruefe(await titel.getAttribute('data-lp-groesse') === '0', 'Tippen: nach dem Löschen wieder Stufe 0');
      await titel.evaluate((e, t) => { e.textContent = t; }, vorher);
      pruefe(await titel.getAttribute('data-lp-groesse') === '0', 'Zurücksetzen des Textes (Escape im Bild) hält die Stufe richtig');
      await kontext.close();
    }
    // Schwellen aus dem Quelltext: andere Schwellen im HTML ergeben andere Stufen — im Skript steht keine zweite Liste
    {
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 1280, { umschreiben: (h) => h.replace(/data-lp-stufen="[^"]*"/, 'data-lp-stufen="5 10 15"') });
      await page.evaluate((t) => { document.querySelector('.lp-hero-titel').textContent = t; }, titelMit(12));
      pruefe(await page.locator('.lp-hero-titel').getAttribute('data-lp-groesse') === '2', 'Schwellen "5 10 15" im Quelltext: 12 Zeichen ergeben Stufe 2');
      await kontext.close();
    }
    // Ohne Skript gilt die Stufe aus dem HTML (kein Flackern, keine Neuberechnung)
    {
      const lang = titelMit(80);
      const rechne = (h) => h.replace(/(<h1 class="lp-hero-titel"[^>]*?) data-lp-groesse="\d"([^>]*>)[^<]*/, `$1 data-lp-groesse="3"$2${lang}`);
      const mit = await oeffnen(browser, 'hero--bild-rechts', 1280, { umschreiben: rechne });
      const ohne = await oeffnen(browser, 'hero--bild-rechts', 1280, { umschreiben: rechne, ohneJs: true });
      const a = await mit.page.evaluate(messen);
      const b = await ohne.page.evaluate(messen);
      pruefe(a.stufe === '3' && b.stufe === '3' && a.px === b.px && b.px < 56, `ohne Skript steht Stufe 3 schon im Bild (${b.px}px, mit Skript ${a.px}px)`);
      await mit.kontext.close(); await ohne.kontext.close();
    }
    // Dachzeile: 30 Zeichen eine Zeile, 60 und 100 zwei (1440 px); schmaler mehr, aber ohne Überlauf; ausgewogener Umbruch
    for (const [breite, erwartet] of [[1440, { 30: [1, 1], 60: [2, 2], 100: [2, 2] }], [1024, { 30: [1, 1], 60: [2, 2], 100: [2, 3] }], [390, { 30: [1, 1], 60: [2, 3], 100: [3, 4] }]]) {
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', breite);
      for (const n of [30, 60, 100]) {
        await page.evaluate((t) => { document.querySelector('.lp-hero-dachzeile').textContent = t; }, dachMit(n));
        const m = await page.evaluate(() => {
          const d = document.querySelector('.lp-hero-dachzeile');
          const st = getComputedStyle(d);
          return { zeilen: Math.round(d.getBoundingClientRect().height / parseFloat(st.lineHeight)), umbruch: st.textWrapStyle || st.textWrap, seite: document.documentElement.scrollWidth - window.innerWidth, ueber: d.scrollWidth - d.clientWidth };
        });
        const [von, bis] = erwartet[n];
        pruefe(m.zeilen >= von && m.zeilen <= bis, `Dachzeile ${n} Zeichen @${breite}: ${m.zeilen} Zeilen statt ${von}–${bis}`);
        pruefe(m.seite <= 0 && m.ueber <= 0, `Dachzeile ${n} Zeichen @${breite}: Überlauf ${m.seite}/${m.ueber}px`);
        pruefe(m.umbruch === 'balance', `Dachzeile @${breite}: Umbruch ist ${m.umbruch}, nicht balance`);
      }
      await kontext.close();
    }
  }

  // Fokus nach dem Sprung zum Formular (3.59.0): Ein Klick auf einen Knopf zur Anmeldung setzt den Cursor ins erste Feld — nur dieser Klick,
  // nie beim Laden und nie nach einer Rückmeldung; der Honigtopf ist nie das erste Feld; die Statistik zählt den automatischen Fokus nicht.
  {
    const KNOPF = 'a.lp-knopf[href="#anmeldung"]';
    const fokusIm = (page, ms) => page.waitForFunction(() => document.activeElement && document.activeElement.id === 'lp-f-vorname', null, { timeout: ms }).then(() => true, () => false);
    const aktiv = (page) => page.evaluate(() => { const a = document.activeElement; return a ? `${a.tagName.toLowerCase()}#${a.id}.${a.className}` : ''; });
    const art = (b) => b.map((x) => JSON.parse(x.text).art).join();
    const ruhe = (page) => page.waitForTimeout(1500);
    const sichtbar = (page) => page.evaluate(() => { const r = document.getElementById('lp-f-vorname').getBoundingClientRect(); return { oben: Math.round(r.top), unten: Math.round(r.bottom), hoehe: window.innerHeight, y: Math.round(window.scrollY) }; });

    // Der Normalfall auf drei Kopfbereichen, mit Maus: Cursor im ersten Feld, Feld im Bild, Fokusring da, noch nichts gezählt
    for (const name of ['hero--bild-rechts', 'hero--bild-hinten', 'hero--schlicht']) {
      for (const breite of [1440, 390]) {
        const { page, kontext, beacons } = await oeffnen(browser, name, breite);
        await page.waitForTimeout(300);
        pruefe(!(await aktiv(page)).startsWith('input'), `Fokus ${name} @${breite}: beim Laden steht der Cursor noch in keinem Feld (${await aktiv(page)})`);
        await page.evaluate(() => { document.getElementById('lp-f-vorname').addEventListener('focus', () => { window.__fokusY = Math.round(window.scrollY); }); });
        await page.click(KNOPF);
        pruefe(await fokusIm(page, 3000), `Fokus ${name} @${breite}: nach dem Klick auf den Knopf steht der Cursor im ersten Feld (${await aktiv(page)})`);
        const s = await sichtbar(page);
        pruefe(s.y > 0 && s.oben >= 0 && s.unten <= s.hoehe, `Fokus ${name} @${breite}: das Feld liegt nach dem Sprung im Bild (${JSON.stringify(s)})`);
        const fokusY = await page.evaluate(() => window.__fokusY);
        pruefe(Math.abs(fokusY - s.y) <= 1, `Fokus ${name} @${breite}: der Fokus kommt erst, wenn der Sprung steht (beim Fokus ${fokusY}px, am Ende ${s.y}px)`);
        const ring = await page.evaluate(() => { const f = document.getElementById('lp-f-vorname'); return { an: f.matches(':focus-visible'), breite: parseFloat(getComputedStyle(f).outlineWidth), art: getComputedStyle(f).outlineStyle }; });
        pruefe(ring.an && ring.breite > 0 && ring.art !== 'none', `Fokus ${name} @${breite}: Fokusring sichtbar (${JSON.stringify(ring)})`);
        pruefe(await page.evaluate(() => document.querySelector('label[for="lp-f-vorname"]').textContent.trim().length > 0 && document.getElementById('lp-f-vorname').required), `Fokus ${name} @${breite}: das Feld hat seine Beschriftung`);
        await ruhe(page);
        pruefe(art(beacons) === 'aufruf', `Fokus ${name} @${breite}: der automatische Fokus zählt nicht als formular (${art(beacons)})`);
        await kontext.close();
      }
    }

    // Weniger Bewegung: kein sanftes Scrollen, der Fokus kommt sofort
    {
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 390, { reducedMotion: 'reduce' });
      await page.click(KNOPF);
      pruefe(await fokusIm(page, 400), 'Fokus bei „weniger Bewegung“: sofort im ersten Feld');
      const s = await sichtbar(page);
      pruefe(s.y > 0 && s.oben >= 0 && s.unten <= s.hoehe, `Fokus bei „weniger Bewegung“: Feld im Bild (${JSON.stringify(s)})`);
      await kontext.close();
    }

    // Tastatur: Enter auf dem Knopf wirkt wie ein Klick
    {
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 1440);
      await page.focus(KNOPF);
      await page.keyboard.press('Enter');
      pruefe(await fokusIm(page, 3000), 'Fokus: Enter auf dem Knopf setzt den Cursor ins erste Feld');
      await kontext.close();
    }

    // Nicht beim Laden mit Sprungmarke, nicht nach einer Rückmeldung; ohne Felder (ok) passiert auch beim Klick nichts
    for (const [suche, wasSteht] of [['#anmeldung', 'Adresse mit #anmeldung'], ['?anmeldung=fehler', 'Rückmeldung fehler'], ['?anmeldung=ok', 'Rückmeldung ok']]) {
      const { page, kontext, beacons } = await oeffnen(browser, 'hero--bild-rechts', 390, { suche });
      await ruhe(page);
      pruefe(!(await aktiv(page)).startsWith('input'), `Fokus ${wasSteht}: beim Laden kein Cursor in einem Feld (${await aktiv(page)})`);
      if (suche.startsWith('?anmeldung=')) {
        const meldungFokus = await page.evaluate(() => document.activeElement.matches('.lp-anmeldung-fehler, .lp-anmeldung-danke'));
        pruefe(meldungFokus, `Fokus ${wasSteht}: die Meldung behält den Fokus, wie bisher (${await aktiv(page)})`);
      }
      if (suche === '?anmeldung=ok') {
        await page.click(KNOPF);
        await ruhe(page);
        pruefe(!(await aktiv(page)).startsWith('input'), `Fokus ${wasSteht}: ohne Felder bleibt der Fokus auch nach dem Klick draußen (${await aktiv(page)})`);
      }
      pruefe(art(beacons).split(',').filter((a) => a === 'formular').length === 0, `Fokus ${wasSteht}: nichts als formular gezählt (${art(beacons)})`);
      await kontext.close();
    }

    // Der Honigtopf ist nie das erste Feld, auch wenn er im Quelltext vorn steht; ist kein Feld bedienbar (Fieldset gesperrt), passiert nichts
    {
      // Der Honigtopf und drei Attrappen (versteckt, gesperrt, ausgeblendet) stehen vor dem echten ersten Feld
      const ATTRAPPEN = '<input type="hidden" name="a1"><input name="a2" disabled><input name="a3" hidden>';
      const vorn = (h) => { const m = h.match(/<div class="form-hp"[\s\S]*?<\/div>\s*/); return h.replace(m[0], '').replace('<fieldset', ATTRAPPEN + m[0] + '<fieldset'); };
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 1440, { umschreiben: vorn });
      await page.click(KNOPF);
      pruefe(await fokusIm(page, 3000), `Fokus: Honigtopf, verstecktes, gesperrtes und ausgeblendetes Feld vor dem echten ersten Feld werden übersprungen (${await aktiv(page)})`);
      await kontext.close();
      const gesperrt = await oeffnen(browser, 'hero--bild-rechts', 1440, { umschreiben: (h) => vorn(h).replace('<fieldset ', '<fieldset disabled ') });
      await gesperrt.page.click(KNOPF);
      await ruhe(gesperrt.page);
      pruefe(!(await aktiv(gesperrt.page)).startsWith('input'), `Fokus: bei gesperrtem Fieldset nirgends hinein, auch nicht in den Honigtopf (${await aktiv(gesperrt.page)})`);
      await gesperrt.kontext.close();
    }

    // Andere Ziele und der Bearbeiten-Modus des Panels: ein abgefangener Klick (preventDefault in der Fangphase) und ein Link ohne Formularziel tun nichts
    {
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 1440);
      await page.evaluate(() => { document.addEventListener('click', (e) => { if (e.target.closest('a[href]')) { e.preventDefault(); } }, true); });
      await page.click(KNOPF);
      await ruhe(page);
      pruefe(!(await aktiv(page)).startsWith('input'), `Fokus: abgefangener Klick (Bearbeiten-Modus) setzt keinen Cursor (${await aktiv(page)})`);
      await kontext.close();
      const ohne = await oeffnen(browser, 'hero--bild-rechts', 1440);
      await ohne.page.evaluate(() => { document.body.insertAdjacentHTML('beforeend', '<a id="t-anders" href="#lp-a2-titel">Zur Überschrift</a>'); });
      await ohne.page.click('#t-anders');
      await ruhe(ohne.page);
      pruefe(!(await aktiv(ohne.page)).startsWith('input'), `Fokus: ein Link zu einer Überschrift (kein Formularziel) setzt keinen Cursor (${await aktiv(ohne.page)})`);
      await ohne.kontext.close();
    }

    // Nur der Anmeldeabschnitt selbst ist ein Formularziel: <main id="inhalt"> enthält das Formular, ist aber keins (WCAG 2.4.1: der Skip-Link
    // „Zum Inhalt springen“ darf den Cursor nicht ins Formular schicken); der Kopfabschnitt und ein Anker mitten im Formular ebenso nicht
    {
      const { page, kontext } = await oeffnen(browser, 'hero--bild-rechts', 1440);
      const SKIP = 'a.skip-link[href="#inhalt"]';
      pruefe(await page.locator(SKIP).count() === 1, 'Fokus/Skip-Link: der Seitenrahmen hat den Link „Zum Inhalt springen“');
      await page.focus(SKIP);
      await page.keyboard.press('Enter');
      await ruhe(page);
      pruefe(!(await aktiv(page)).startsWith('input'), `Fokus/Skip-Link: Enter auf „Zum Inhalt springen“ setzt keinen Cursor ins Formular (${await aktiv(page)})`);
      await page.evaluate(() => { document.activeElement.blur(); window.scrollTo(0, 0); });
      await page.evaluate(() => document.querySelector('a.skip-link').click());
      await ruhe(page);
      pruefe(!(await aktiv(page)).startsWith('input'), `Fokus/Skip-Link: auch ein Klick darauf setzt keinen Cursor ins Formular (${await aktiv(page)})`);
      await page.evaluate(() => { document.body.insertAdjacentHTML('beforeend', '<a id="t-start" href="#start">Zum Kopf</a><a id="t-feld" href="#lp-f-email">Zum E-Mail-Feld</a>'); });
      for (const [id, was] of [['#t-start', 'der Kopfabschnitt'], ['#t-feld', 'ein Anker auf ein Feld im Formular']]) {
        await page.evaluate(() => { document.activeElement.blur(); window.scrollTo(0, 0); });
        await page.click(id);
        await ruhe(page);
        pruefe(!(await aktiv(page)).startsWith('input#lp-f-vorname'), `Fokus: ${was} ist kein Formularziel, der Cursor springt nicht ins erste Feld (${await aktiv(page)})`);
      }
      await page.evaluate(() => { document.activeElement.blur(); window.scrollTo(0, 0); });
      await page.click(KNOPF);
      pruefe(await fokusIm(page, 3000), `Fokus: der Knopf zur Anmeldung wirkt weiter, auch nach den anderen Sprüngen (${await aktiv(page)})`);
      await kontext.close();
    }

    // Statistik: erst das erste echte Anklicken, Tippen oder Weiterspringen zählt als formular — genau einmal; auch hier fremd = 0
    for (const [wie, tun] of [['Tippen', (p) => p.keyboard.type('Anna')], ['Anklicken des Felds', (p) => p.click('#lp-f-vorname')], ['Tab ins nächste Feld', (p) => p.keyboard.press('Tab')]]) {
      const { page, kontext, beacons, fremd } = await oeffnen(browser, 'hero--bild-rechts', 1440);
      await page.click(KNOPF);
      await fokusIm(page, 3000);
      await ruhe(page);
      pruefe(art(beacons) === 'aufruf', `Fokus/Statistik (${wie}): vorher nur aufruf (${art(beacons)})`);
      await tun(page);
      await ruhe(page);
      pruefe(art(beacons) === 'aufruf,formular', `Fokus/Statistik (${wie}): danach ein formular (${art(beacons)})`);
      await page.keyboard.type('x'); await page.keyboard.press('Tab'); await page.click('#lp-f-vorname');
      await ruhe(page);
      pruefe(art(beacons) === 'aufruf,formular', `Fokus/Statistik (${wie}): weiteres Tippen, Springen und Anklicken zählt nicht noch einmal (${art(beacons)})`);
      pruefe(fremd.length === 0, `Fokus/Statistik (${wie}): keine Fremdanfrage (${fremd.join(', ')})`);
      await kontext.close();
    }
  }

  // Weniger Bewegung: keine Übergänge
  {
    const { page, kontext } = await oeffnen(browser, 'abschluss--akzent', 1280, { reducedMotion: 'reduce' });
    const dauer = await page.evaluate(() => getComputedStyle(document.querySelector('.lp-knopf')).transitionDuration.split(',').map((s) => parseFloat(s) * (s.includes('ms') ? 0.001 : 1)));
    pruefe(dauer.every((d) => d <= 0.001), `keine Übergänge bei „weniger Bewegung“ (${dauer})`);
    await kontext.close();
  }

  await browser.close();
  console.log('');
  if (fehler.length) {
    console.log(`FEHLER — ${fehler.length} Problem(e), ${bestanden} Prüfung(en) bestanden\n`);
    for (const f of fehler) { console.log(`  ✗ ${f}`); }
    console.log('');
    process.exit(1);
  }
  console.log(`ok — ${bestanden} Browserprüfungen bestanden\n`);
})().catch((e) => { console.error(e); process.exit(2); });
