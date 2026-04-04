Endoskeleton-Feature-Entscheidung und Beauftragung. Endoskeleton ist das modulare Website-Template-System für alle generierten SSI-Websites.

## Die zentrale Frage zuerst

Bevor irgendetwas implementiert wird, stelle diese Frage:

> Ist die Änderung allgemein genug, dass ALLE generierten Websites davon profitieren?

- **Ja** → gehört ins Endoskeleton. Allgemein formulieren, im Endoskeleton-Repo umsetzen.
- **Nein** → lokal in der spezifischen Website anpassen. Endoskeleton NICHT anfassen.
- **Unsicher** → wenn es mehr als 2 Websites betrifft, gehört es ins Endoskeleton.

## Prüfung: Hat Endoskeleton das schon?

Bevor etwas Neues gebaut wird, erst prüfen:

```bash
ssh server7 "ls /home/pawbot/projects/endoskeleton/"
ssh server7 "ls /home/pawbot/projects/endoskeleton/buttons/"
ssh server7 "ls /home/pawbot/projects/endoskeleton/colors/"
ssh server7 "ls /home/pawbot/projects/endoskeleton/fonts/"
ssh server7 "ls /home/pawbot/projects/endoskeleton/layouts/"
ssh server7 "ls /home/pawbot/projects/endoskeleton/heroes/"
ssh server7 "ls /home/pawbot/projects/endoskeleton/softness/"
ssh server7 "cat /home/pawbot/projects/endoskeleton/VERSION"
```

Demo: skeleton.ssi.at

## Architektur: Was gehört wohin?

```
Endoskeleton (/home/pawbot/projects/endoskeleton/)
├── Neue CSS-Presets (Farben, Fonts, Buttons, Layouts, Heroes)
├── shared.js (Demo-Controller, Nav-Behavior)
├── demo.html (Live-Preview aller Kombinationen)
├── pages/*.html (Template-Seiten: index, kontakt, impressum, datenschutz)
├── base.css (Grundstruktur)
└── config.json (Standard-Konfiguration)

SSI-Core / Panel (/home/pawbot/projects/ssi-core/)
├── Generator-UI (generate.php — Farb-/Layout-/Font-Auswahl)
├── pipeline.sh (kopiert Endoskeleton + füllt mit Inhalten)
├── WebsitesController.php (CRUD für Websites)
└── scripts/ (Image-Downloader, API-Helfer)

Generierte Websites (/home/pawbot/projects/websites/{domain}/)
├── Kopie der Endoskeleton-Presets (von pipeline.sh)
├── Von Claude individuell angepasste Inhalte
└── _endoskeleton/ (Referenz-Templates, nicht ausliefern)
```

## Beauftragung: So formulieren

NICHT: "Die Obststadt-Website braucht einen neuen Button-Stil"
SONDERN: "Endoskeleton soll einen neuen Button-Preset 'pill' bekommen (abgerundete Kanten, breit, zentrierter Text)"

NICHT: "Im Generator fehlt eine Schriftart-Option"
SONDERN: Zwei Schritte:
1. "Endoskeleton: neuen Font-Preset 'display' anlegen (Display/Headline-Schrift)"
2. "Panel: Font-Preset 'display' im Generator als Option hinzufügen"

Der Auftrag ist immer system-agnostisch — keine Referenz auf eine spezifische Website.

## Umsetzung

### Endoskeleton ändern (auf server7):

```bash
ssh server7 "cd /home/pawbot/projects/endoskeleton && [Änderungen machen]"
```

Nach Änderungen:
1. VERSION-Datei bumpen (patch bei Fixes, minor bei Features)
2. CHANGELOG.md aktualisieren
3. shared.js Version-String aktualisieren
4. demo.html Version aktualisieren
5. Committen + Pushen:

```bash
ssh server7 "cd /home/pawbot/projects/endoskeleton && git add -A && git commit -m 'feat: vX.Y.Z — Beschreibung' && git push origin main"
```

### Pipeline/Generator ändern (Panel):

```bash
ssh server7 "cd /home/pawbot/projects/ssi-core && [Änderungen machen]"
```

### Bestehende Websites aktualisieren:

Wenn ein Endoskeleton-Update auch bestehende Websites betreffen soll:
```bash
# Einzelne Website updaten (nur CSS-Presets, NICHT Inhalte)
ssh server7 "cd /home/pawbot/projects/ssi-core && bash scripts/pipeline.sh update-css <domain>"
```

⚠️ NIEMALS Inhalte überschreiben — nur CSS-Presets/shared.js updaten!

## Endoskeleton Info (Stand 2026-04-04)

- Repo: github.com/mmollay/endoskeleton
- Pfad: /home/pawbot/projects/endoskeleton/ (server7)
- Version: 3.15.0
- Demo: skeleton.ssi.at
- Generator: panel.ssi.at/websites (SSI-Core)

## Verfügbare Presets

| Kategorie | Optionen |
|-----------|----------|
| Layouts | modern, classic, magazine |
| Heroes | minimal, split, fullscreen, banner, veil |
| Farben | 23 Profile (green, blue, coral, navy, wine, charcoal...) |
| Buttons | rounded, soft, sharp, outline, bold, ghost, tactical, tech |
| Fonts | modern, system, serif, humanist, editorial, elegant, rounded, mono |
| Charakter | sanft, elegant, neutral, markant, kantig |
| Nav-Layouts | standard, minimal, centered, club |
| Nav-Behavior | sticky, autohide, transparent |
| Spacing | compact, normal, spacious |
| Animation | none, subtle, dynamic |
| Widths | compact, default, wide, full |

~45.000 visuelle Kombinationen.
