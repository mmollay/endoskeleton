#!/usr/bin/env node
/*
 * Browsertest des Bausteins modules/newsletter.js gegen die Testseite tests/fixtures/newsletter-seite.html.
 * Die Seite kommt über die Scheinadresse https://beispiel.test/ direkt aus dem Kit-Ordner (https, damit WebCrypto da ist);
 * die Antwort des Panels spielt der Test selbst: der POST an panel.ssi.at/lp/anmelden wird abgefangen (es geht NIE etwas
 * hinaus), geprüft und mit der Rückleitung auf die Seite beantwortet. Jede andere Anfrage wird abgebrochen und als fremd gemeldet.
 *
 *   ES_PLAYWRIGHT=/home/pawbot/projects/panel/node_modules/playwright node ci/browser-newsletter.js
 *
 * Kein npm install im Endoskeleton (Schadcode-Commit eb8a34d) — Playwright kommt von außen.
 */
'use strict';

const pw = require(process.env.ES_PLAYWRIGHT || '/home/pawbot/projects/panel/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const WURZEL = path.join(__dirname, '..');
const SEITE = '/tests/fixtures/newsletter-seite.html';
const SCHEIN = 'https://beispiel.test';
const ZIEL = 'https://panel.ssi.at/lp/anmelden';
const TEXT = 'Ich möchte den Newsletter von Beispiel erhalten und kann mich jederzeit abmelden.';
const TYPEN = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml' };
const probleme = [];
let pruefungen = 0;
const ok = (bedingung, was) => { pruefungen++; if (!bedingung) { probleme.push(was); } };

/* Dateien des Kits unter der Scheinadresse ausliefern; Pfade außerhalb des Kits und fehlende Dateien gibt es nicht. */
function datei(pfad) {
  const f = path.join(WURZEL, decodeURIComponent(pfad));
  return f.startsWith(WURZEL + path.sep) && fs.existsSync(f) && fs.statSync(f).isFile() ? f : null;
}

// Kontrast eines Elements gegen den Grund, auf dem es steht (Schichten der Vorfahren übereinander gerechnet).
const KONTRAST = (el) => {
  const rgb = (s) => {
    const z = (s.match(/[\d.]+/g) || []).map(Number);
    return s.startsWith('color(') ? [z[0] * 255, z[1] * 255, z[2] * 255, z[3] ?? 1] : [z[0], z[1], z[2], z[3] ?? 1];
  };
  let grund = [255, 255, 255];
  const schichten = [];
  for (let n = el; n; n = n.parentElement) { schichten.push(rgb(getComputedStyle(n).backgroundColor)); }
  for (const [r, g, b, a] of schichten.reverse()) { grund = [r * a + grund[0] * (1 - a), g * a + grund[1] * (1 - a), b * a + grund[2] * (1 - a)]; }
  const [r, g, b] = rgb(getComputedStyle(el).color);
  const hell = (c) => c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0);
  const [a1, a2] = [hell(grund), hell([r, g, b])];
  return (Math.max(a1, a2) + 0.05) / (Math.min(a1, a2) + 0.05);
};

async function laden(browser, o, abfrage = '') {
  const kontext = await browser.newContext({ viewport: { width: o.breite || 1280, height: 900 }, colorScheme: o.dunkel ? 'dark' : 'light' });
  const page = await kontext.newPage();
  const stand = { posts: [], fremd: [], konsole: [] };
  if (o.ohneCrypto) { await page.addInitScript(() => { Object.defineProperty(window, 'crypto', { value: {}, configurable: true }); }); }
  page.on('console', (m) => { if (m.type() === 'error') { stand.konsole.push(m.text()); } });
  await page.route('**/*', (r) => {
    const u = new URL(r.request().url());
    if (u.origin === SCHEIN) {
      const f = datei(u.pathname);
      return f ? r.fulfill({ status: 200, contentType: TYPEN[path.extname(f)] || 'application/octet-stream', body: fs.readFileSync(f) }) : r.fulfill({ status: 404, body: '' });
    }
    if (u.origin + u.pathname === ZIEL) {
      stand.posts.push({ methode: r.request().method(), typ: r.request().headers()['content-type'], daten: new URLSearchParams(r.request().postData() || '') });
      /* Das Panel antwortet mit 303; Chromium folgt einer von Playwright erzeugten Weiterleitung aber nicht über den Fänger.
         Die Attrappe schickt deshalb dieselbe Zieladresse als Navigation nach (der Baustein erlebt den gleichen Seitenaufruf). */
      const ziel = `${SCHEIN}${SEITE}?anmeldung=${o.antwort || 'ok'}#anmeldung`;
      return r.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: `<!doctype html><meta charset="utf-8"><script>location.replace(${JSON.stringify(ziel)})</script>` });
    }
    stand.fremd.push(u.href);
    return r.abort();
  });
  await page.goto(SCHEIN + SEITE + abfrage);
  return { page, kontext, stand };
}

