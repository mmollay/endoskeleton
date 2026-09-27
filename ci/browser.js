#!/usr/bin/env node
/*
 * Browsertests der Landingpage-Bausteine gegen das gebaute Musterbuch (musterbuch/, neutrales Testprofil).
 * Die Seiten kommen über die Scheinadresse http://lp.test/ direkt aus dem Ordner (wie GRIDKit gk.test); jede andere
 * Anfrage wird abgebrochen und gezählt.
 *
 *   ES_PLAYWRIGHT=/home/pawbot/projects/panel/node_modules/playwright node ci/browser.js
 *
 * Kein npm install im Endoskeleton (Schadcode-Commit eb8a34d) — Playwright kommt von außen.
 */
'use strict';

const pw = require(process.env.ES_PLAYWRIGHT || '/home/pawbot/projects/panel/node_modules/playwright');
const fs = require('fs');
const path = require('path');

const MUSTERBUCH = path.join(__dirname, '..', 'musterbuch');
const BILDER = path.join(__dirname, 'bilder');
const BREITEN = [320, 360, 390, 1280];
const TYPEN = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.png': 'image/png', '.woff2': 'font/woff2' };

let bestanden = 0;
const fehler = [];
const pruefe = (bedingung, was) => { if (bedingung) { bestanden++; } else { fehler.push(was); } };

