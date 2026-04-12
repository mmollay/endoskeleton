# KI-Refinement (Lynx) — Design Spec

**Datum:** 2026-04-12
**Projekt:** SSI Scanner + SSI Endoskeleton
**Scope:** KI-gestütztes Content-Refinement für gescannte Websites

---

## Zusammenfassung

Nach einem Website-Scan liefert der Scanner rohe Texte (Headlines, About-Texte, Service-Beschreibungen). Das KI-Refinement nimmt diese Rohdaten und erzeugt daraus professionelle, verkaufsstarke Webtexte. Fehlende Sektionen werden aus dem Kontext generiert, vorhandene Texte umgeschrieben und die Seitenstruktur optimiert.

## Datenfluss

```
Scanner-Scan → Roh-Content im Konfigurator
                    ↓
         User klickt "KI optimieren"
                    ↓
         POST scanner.ssi.at/api/v1/refine
                    ↓
         Scanner ruft Gemini auf:
         - Texte professionell umschreiben
         - Fehlende Sektionen generieren
         - Seitenstruktur vorschlagen
                    ↓
         Response: optimierter Content-JSON
                    ↓
         Konfigurator ersetzt Content via ContentInjector
                    ↓
         User sieht optimierte Version
         - Kann einzelne Sektionen re-generieren
         - Kann manuell editieren
```

## API-Endpoint

**Ort:** ssi-scanner (`scanner.ssi.at/api/v1/refine`)
**Grund:** Scanner hat bereits Gemini-Integration (analyze.py, API Key, Konfiguration)

### Request

```
POST /api/v1/refine
Content-Type: application/json
```

```json
{
  "domain": "beispiel.at",
  "scan_data": {
    "title": "Beispiel GmbH",
    "hero_text": { "headline": "...", "subtitle": "...", "eyebrow": "..." },
    "selected": {
      "about": { "title": "...", "text": "..." },
      "services": { "title": "...", "text": "..." }
    },
    "contact": { "emails": [], "phones": [], "cities": [] },
    "branch": "string",
    "ai_analysis": { "branch": "...", "target_audience": "...", "design_mood": "..." },
    "pages": [{ "url": "...", "title": "...", "text": "..." }],
    "seo": { "title": { "text": "..." }, "description": { "text": "..." } }
  },
  "sections": ["all"]
}
```

Das `sections`-Feld erlaubt spaeter gezieltes Re-Generieren einzelner Sektionen:
- `["all"]` — alles auf einmal (Standard)
- `["hero"]` — nur Hero-Texte
- `["about"]` — nur About-Sektion
- `["services"]` — nur Services-Sektion

### Response

```json
{
  "status": "ok",
  "domain": "beispiel.at",
  "refined": {
    "hero": {
      "headline": "Professioneller, verkaufsstarker Titel",
      "subtitle": "Ueberzeugender Untertitel der den Besucher anspricht",
      "eyebrow": "Branchenbezeichnung"
    },
    "about": {
      "title": "Ueber uns",
      "text": "Professionell formulierter About-Text, 2-3 Absaetze. Beschreibt das Unternehmen, die Mission, und was es besonders macht."
    },
    "services": {
      "title": "Unsere Leistungen",
      "lead": "Einleitender Satz der die Leistungspalette zusammenfasst.",
      "items": [
        { "title": "Leistung 1", "text": "Beschreibung der ersten Leistung, max 200 Zeichen." },
        { "title": "Leistung 2", "text": "Beschreibung der zweiten Leistung." },
        { "title": "Leistung 3", "text": "Beschreibung der dritten Leistung." }
      ]
    },
    "meta": {
      "seo_title": "SEO-optimierter Seitentitel | Firmenname",
      "seo_description": "Meta-Description fuer Suchmaschinen, 150-160 Zeichen."
    }
  },
  "model": "gemini-2.5-flash",
  "tokens_used": 1234,
  "duration_ms": 4500
}
```

### Fehlerbehandlung

```json
{
  "status": "error",
  "error": "insufficient_data",
  "message": "Nicht genug Textdaten fuer sinnvolles Refinement. Mindestens hero_text oder about-Text benoetigt."
}
```

Moegliche Fehler:
- `insufficient_data` — Scanner hat zu wenig Textdaten gefunden
- `ai_error` — Gemini-API Fehler (Rate Limit, Timeout)
- `invalid_request` — Fehlende Pflichtfelder

## Gemini-Prompt

Der Prompt wird als Python-Script implementiert (`scripts/refine.py`), analog zu `analyze.py`.

### System-Prompt

```
Du bist ein professioneller Webtexter. Du bekommst Rohdaten von einer gescannten Website
und erstellst daraus professionelle, verkaufsstarke Webtexte fuer eine moderne Website.

Regeln:
- Schreibe in der Sprache der Original-Texte (meist Deutsch)
- Verwende eine professionelle, aber zugaengliche Tonalitaet
- Hero-Headline: max 8 Woerter, praegnant, aktivierend
- Hero-Subtitle: 1-2 Saetze, beschreibt den Kernnutzen
- About-Text: 2-3 Absaetze, erzaehlt die Geschichte des Unternehmens
- Service-Items: 3-6 Leistungen mit kurzen, konkreten Beschreibungen
- Erfinde KEINE Fakten — leite alles aus den vorhandenen Daten ab
- Wenn Informationen fehlen, formuliere allgemein passend zur Branche
- SEO: Title max 60 Zeichen, Description 150-160 Zeichen
```

