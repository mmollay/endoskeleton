# Endoskeleton v3.50.1

Modulares Website-Template-System für alle generierten SSI-Websites.
Repo: github.com/mmollay/endoskeleton
Demo: skeleton.ssi.at

## Regeln

1. **Nur allgemeingültige Änderungen** — was ALLE Websites betrifft gehört hierher, website-spezifisches NICHT
2. **Nach jeder Änderung**: VERSION bumpen, CHANGELOG.md updaten (die Version steht nur in `VERSION`)
3. **Committen + Pushen**: `git add -A && git commit -m 'feat/fix: vX.Y.Z — Beschreibung' && git push origin main` — **Git nie als root ausführen** (sonst gehört `.git` root und pawbot kann nicht mehr committen)
4. **Testen**: skeleton.ssi.at im Browser prüfen (oder curl)
5. **Keine Breaking Changes** an bestehenden Preset-Namen — bestehende Websites nutzen diese!

## Architektur

```
base.css                → Grundstruktur (immer geladen)
layouts/NAME.css        → modern, classic, magazine
heroes/NAME.css         → minimal, split, fullscreen, banner, veil
colors/NAME.css         → 23 Farbprofile
buttons/NAME.css        → 8 Button-Stile
fonts/NAME.css          → 8 Font-Presets
softness/NAME.css       → 5 Charakter-Stufen (sanft bis kantig)
navlayouts/NAME.css     → 4 Navigations-Layouts
spacing/NAME.css        → compact, normal, spacious
animation/NAME.css      → none, subtle, dynamic
widths/NAME.css         → compact, default, wide, full
shared.js               → Demo-Controller + Nav-Behavior
statistik.js            → Besucherzähler für Webseiten (anonym, ans Panel; eine Einbindungszeile, siehe unten)
pages/SEITE.html        → Template-Seiten (index, kontakt, impressum, datenschutz, subpage)
demo.html               → nur noch Weiterleitung auf konfigurator.html
konfigurator.html       → Live-Preview aller Kombinationen (Sidebar-Konfigurator)
config.json             → Standard-Konfiguration
```

## Pipeline-Integration

SSI-Core pipeline.sh liest aus diesem Verzeichnis:
- ENDOSKELETON_DIR="/home/pawbot/projects/endoskeleton"
- Kopiert base.css, layouts, heroes, colors, buttons, fonts, softness, shared.js in neue Websites
- pages/SEITE.html dienen als Referenz-Templates für Claude

## Besucherzähler für Webseiten (statistik.js, seit 3.63.0; Vertrag v2 Teil 1 seit 3.65.0)

Zählt die Besuche einer Webseite anonym im Panel („Meine Webseite“ → Statistik): einen Aufruf je Seite, bei jedem Verlassen (Verstecken des Tabs, Seitenwechsel) die sichtbare Zeit seit dem letzten Senden
und die Scrolltiefe, nach über 60 Sekunden Abwesenheit ein „da“. Kein Cookie, kein Web-Speicher, keine Kennung des Besuchers. Vertrag `v: 2` — das Panel muss `v: 2` und `da` annehmen (ein Panel, das nur `v: 1`
kennt, verwirft jede v2-Nachricht), bevor `statistik.js` oder eine neu gebaute Landingpage ausgeliefert wird. Einbau: `statistik.js` neben die Seiten legen und **eine Zeile unmittelbar vor `</body>` jeder Seite** setzen — die Domain klein und ohne `www.`, genau wie im Panel eingetragen:

    <script src="statistik.js" data-quelle="web:beispiel.at" defer></script>

Gesendet wird nur auf der Domain selbst (oder `www.<Domain>`); Vorschau und lokale Kopien zählen nie. `<body data-statistik="aus">`, „Nicht verfolgen“ und Global Privacy Control schalten den Zähler ab.
Das Panel zählt nur, wenn der Schalter „Besucher zählen“ der Webseite an ist. Die Content-Security-Policy der Seite muss `connect-src https://panel.ssi.at` erlauben (die `.htaccess` dieses Repos tut das nicht —
nur wer den Zähler dort einsetzt, muss sie ergänzen). Ein Google Tag oder Cookie-Banner ersetzt der Zähler nicht; die Datenschutzerklärung der Webseite nennt ihn, sobald er live ist. Tests: `tests/statistik.test.php`,
Browser: `ci/browser-statistik.js`.

## Preset hinzufügen (Checkliste)

1. CSS-Datei in der richtigen Kategorie erstellen (z.B. colors/magenta.css)
2. konfigurator.html: Option im Dropdown hinzufügen
3. shared.js: Falls neue Kategorie → Logik erweitern
4. config.json: Default-Werte anpassen falls nötig
5. CHANGELOG.md updaten
6. VERSION bumpen
7. Committen + Pushen