/** Öffnet eine Musterbuch-Seite (optionen.suche z. B. '?anmeldung=ok#anmeldung'); fremd sammelt alle Anfragen außerhalb von lp.test. */
async function oeffnen(browser, name, breite, optionen = {}) {
  const kontext = await browser.newContext({ viewport: { width: breite, height: 900 }, reducedMotion: optionen.reducedMotion || 'no-preference', javaScriptEnabled: optionen.ohneJs !== true });
  const page = await kontext.newPage();
  const fremd = [];
  await page.route('**/*', (r) => {
    const u = new URL(r.request().url());
    if (u.host !== 'lp.test') { fremd.push(u.href); return r.abort(); }
    let f = path.join(MUSTERBUCH, decodeURIComponent(u.pathname));
    if (f.endsWith(path.sep)) { f = path.join(f, 'index.html'); }
    if (!f.startsWith(MUSTERBUCH) || !fs.existsSync(f)) { return r.fulfill({ status: 404, body: 'fehlt' }); }
    return r.fulfill({ status: 200, body: fs.readFileSync(f), contentType: TYPEN[path.extname(f)] || 'application/octet-stream' });
  });
  if (optionen.uhr) { await page.clock.install({ time: new Date(optionen.uhr) }); }
  await page.goto(`http://lp.test/${name}${name ? '/' : ''}${optionen.suche || ''}`);
  return { page, fremd, kontext };
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

(async () => {
  const browser = await pw.chromium.launch();
  fs.mkdirSync(BILDER, { recursive: true });
  const seiten = fs.readdirSync(MUSTERBUCH, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name).sort();
  pruefe(seiten.length >= 16, `Musterbuch enthält alle Seiten (${seiten.length})`);

  // Jede Seite in jeder Breite: kein waagrechtes Scrollen, keine Fremdanfrage, Kontrast gemessen
  for (const name of ['', ...seiten]) {
    for (const breite of BREITEN) {
      const { page, fremd, kontext } = await oeffnen(browser, name, breite);
      const ueber = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      pruefe(ueber <= 0, `${name || 'Übersicht'} @${breite}: waagrecht ${ueber}px zu breit`);
      pruefe(fremd.length === 0, `${name || 'Übersicht'} @${breite}: Fremdanfragen ${fremd.join(', ')}`);
      const schwach = await page.evaluate(kontrastImBrowser);
      pruefe(schwach.length === 0, `${name || 'Übersicht'} @${breite}: zu wenig Kontrast: ${schwach.join(' | ')}`);
      if (name && (breite === 390 || breite === 1280)) { await page.screenshot({ path: path.join(BILDER, `${name}-${breite}.png`), fullPage: true }); }
      await kontext.close();
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

  // Rückmeldung der Weiterleitung (?anmeldung=…): passende Meldung sichtbar und fokussiert, Felder nur bei „fehler“;
  // unbekannte Werte und die Seite ohne Parameter bleiben wie bisher. Kontrast der sichtbaren Meldung gemessen.
  {
    const MELDUNGEN = ['lp-anmeldung-danke', 'lp-anmeldung-bereits', 'lp-anmeldung-fehler'];
    const faelle = [['ok', 'lp-anmeldung-danke', false], ['bereits', 'lp-anmeldung-bereits', false], ['fehler', 'lp-anmeldung-fehler', true],
      ['unsinn', null, true], ['constructor', null, true], [null, null, true]];
    for (const seite of ['anmeldung--karte', 'anmeldung--breit']) {
      for (const [zustand, klasse, felder] of faelle) {
        const suche = zustand === null ? '' : `?anmeldung=${zustand}#anmeldung`;
        const { page, kontext } = await oeffnen(browser, seite, 390, { suche });
        const ist = await page.evaluate((klassen) => {
          const fs = document.querySelector('.lp-formular fieldset');
          return {
            sichtbar: klassen.filter((k) => { const e = document.querySelector(`.${k}`); return e !== null && e.getClientRects().length > 0; }),
            fokus: document.activeElement === document.body ? 'body' : document.activeElement.className,
            felder: fs.getClientRects().length > 0,
            adresse: location.search + location.hash,
            durchgelaufen: document.querySelector('.lp-formular').noValidate,   // lp.js ist nicht abgebrochen
          };
        }, MELDUNGEN);
        const fall = `${seite}${suche || ' ohne Parameter'}`;
        pruefe(JSON.stringify(ist.sichtbar) === JSON.stringify(klasse ? [klasse] : []), `${fall}: sichtbare Meldung ${ist.sichtbar.join(', ') || 'keine'} (erwartet ${klasse || 'keine'})`);
        pruefe(ist.fokus === (klasse || 'body'), `${fall}: Fokus auf ${ist.fokus} (erwartet ${klasse || 'body'})`);
        pruefe(ist.felder === felder, `${fall}: Felder ${ist.felder ? 'sichtbar' : 'ausgeblendet'} (erwartet ${felder ? 'sichtbar' : 'ausgeblendet'})`);
        pruefe(ist.adresse === (klasse ? '' : suche), `${fall}: Adresse danach „${ist.adresse}“ (Teilen oder Neuladen zeigt wieder das Formular)`);
        pruefe(ist.durchgelaufen, `${fall}: lp.js läuft durch (Formularprüfung aktiv)`);
        if (klasse) {
          const schwach = await page.evaluate(kontrastImBrowser, `.${klasse}`);
          pruefe(schwach.length === 0, `${fall}: Kontrast der Meldung: ${schwach.join(' | ')}`);
        }
        await kontext.close();
      }
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

  // Termine mit gestellter Uhr: vergangene ausgeblendet, ohne Termin der Ersatztext
  {
    let { page, kontext } = await oeffnen(browser, 'termin--karte', 1280, { uhr: '2099-11-01T12:00:00+01:00' });
    const zwischen = await page.evaluate(() => ({ sichtbar: [...document.querySelectorAll('.lp-termin-eintrag')].filter((e) => !e.hidden).length, leer: document.querySelector('.lp-termin-leer').hidden }));
    pruefe(zwischen.sichtbar === 1 && zwischen.leer, `ein vergangener Termin ausgeblendet (${JSON.stringify(zwischen)})`);
    await kontext.close();
    ({ page, kontext } = await oeffnen(browser, 'termin--karte', 1280, { uhr: '2100-01-01T00:00:00+01:00' }));
    const danach = await page.evaluate(() => ({ leer: !document.querySelector('.lp-termin-leer').hidden, liste: document.querySelector('.lp-termin-liste').hidden, ort: document.querySelector('.lp-termin-ort').hidden }));
    pruefe(danach.leer && danach.liste && danach.ort, `alle vorbei: Ersatztext statt Liste (${JSON.stringify(danach)})`);
    await kontext.close();
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