### User-Prompt

```
Website: {domain}
Branche: {branch}
Zielgruppe: {target_audience}

Vorhandene Texte:
- Titel: {title}
- Hero: {hero_text}
- About: {about_text}
- Services: {services_text}
- Kontakt: {contact}
- Sonstiger Content: {page_texts_summary}

Erstelle optimierte Webtexte im folgenden JSON-Format:
{schema}
```

### Antwort-Parsing

Gemini liefert JSON zurueck. Das Script:
1. Parst die JSON-Antwort
2. Validiert alle Pflichtfelder (hero.headline, hero.subtitle, about.title, about.text)
3. Trimmt Texte auf maximale Laengen (headline: 80 Zeichen, subtitle: 200, about: 2000, service-item: 300)
4. Gibt das validierte JSON zurueck

## Scanner-Implementation

### Neue Dateien

| Datei | Zweck |
|-------|-------|
| `scripts/refine.py` | Gemini-Aufruf + Prompt + Response-Parsing |
| `api/endpoints/refine.php` | REST-Endpoint, Validierung, ruft refine.py auf |

### Aenderungen an bestehenden Dateien

| Datei | Aenderung |
|-------|-----------|
| `api/index.php` | Route `/v1/refine` hinzufuegen |

### refine.py Ablauf

1. Lies scan_data aus stdin (JSON)
2. Baue System-Prompt + User-Prompt
3. Rufe Gemini API auf (gleiche Konfiguration wie analyze.py)
4. Parse JSON-Antwort
5. Validiere + trimme
6. Schreibe Ergebnis nach stdout (JSON)

### Rate Limiting

Gleicher Mechanismus wie analyze.py — ein Refine-Call pro Domain pro 5 Minuten. Cached in `data/refine-cache/{domain}.json`.

## Konfigurator-Integration (Endoskeleton)

### UI-Elemente

**"KI optimieren" Button** in der Sidebar, im Bereich unter dem Preset-Picker:

```html
<button id="btn-ki-refine" class="btn btn-primary btn-sm" style="width:100%;">
  <i data-lucide="sparkles"></i> KI optimieren
</button>
```

**Zustands-Anzeige:**
- Normal: "KI optimieren" mit Sparkles-Icon
- Loading: btn-loading Klasse (CSS-Spinner)
- Fertig: kurzer btn-success-flash, dann zurueck zu Normal
- Fehler: rote Fehlermeldung unter dem Button

### JavaScript-Ablauf

1. User klickt "KI optimieren"
2. Button geht in Loading-State
3. `fetch('https://scanner.ssi.at/api/v1/refine', { method: 'POST', body: JSON.stringify({ domain, scan_data, sections: ['all'] }) })`
4. Bei Erfolg:
   - Refined-Content in `_scanCache[domain]` mergen
   - `ContentInjector.inject(mergedScanData)` aufrufen
   - Button: Success-Flash
5. Bei Fehler:
   - Fehlermeldung anzeigen
   - Button zurueck zu Normal

### Content-Merge

Der Refine-Response ueberschreibt gezielt Felder im scanData:
- `scanData.hero_text` ← `refined.hero`
- `scanData.selected.about` ← `refined.about` (title + text)
- `scanData.selected.services` ← `refined.services` (title + lead + items)
- Neue Felder: `scanData.refined = true` (Flag fuer UI)

Nach dem Merge wird `ContentInjector.inject(scanData)` erneut aufgerufen — der bestehende Injector aktualisiert alle DOM-Elemente.

### Einzelne Sektionen re-generieren

Kleines Regenerate-Icon neben jeder Sektion im Konfigurator (nur sichtbar wenn `scanData.refined === true`):

```html
<button class="btn-refine-section" data-section="hero" title="Hero neu generieren">
  <i data-lucide="refresh-cw"></i>
</button>
```

Klick sendet: `sections: ["hero"]` statt `["all"]`. Response wird nur fuer die angefragte Sektion gemergt.

## Neue Dateien

| Projekt | Datei | Zweck |
|---------|-------|-------|
| ssi-scanner | `scripts/refine.py` | Gemini-Prompt + Parsing |
| ssi-scanner | `api/endpoints/refine.php` | REST-Endpoint |
| ssi-endoskeleton | (keine neuen Dateien) | Aenderungen in konfigurator.html + konfigurator.js |

## Geaenderte Dateien

| Projekt | Datei | Aenderung |
|---------|-------|-----------|
| ssi-scanner | `api/index.php` | Route `/v1/refine` |
| ssi-endoskeleton | `konfigurator.html` | KI-Button + Regenerate-Icons |
| ssi-endoskeleton | `js/konfigurator.js` | Refine-Fetch + Content-Merge |

## Testplan

1. **Unit:** refine.py mit Mock-Scan-Daten aufrufen, JSON-Output pruefen
2. **API:** `curl -X POST scanner.ssi.at/api/v1/refine` mit echten Scan-Daten
3. **Integration:** Im Konfigurator Domain scannen, "KI optimieren" klicken, pruefen ob Texte sich verbessern
4. **Edge Cases:** Scan ohne About-Text, Scan ohne Services, leerer Scan
5. **Rate Limit:** Zweiter Aufruf innerhalb 5 Minuten gibt cached Result