async function haupt() {
  const browser = await pw.chromium.launch();
  const erwartet = crypto.createHash('sha256').update(TEXT, 'utf8').digest('hex').slice(0, 12);

  // 1. Aufbau, leeres Absenden, Tastatur, POST-Inhalt, Rückleitung ok (hell, 1280).
  {
    const { page, kontext, stand } = await laden(browser, {});
    await page.waitForSelector('#anmeldung form');
    ok(await page.locator('#anmeldung').count() === 1, 'genau ein Abschnitt #anmeldung');
    ok(await page.evaluate(() => document.getElementById('anmeldung').nextElementSibling === document.querySelector('#site-footer, .site-footer') || document.getElementById('anmeldung').nextElementSibling.matches('.site-footer')), 'Abschnitt steht direkt über dem Fuß');
    ok(await page.evaluate(() => [...document.querySelectorAll('#anmeldung input:not([type=hidden])')].every((i) => document.querySelector(`label[for="${i.id}"]`))), 'jedes Eingabefeld hat ein Label');
    ok(await page.locator('#anmeldung [style]').count() === 0, 'kein style-Attribut im Abschnitt');
    ok((await page.locator('#newsletter-titel').innerHTML()).includes('&amp;'), 'Titel mit & wird maskiert');
    ok(await page.locator('#anmeldung .section-lead b').count() === 0, 'Sonderzeichen in der Einleitung bleiben Text (kein Markup)');
    ok(await page.locator('.form-hp').getAttribute('aria-hidden') === 'true' && await page.locator('#newsletter-website').getAttribute('tabindex') === '-1', 'Honigtopf versteckt und nicht per Tab erreichbar');
    await page.waitForFunction(() => document.querySelector('[name=einwilligung_kurz]').value !== '');
    ok(await page.locator('[name=einwilligung_kurz]').inputValue() === erwartet, 'Kurzsumme = erste 12 Hex-Zeichen von SHA-256 des Einwilligungstexts (' + erwartet + ')');

    await page.click('#anmeldung button[type=submit]');   // leer absenden
    ok(stand.posts.length === 0, 'leeres Formular geht nicht hinaus');
    ok(await page.evaluate(() => ['vorname', 'email', 'einwilligung'].every((n) => document.getElementById('newsletter-' + n).getAttribute('aria-invalid') === 'true' && !document.getElementById('newsletter-' + n + '-fehler').hidden && document.getElementById('newsletter-' + n).getAttribute('aria-describedby') === 'newsletter-' + n + '-fehler')), 'drei Pflichtfelder: aria-invalid, Fehlertext sichtbar, aria-describedby');
    ok(await page.evaluate(() => document.activeElement.id) === 'newsletter-vorname', 'Fokus auf dem ersten Fehlerfeld');
    ok(await page.evaluate(() => document.getElementById('newsletter-nachname').getAttribute('aria-invalid') === null), 'freiwilliges Nachname-Feld ist nie ein Fehler');

    // Tastatur: nur Tab/Leertaste/Enter. Ungültige Adresse zuerst, dann berichtigen.
    await page.focus('#newsletter-vorname');
    await page.keyboard.type('Erika');
    await page.keyboard.press('Tab'); await page.keyboard.type('Muster');
    await page.keyboard.press('Tab'); await page.keyboard.type('keine-adresse');
    await page.keyboard.press('Tab'); await page.keyboard.press('Space');
    await page.keyboard.press('Tab');
    ok(await page.evaluate(() => document.activeElement.matches('a[href$="#newsletter"]')), 'Tab-Reihenfolge: Häkchen, dann Link zur Datenschutzerklärung');
    await page.keyboard.press('Tab'); await page.keyboard.press('Enter');
    ok(stand.posts.length === 0 && await page.evaluate(() => document.activeElement.id) === 'newsletter-email', 'ungültige E-Mail-Adresse: kein POST, Fokus im E-Mail-Feld');
    await page.fill('#newsletter-email', 'erika@example.test');
    await Promise.all([page.waitForURL(/anmeldung=ok|#anmeldung|\/newsletter-seite/), page.press('#newsletter-email', 'Enter')]);
    await page.waitForSelector('.newsletter-meldung');
    ok(stand.posts.length === 1, 'genau ein POST (war ' + stand.posts.length + ')');
    const p = stand.posts[0] || { daten: new URLSearchParams() };
    ok(p.methode === 'POST' && /^application\/x-www-form-urlencoded/.test(p.typ || ''), 'POST, normales Formular (urlencoded)');
    const felder = Object.fromEntries(p.daten);
    ok(JSON.stringify(Object.keys(felder).sort()) === JSON.stringify(['einwilligung', 'einwilligung_kurz', 'email', 'liste', 'nachname', 'vorname', 'website']), 'genau die erwarteten Felder: ' + Object.keys(felder).sort().join(','));
    ok(felder.vorname === 'Erika' && felder.nachname === 'Muster' && felder.email === 'erika@example.test' && felder.einwilligung === 'ja' && felder.website === '' && felder.liste === 'beispiel-newsletter' && felder.einwilligung_kurz === erwartet, 'Werte stimmen: ' + JSON.stringify(felder));
    ok(stand.fremd.length === 0, 'keine fremde Anfrage: ' + stand.fremd.join(' '));

    // Rückleitung ok: Meldung statt Felder, Fokus auf der Meldung, Adresse ohne Parameter.
    ok(await page.evaluate(() => document.activeElement.matches('.newsletter-meldung--ok[role=status][aria-live=polite]')), 'Rückleitung ok: Fokus auf der Meldung (role=status, aria-live)');
    ok(await page.isHidden('.newsletter-felder'), 'Rückleitung ok: Felder weg');
    const text = await page.locator('.newsletter-meldung').innerText();
    ok(text.includes('Vielen Dank') && text.includes('Falls Sie schon angemeldet waren, bekommen Sie keine neue E-Mail.'), 'Rückleitung ok: Dank und fester Zusatzsatz');
    ok(!page.url().includes('anmeldung') && !page.url().includes('#'), 'Parameter und Sprungmarke aus der Adresse entfernt: ' + page.url());
    await kontext.close();
  }

  // 2. Rückleitung fehler / bereits / unbekannt; ohne WebCrypto.
  for (const [wert, erwartetArt] of [['fehler', 'fehler'], ['bereits', 'ok'], ['voll', null], ['constructor', null]]) {
    const { page, kontext } = await laden(browser, {}, '?anmeldung=' + wert + '#anmeldung');
    await page.waitForSelector('#anmeldung form', { state: 'attached' });
    ok(await page.locator('.newsletter-meldung').count() === (erwartetArt ? 1 : 0), `?anmeldung=${wert}: ${erwartetArt ? 'Meldung ' + erwartetArt : 'keine Meldung'}`);
    if (erwartetArt === 'fehler') {
      const t = await page.locator('.newsletter-meldung').innerText();
      ok(await page.isVisible('.newsletter-felder') && t.includes('nicht geklappt') && t.includes('Falls Sie schon angemeldet waren'), 'fehler: Hinweis mit Zusatzsatz, Felder bleiben');
    }
    await kontext.close();
  }
  {
    const { page, kontext } = await laden(browser, { ohneCrypto: true });
    await page.waitForTimeout(600);
    ok(await page.locator('#anmeldung').count() === 0, 'ohne WebCrypto (kein https): kein Formular statt eines toten');
    await kontext.close();
  }

  // 3. Kontrast, Telefonbreite, Fokusring — hell und dunkel.
  for (const o of [{ dunkel: false, breite: 1280 }, { dunkel: true, breite: 1280 }, { dunkel: false, breite: 390 }, { dunkel: true, breite: 390 }]) {
    const name = `${o.dunkel ? 'dunkel' : 'hell'} ${o.breite}`;
    const { page, kontext } = await laden(browser, o);
    await page.waitForSelector('#anmeldung form');
    await page.click('#anmeldung button[type=submit]');   // Fehlertexte sichtbar machen
    const ziele = ['#newsletter-titel', '#anmeldung .section-lead', 'label[for=newsletter-vorname]', 'label[for=newsletter-einwilligung]', '#anmeldung label a', '#newsletter-vorname-fehler', '#newsletter-vorname', '#anmeldung .btn'];
    for (const z of ziele) { const k = await page.$eval(z, KONTRAST); ok(k >= 4.5, `Kontrast ${name} ${z}: ${k.toFixed(2)} (mind. 4,5)`); }
    ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `${name}: kein waagerechtes Scrollen`);
    ok(await page.evaluate(() => document.documentElement.dataset.theme === 'dark') === !!o.dunkel, `${name}: Farbschema stimmt`);
    ok(await page.$eval('#newsletter-vorname', (el) => getComputedStyle(el).outlineStyle !== 'none' && parseFloat(getComputedStyle(el).outlineWidth) >= 2), `${name}: sichtbarer Fokusring am Eingabefeld`);
    ok(await page.$eval('#anmeldung .btn', (el) => el.getBoundingClientRect().height >= 40), `${name}: Knopf mindestens 40 px hoch`);
    await kontext.close();
  }
  for (const dunkel of [false, true]) {
    const { page, kontext } = await laden(browser, { dunkel }, '?anmeldung=ok#anmeldung');
    await page.waitForSelector('.newsletter-meldung');
    for (const z of ['.newsletter-meldung p', '.newsletter-nachsatz']) { const k = await page.$eval(z, KONTRAST); ok(k >= 4.5, `Kontrast ${dunkel ? 'dunkel' : 'hell'} Meldung ${z}: ${k.toFixed(2)}`); }
    await kontext.close();
  }

  await browser.close();
  console.log(probleme.length ? `FEHLER — ${probleme.length} Problem(e), ${pruefungen - probleme.length} von ${pruefungen} Prüfungen bestanden\n  ✗ ${probleme.join('\n  ✗ ')}` : `ok — ${pruefungen} Prüfungen bestanden`);
  process.exit(probleme.length ? 1 : 0);
}

haupt().catch((fehler) => { console.error(fehler); process.exit(2); });
