# Download-Button im Konfigurator — Design

**Datum:** 2026-04-12
**Status:** Approved
**Sub-Projekt:** C (Fertige Website zum Download)
**Repo:** ssi-endoskeleton

## Problem

Der Konfigurator zeigt eine Live-Vorschau der gescannten Website in verschiedenen Presets. Aber der User kann das Ergebnis aktuell nicht mitnehmen — es gibt keinen "Fertig"-Knopf. Das gesamte System generiert zwar komplette Websites (via /endo CLI), aber nur programmgesteuert. Kein Browser-User kann eine Website exportieren.

## Ziel

Ein Knopf in der Konfigurator-Sidebar: "Website herunterladen". Klick generiert ein ZIP mit der kompletten Website (Home + Subpages + CSS/JS + Fonts), basierend auf dem aktuellen Preset und den Scan-Daten. Der ZIP landet im Downloads-Ordner des Browsers.

## Nicht-Ziele

- Kein Server-Side Speichern oder Account-System
- Kein automatisches Deployment (User muss ZIP selbst hochladen)
- Kein Cloud-Hosting-Angebot
- Keine Bearbeitungs-UI (Content wird 1:1 aus dem Scan genommen)
- Kein Impressum/Datenschutz-Textgenerator (Platzhalter bleiben)

## Architektur

```
User klickt "Website herunterladen"
    ↓
konfigurator.js: _buildContentFromScan(scanData, presetName)
    ↓  baut { company, pages[], contact, logo_url, sections[] }
    ↓
fetch("/api/v1/generate", { preset, content })
    ↓  SiteGenerator.php erzeugt alle HTML/CSS/JS
    ↓  packt ZIP, gibt download_url zurück
    ↓
Browser: window.location = download_url → ZIP-Download
```

Keine Backend-Änderungen nötig. SiteGenerator.php und generate.php existieren und funktionieren.

## Scan→Content Adapter

Die Adapter-Funktion `_buildContentFromScan(scanData, presetName)` mapped:

| Scan-Feld | Content-Feld |
|---|---|
| `scanData.title` oder `scanData.seo.title.text` oder `scanData.domain` | `content.company` |
| `scanData.logo.src` | `content.logo_url` |
| `scanData.contact.emails[0]` | `content.contact.email` |
| `scanData.contact.phones[0]` | `content.contact.phone` |
| `scanData.selected.about` | Homepage About-Section |
| `scanData.selected.services` | Homepage Services-Section |
| `scanData.hero_image.src` | Hero background_image |
| `scanData.colors` (erster nicht-weisser Hex) | `content.primary_color` |
| `scanData.pages[]` die nicht About/Services sind | Sub-Pages |
| `scanData.branch` | Hero eyebrow label |

Homepage (`slug: "index"`) bekommt Sections: hero, about, services.
Sub-Pages werden aus `scanData.pages[]` gebaut (jede bekommt eine einfache Text-Section).
Kontakt, Impressum, Datenschutz werden als eigene Pages angelegt.

## UI-Elemente

**Button** in der Konfigurator-Sidebar (unter den Preset-Buttons):
- Text: "Website herunterladen"
- Icon: Download-Pfeil (Unicode ↓ oder SVG)
- Zustand: disabled wenn kein Scan geladen, Loading-Spinner während Generate

**Loading-State:**
- Button-Text wird zu "Generiere..." mit Spinner
- Disabled während API-Call
- Bei Erfolg: Download startet, Button zurück auf Normal
- Bei Fehler: kurze Fehlermeldung, Button zurück auf Normal

## File Structure

```
~/code/ssi-endoskeleton/
├── js/konfigurator.js       MODIFY — _buildContentFromScan() + download handler
├── konfigurator.html        MODIFY — download button element
├── css/konfigurator.css     MODIFY — button styling + loading state
├── VERSION                  3.33.0 → 3.34.0
├── CHANGELOG.md             [3.34.0] entry
└── tests/smoke.sh           MODIFY — Test 8: download button exists in HTML
```

## Error-Handling

- Kein Scan geladen → Button ist disabled (grau)
- Generate-API gibt 429 (Rate-Limit) → Meldung "Bitte 1 Minute warten"
- Generate-API gibt 500 → Meldung "Fehler bei der Generierung"
- Netzwerkfehler → Meldung "Keine Verbindung zum Server"
- ZIP-URL 404 → Meldung "Download nicht verfügbar"

## Testing

**Smoke Test 8:** konfigurator.html enthält den Download-Button-Selektor
**Manueller Test:** Konfigurator mit obststadt.at öffnen, Preset wählen, Download klicken, ZIP prüfen

## Risiken

| Risiko | Mitigation |
|---|---|
| Sections-Format stimmt nicht mit SiteGenerator Erwartung überein | Exakter Code-Read von SiteGenerator.php, Testlauf gegen echte Domains |
| Rate-Limiting (max 10 Generates/h) blockiert Demo-Nutzung | Akzeptabel fuer Testing, spaeter Rate-Limit erhoehen |
| ZIP-Download blockiert durch Cloudflare | API-Pfad data/generated/ ist unter skeleton.ssi.at gehostet, CF leitet durch |
| Scan-Daten fehlen teilweise (kein selected, kein hero_image) | Defensive Checks: Fallback auf Domain-Name, Placeholder-Texte wenn noetig |

## Erfolgs-Kriterien

- [ ] Download-Button sichtbar im Konfigurator nach Scan-Load
- [ ] Klick auf Button startet ZIP-Download
- [ ] ZIP enthaelt index.html mit echtem Hero-Bild und About-Text
- [ ] ZIP enthaelt base.css + Preset-CSS-Module + shared.js
- [ ] Loading-State sichtbar waehrend Generate
- [ ] Error-State bei Netzwerkfehler
- [ ] Smoke Test 8 gruen
- [ ] Tag v3.34.0 gepusht
