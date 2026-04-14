# Changelog — SSI Endoskeleton

Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)

---

## [3.47.0] — 2026-04-14

### Added
- **KI-Refinement automatisch beim Generieren** — Klick auf "Website generieren" ruft automatisch Gemini auf, das Hero-Texte, About, Services und alle Subpages professionell umschreibt. Kein manueller "KI optimieren"-Klick mehr nötig.
- **Subpage-Texte KI-optimiert** — refine.py verarbeitet jetzt bis zu 8 Unterseiten mit eigenem Titel und Text. Vorher nur Hero/About/Services.
- **Bilder lokal eingebettet** — Generierte Seiten enthalten die Bilder im `img/`-Ordner statt Hotlinking auf die Original-Website. ZIP enthält alles, Seite funktioniert offline.
- **Subpage-Bilder** — Unterseiten-Template zeigt jetzt das passende Bild neben dem Text (grid-2 Layout).
- **Preview im neuen Tab** — "Website generieren" öffnet die fertige Seite direkt im Browser. ZIP wird parallel heruntergeladen.

### Fixed
- **Nav-Labels vs. Seitentitel getrennt** — Navigation zeigt kurze Labels ("Förderungen"), Seitenüberschriften den vollen KI-Titel ("Förderungen & Finanzierung für Ihren Assistenzhund").
- **About-Textgröße** — `section-text` statt `section-lead` für lange About-Texte, keine überdimensionierte Schrift mehr.
- **Absätze im About-Text** — `textToHtml()` wandelt Newlines in echte `<p>`-Tags um statt alles in einen Block zu pressen.
- **CSS-Module in allen Templates** — subpage.html, kontakt.html, impressum.html, datenschutz.html haben jetzt alle CSS-Links (fonts, colors, heroes). Vorher fehlten sie → falsche Farben/Schriften auf Unterseiten.
- **Leere Bild-Container entfernt** — Seiten ohne Bilder zeigen kein broken-image-Icon mehr.
- **Footer-/Nav-Müll in Texten** — "Powered by SSILogin", "Impressum - Kontakt - Datenschutz", spaced-out Titel ("N e u i g k e i t e n") werden jetzt gefiltert.
- **Login/Admin/403-Seiten gefiltert** — Keine Login-Pages oder Error-Seiten mehr im Nav oder als Subpages.
- **Broken Slugs** — Query-Strings (`?lp=center`) werden vor der Slug-Extraktion entfernt, kein "indexphplpcenter" mehr.
- **Service-Cards** — Bevorzugt KI-refined Items, bessere Fallback-Extraktion bei Texten ohne Doppel-Newlines.
- **Hero-Subtitle** — Zeigt echten Subtitle statt abgeschnittenen About-Text.
- **Cleanup-TTL** — Generierte Seiten bleiben 24h statt 1h (Entwicklungsphase).

### Changed
- **`_cleanPageText()` erweitert** — Filtert Footer-Zeilen (beliebige Länge), Nav-Listen, spaced-out Zeilen, trailing Footer-Content.
- **`_extractServiceItems()` verbessert** — Fallback auf Single-Newline-Split wenn keine Doppel-Newlines vorhanden.

---

## [3.46.0] — 2026-04-14

### Fixed
- **Umlaute korrekt** — Fallback-Texte im Konfigurator nutzen jetzt echte Umlaute (Über uns, über, Leistungen) statt ASCII-Ersatz.

---

## [3.45.8] — 2026-04-14

### Removed
- **"Ansicht vertiefen" Button entfernt** — Öffnete nur einen nutzlosen Fullscreen-Preview ohne Funktionalität. "Website generieren" ist jetzt der einzige Action-Button.

---

## [3.45.7] — 2026-04-14

### Added
- **Scanner-Ergebnis Link im Header** — Nach dem Scannen einer Domain erscheint oben in der Sidebar ein "Scanner-Ergebnis" Link, der direkt zur Scanner-API-Ansicht führt (`scanner.ssi.at/api/v1/scans/{domain}`). Damit kann man sofort prüfen ob Bilder und Texte korrekt erfasst wurden.

---

## [3.45.6] — 2026-04-13

### Fixed
- **Override-Key-Aliase für Robustheit** — `generate.php` normalisiert jetzt alternative Key-Namen (`button→buttons`, `softness→charakter`, `navlayout→navLayout`), damit auch externe API-Aufrufe mit leicht abweichenden Keys funktionieren.

---

## [3.45.5] — 2026-04-13

### Fixed
- **Leere Sections auch in Subpages entfernt** — `removeEmptySections()` erkennt jetzt `<div id="site-footer">` und `<script>` als Section-Grenzen. Damit werden auch leere CTA/Features-Sections in Subpages korrekt entfernt, wo kein HTML-Kommentar als Folge-Marker existiert.

---

## [3.45.1] — 2026-04-13

### Fixed
- **Empty-Element Cleanup zu aggressiv** — Regex entfernte auch `<div class="hero-bg" style="background-image:..."></div>` weil Div "leer" war (kein Textinhalt). Fix: `div` und `section` aus dem Cleanup entfernt, nur semantische Text-Elemente (h1-h6, p, span, a, li, figcaption, cite) werden gecleant.

---

## [3.45.0] — 2026-04-13

### Fixed
- **Bilder-Mapping komplett repariert (BUG 1)** — Scanner speichert Bilder als `images[].url`, Konfigurator las `hero_image.src`. Neuer Image-Index baut `imagesByRole` und `imagesByPage` Lookup-Maps. Hero-Image Fallback-Kette: hero_image → role:hero → grösstes Bild → Screenshot.
- **About-Bild aus Scanner-Images** — About-Section bekommt Bilder mit `role:"about"` aus dem Image-Index.
- **Subpage-Bilder per page_url zugeordnet** — Jede Unterseite bekommt das passende Bild aus `imagesByPage[url]`.
- **Subpages leer (BUG 2)** — Sections als `type: "text"` statt `type: "about"` gesendet, damit `PAGE_CONTENT` befüllt wird.
- **Leere HTML-Elemente (BUG 4)** — Nach Placeholder-Cleanup werden leere h1-h6, p, span, a, li, figcaption, cite entfernt.

---

## [3.44.5] — 2026-04-13

### Fixed
- **Text-Cleaner komplett umgeschrieben** — `_cleanPageText()` filtert jetzt aggressiv Cookie-Banner ("Diese Webseite benutzt Cookies", "Verstanden!", "Learn more"), Navigation-Fragmente (Home, Kontakt, Links, Spenden, etc.) und ALL-CAPS-Kurzzeilen **überall** im Text, nicht nur am Anfang. Erste echte Content-Zeile muss >30 Zeichen haben. Verhindert dass generierte Seiten Cookie-Banner und Nav-Müll als About-Text oder Hero-Subtitle zeigen.

---

## [3.44.4] — 2026-04-13

### Fixed
- **Leere index.html bei Generierung — Root Cause gefunden und behoben** — Scanner liefert für manche Domains duplizierte URLs (`blindenhund.at`, `www.blindenhund.at/index.html`, `blindenhund.at/index.html`), die alle zum Slug "index" normalisiert werden. Die foreach-Schleife in `SiteGenerator.generate()` überschrieb die korrekte index.html (mit Hero/About/Services) mit einer späteren Subpage-Version (nur About). Fix: Slug-Deduplizierung in JS (`_buildContentFromScan`) UND PHP (`SiteGenerator.generate()`). Erste Occurrence gewinnt — die index-Seite mit 3 Sections bleibt erhalten.
- **www-Varianten in Subpage-Liste** — `_buildContentFromScan` vergleicht jetzt URLs auch ohne `www.` gegen die start_url, um doppelte Homepage-Einträge zu vermeiden.

---

## [3.44.3] — 2026-04-13

### Fixed
- **Leere generierte Seiten behoben** — `_buildContentFromScan()` hat bei fehlendem Scan-Daten leere Strings an den Generator übergeben. Jetzt mehrstufige Fallback-Ketten:
  - **Hero-Bild:** `hero_image.src` → `screenshots.desktop` (Scanner-Screenshot als Fallback)
  - **About-Text:** `selected.about.text` → SEO-Description → automatisch generierter Firmentext
  - **Services:** Navigations-Müll wird erkannt und verworfen (wenn >70% des Originaltexts beim Cleaning verloren geht). Fallback: Service-Items werden aus den Scan-Seitentiteln aufgebaut.
  - **Subtitle:** `hero_text.subtitle` → SEO-Description
- **Nav-Fragmente in Services-Text** — `_cleanPageText()` filtert jetzt Navigations-Zeilen ("Start", "Home", "Kontakt", "DE", "|" etc.) aggressiv raus, statt nur Großbuchstaben-Zeilen zu überspringen.
- **Broken Images in generierten HTML-Seiten** — `SiteGenerator.php` entfernt jetzt `<img src="">` Tags und leere `background-image: url('')` Styles nach der Generierung, statt broken-image-Icons anzuzeigen.

---

## [3.44.2] — 2026-04-13

### Fixed
- **ZIP-Download via Blob statt window.location** — Generate-Button lud ZIP per `window.location.href = download_url`, was zu 503-Fehlern führte (Cloudflare Race-Condition bei frisch generierten URLs). Jetzt: ZIP wird via `fetch()` als Blob geladen und per temporärem `<a download>` ausgelöst. Funktioniert sofort, keine Timing-Probleme.

---

## [3.44.1] — 2026-04-13

### Changed
- **demo.html abgeschafft** — `demo.html` (~2700 Zeilen) durch einen 15-Zeilen Redirect-Stub ersetzt. Leitet `demo.html#hash` automatisch auf `konfigurator.html?fullscreen=1#hash` um. Bestehende Bookmarks, API-Links und Google-Index funktionieren weiter.
- **Fullscreen-Modus in konfigurator.html** — Neuer URL-Parameter `?fullscreen=1` versteckt Sidebar + Domain-Bar und zeigt nur die reine Live-Preview. **Eine Wahrheit** für den gesamten Sidebar-Code.
- **"Ansicht vertiefen" Button** — öffnet jetzt `konfigurator.html?fullscreen=1` statt `demo.html`.
- **API-URLs aktualisiert** — `CatalogReader.php`, `preview.php`, `presets.php`, `RecommendEngine.php`, `schema.php` zeigen auf `konfigurator.html?fullscreen=1` statt `demo.html`. CatalogReader parst PRESETS jetzt aus `konfigurator.html`.

---

## [3.44.0] — 2026-04-13

### Fixed
- **demo.html mit konfigurator.html synchronisiert** — `demo.html` hatte eine komplett veraltete Sidebar-Kopie (keine Tabs, nur 16 Fonts, nur 14 Buttons, altes flaches Layout). Jetzt identisch: Kategorien-Tabs, alle 27 Schriften, alle 24 Buttons, alle 24 Farben in 5 Tabs, Card-Style Sektionen, graues Sidebar-Panel (`#f1f5f9`), `syncCatTabs()` bei Preset-Wechsel. **Eine Wahrheit für beide Dateien.**

---

## [3.43.3] — 2026-04-13

### Changed
- **Sidebar-Panel visuell abgehoben** — Hintergrund von `#fafafa` auf `#f1f5f9` (deutlicherer Kontrast zur weißen Preview), plus `border-left` und `box-shadow` nach links. Die Sidebar hebt sich jetzt klar als eigener Bereich vom Live-Preview ab.
- **Demo-Section Cards weiß auf grauem Panel** — `.demo-section` jetzt `#ffffff` statt `#f8fafc` → weißer Karteneffekt auf dem dunkleren Sidebar-Hintergrund. Stärkerer Kontrast, schnellere visuelle Zuordnung.
- **Action-Buttons-Bereich** — `border-top`-Linie entfernt. Stattdessen `margin-top: 8px` und eigener Hintergrund `#eef2f7` mit abgerundeter Oberkante (`border-radius: 10px 10px 0 0`). Visuell klar getrennt vom Sidebar-Content ohne harte Linie.

---

## [3.43.2] — 2026-04-13

### Changed
- **Action-Buttons entwirrt** — Das Button-Chaos am unteren Sidebar-Rand aufgeräumt. Klare Hierarchie: **Website generieren** ist jetzt die einzige große primäre CTA (solid-blau, Schatten, Icon 🚀). **Ansicht vertiefen** ist sekundär (outline, kleiner). **Config exportieren** und **/endo Befehl** wurden zu kompakten Utility-Buttons (outline, 2-Spalten-Row, Icons statt Emoji 🤖) im Details-Tab zusammengefasst. Die 4 konkurrierenden Vollflächen-Buttons sind damit ersetzt durch ein klares Primär-/Sekundär-/Utility-Muster.
- **Icons auf Action-Buttons** — Lucide-Icons (`download`, `terminal`, `layers`, `rocket`) statt Text-only für schnellere visuelle Erkennung.

---

## [3.43.1] — 2026-04-13

### Changed
- **Sidebar-Sektionen als Cards** — `.demo-section` hat jetzt einen dezenten Card-Look (heller Hintergrund `#f8fafc`, 1px Border, 8px Border-Radius, subtle Shadow). Statt thin-line-Separatoren sind Farbe/Schrift/Buttons und alle anderen Sektionen visuell klar abgeteilt, schneller wahrnehmbar.

---

## [3.43.0] — 2026-04-13

### Added
- **Kategorien-Tabs im Konfigurator** — Farben, Schriften und Buttons sind jetzt in Tabs pro Kategorie gegliedert (kleine Chips über dem Grid). Spart ~60% vertikalen Platz in der Sidebar und macht die Auswahl künstlerisch statt technisch.
  - **Farben (5 Tabs):** Warm · Grün · Blau · Neutral · Neon (24 Swatches)
  - **Schriften (5 Tabs):** Sans · Serif · Display · Verspielt · Mono (**alle 27 Schriften** — 11 fehlende im Picker ergänzt: contrast, editorial, elegant, humanist, modern, mono, playful, rounded, serif, survival, system)
  - **Buttons (5 Tabs):** Eckig · Rund · Soft · Outline · Special (**alle 24 Buttons** — 10 fehlende ergänzt: bold, ghost, outline, outline-thin, outline-elegant, rounded, sharp, soft, tactical, tech)
- **Auto-Tab-Switch bei Preset-Wechsel** — Beim Anwenden eines Presets oder Laden via URL-Hash wechselt der Tab automatisch in die Kategorie der aktiven Karte.
- **KI-Refinement Ergebnis-Panel** (aus v3.42.2) — Schwebendes Panel rechts oben zeigt nach dem KI-Klick die neuen Hero/About/Services/SEO-Texte + Links zu den Sektionen (`_showRefineResults` in `js/konfigurator.js`).

### Fixed
- **refine.php Python-venv-Pfad** (aus v3.42.1) — Hart verdrahtet auf `/home/pawbot/core/venv/bin/python3` (vorher relativer Pfad `$scannerDir/../core/venv/bin/python3`, was auf server7 ins Leere zeigte und KI-Refinement stillschweigend fehlschlagen ließ).

---

## [3.42.0] — 2026-04-12

### Added
- **KI optimieren Button im Konfigurator** — Neuer "KI optimieren" Button in der Struktur-Sidebar (nach dem Button-Picker). Aktiviert sich nach erfolgreichem Scan. Ruft `ScannerClient.refine()` auf und merged verfeinerte Hero/About/Services-Texte direkt in die Vorschau.
- **ScannerClient.refine()** — Neue Methode in `scanner-client.js` für POST-Requests an `/api/v1/refine` (via scan-proxy).
- **scan-proxy: refine-Pfad** — `api/scan-proxy.php` Whitelist um `refine` erweitert.

---

## [3.41.0] — 2026-04-12

### Added
- **Icon Picker im Konfigurator** — Dropdown nach dem Button-Grid in der Sidebar mit 17 Lucide-Icons (arrow-right, chevron-right, phone, mail, calendar, download, external-link, heart, star, check, search, user, home, map-pin, shield, zap, globe). Icon wird live in alle `.hero-ctas .btn` Buttons eingefügt.

---

## [3.40.0] — 2026-04-12

### Changed
- **button-test.html komplett neugeschrieben** — Umfassende Showcase-Seite mit Palette-Switcher (alle 24 Paletten), Sizes-Section, Color-Variants, Icons mit Lucide, States (normal/loading/disabled/success) und Module Preview Grid mit computed-style Capture

---

## [3.39.0] — 2026-04-12

### Added
- **Semantic Button Colors** — `.btn-success`, `.btn-danger`, `.btn-warning` Klassen in `base.css` mit Outline-/Ghost-Varianten (kombinierbar: `.btn-outline.btn-success` etc.)
- **Button Disabled State** — `.btn:disabled` / `.btn.btn-disabled` mit opacity, cursor-not-allowed, pointer-events: none
- **Button Loading Spinner** — `.btn-loading` zeigt animierten Spinner, transparent für ghost/outline Varianten
- **Button Success Flash** — `.btn-success-flash` + `window.btnSuccess(btn, ms)` Helper in `shared.js` — zeigt Checkmark-Icon, revertiert nach Ablauf automatisch
- **Focus Visible** — `.btn:focus-visible` mit outline für Keyboard-Navigation

---

## [3.38.0] — 2026-04-12

### Changed
- **Hero-Buttons respektieren Button-Module** — Safety-Net (Spezifitaet 0,5,0) aus base.css entfernt. Jedes der 24 Button-Module zeigt jetzt seinen eigenen Stil im Hero.
- **base.css Hero-Kontrast erweitert** — btn-outline und btn-secondary werden auf dunklen Hero-Hintergruenden jetzt automatisch weiss dargestellt (zusaetzlich zu btn-ghost)
- **6 Module mit Hero-Kontrast-Regeln** — bold, tactical, tech, minimal-link, cta-arrow, tech-angle haben eigene Dark-Hero-Regeln fuer Sichtbarkeit auf Bild-Hintergruenden
- **Playwright-Test** — `tests/hero-buttons-test.mjs` prueft alle 24 Module auf fullscreen + split Hero (48 Tests)

---

## [3.34.0] — 2026-04-12

### Added
- **Website-Download im Konfigurator** — der "Website generieren"-Button erzeugt jetzt tatsaechlich ein ZIP mit der kompletten Website (Home + Unterseiten + CSS/JS). Die Scan-Daten werden serverseitig in das SiteGenerator-Content-Format uebersetzt und via `/api/v1/generate` verarbeitet.
- `_buildContentFromScan()` — Adapter-Funktion die Scanner-Daten (hero_image, selected.about, selected.services, contact, colors) in das content-Format mapped das SiteGenerator.php erwartet
- `_cleanPageText()` — entfernt Navigations-Prefixe aus Scanner-Texten (die ersten Zeilen sind oft kurze Nav-Labels in Grossbuchstaben)
- `_extractServiceItems()` — splittet Fliesstext in einzelne Service-Card-Items (Titel + Text)
- `_findPrimaryColor()` — findet die erste brauchbare Farbe aus `scan.colors` (ueberspringt Weiss/Schwarz, wandelt rgb() in hex um)

### Changed
- `_onGenerate()` ist nicht mehr ein Clipboard-Copy sondern ruft `/api/v1/generate` auf und startet den ZIP-Download
- `konfigurator.html` — Cache-Buster v=3.33.0 -> v=3.34.0

---

## [3.33.0] — 2026-04-11

### Changed
- **content-injector.js** — `_injectPageContent` nutzt jetzt zuerst `scan.selected.{about,services}` (Scanner v1.6.0+), fällt nur noch zur Iteration über `pages[]` zurück wenn das Feld absent ist
- **About-Section-Bild** — Reihenfolge: `scan.selected.about.image` → `_getImagesByRole("about")` → `contentImages[1]` Index-Fallback
- `konfigurator.html` — Cache-Buster v=3.32.0 → v=3.33.0

---

## [3.32.0] — 2026-04-11

### Added
- **Services-Cards Injection** — `content-injector.js` füllt jetzt `.services-card img` / `.service-item img` / `.service-card img` / `.services-grid img` Elemente mit den Scan-Bildern die `role === "services"` tragen (Scanner v1.5.0+)
- Helper `_getImagesByRole(scanData, role)` — Role-basierter Bild-Filter mit Fallback zu leerem Array für alte Scans

### Changed
- **About-Section-Bild** bevorzugt jetzt `role === "about"` Bilder über die bisherige `contentImages[1]` Index-Logik. Zero Regression: wenn kein role-getaggtes Bild existiert, greift der alte Codepfad
- `konfigurator.html` — Cache-Buster von `v=3.31.3` auf `v=3.32.0`

---

## [3.31.3] — 2026-04-11

### Changed
- **content-injector.js** — Hero-Bild-Auswahl nutzt jetzt `scan.hero_image.src` wenn der Scanner dieses Feld geliefert hat (Scanner v1.4.0+). Fällt auf die bestehende `contentImages[0]` Heuristik zurück, wenn das Feld absent oder malformed ist. Zero Regression für alte Scans.
- **content-injector.js** — Hero-Injection-Guard nutzt jetzt `if (heroImg)` statt `if (contentImages.length > 0)`, sodass Sites die nur `scan.hero_image` liefern aber keine anderen Content-Bilder haben trotzdem ihr Hero injiziert bekommen.
- `konfigurator.html` — Cache-Buster von `v=3.31.2` auf `v=3.31.3` für konfigurator.css, scanner-client.js, content-injector.js, konfigurator.js

---

## [3.31.2] — 2026-04-10

### Fixed
- **content-injector.js** — Title-Shortening bricht nicht mehr mitten in Wort-Bindestrichen: `"Smart-Kit"` bleibt jetzt erhalten, nur Separatoren mit umgebendem Whitespace (` – `, ` | `, ` - `) werden als Split-Punkt erkannt

---

## [3.31.1] — 2026-04-10

### Fixed
- **content-injector.js** — Nav-Labels, About- und Services-Titel werden beim ersten Trennzeichen (`–`, `-`, `|`) gekürzt, damit lange Page-Titel wie `"Leistungen – SSI | Webhosting, …"` als `"Leistungen"` erscheinen. Nav-Labels werden zusätzlich hart auf 25 Zeichen begrenzt

---

## [3.31.0] — 2026-04-10

### Fixed
- **content-injector.js** — About/Services-Sections bleiben nach Inject erhalten: `_setAllText` durch `_setTextAndLock` ersetzt, sodass `applyLang()` die injizierten Scan-Texte nicht mehr überschreibt
- **content-injector.js** — Home-Dedup in Navigation: Pages mit gleichem Titel wie die Homepage (z.B. `/index.html`) werden jetzt ebenfalls aus der Nav gefiltert
- **content-injector.js** — Hero-Bild in allen Hero-Varianten: Split-Layout nutzt `<img src>`, Fullscreen/Banner/Veil nutzen `background-image` — beide Wege werden jetzt bespielt

### Changed
- **content-injector.js** — Fallback-Titel für About/Services: wenn der Scan keine dedizierte Seite findet, werden jetzt "Über uns" und "Unsere Leistungen" statt der generischen Template-Platzhalter angezeigt

---

## [3.19.7] — 2026-04-04

### Fixed
- **demo.html** — FOUC (Flash of Unstyled Content) behoben: Blocking-Script im `<head>` parsed Hash und setzt CSS-URLs per `document.write` VOR dem ersten Render. Kein weißer Flash mehr bei URL mit Hash-Parametern.

---

## [3.19.6] — 2026-04-04

### Added
- **after-fix-v2.png** — Screenshot-Dokumentation nach Logo-Fix

---

## [3.19.5] — 2026-04-04

### Fixed
- **base.css** — Logo-Overlay in Navigation behoben: `flex-shrink: 0`, `white-space: nowrap` und `gap` auf `.nav-inner` verhindern, dass Logo und Menü-Items sich überlagern

---

## [3.19.4] — 2026-04-04

### Fixed
- **demo.html / shared.js** — Release-Bump v3.19.4

---

## [3.19.3] — 2026-04-04

### Fixed
- **demo.html** — Release-Bump nach IIFE-Scope-Fix und Tab/Quick-Preset-Korrekturen

---

## [3.19.2] — 2026-04-04

### Fixed
- **demo.html** — `switchSidebarTab`, `applyPreset`, `exportConfig` als `window.*` exponiert, da sie innerhalb einer IIFE definiert waren und durch `onclick`-Attribute im HTML nicht erreichbar waren (ReferenceError)

---

## [3.19.1] — 2026-04-04

### Changed
- **VERSION** — Patch-Release nach v3.19.0 (Config-Export, 4-Tab-Sidebar)

---

## [3.19.0] — 2026-04-04

### Changed
- **demo.html** — Sidebar in 4 Tabs gegliedert: Presets / Struktur / Stil / Details
- Struktur-Tab: Layout, Hero, Max-Breite, Menü-Layout
- Stil-Tab: Farbe, Charakter, Schrift, Buttons, Dichte, Animation
- Details-Tab: Nav-Verhalten, Nav-Stil, Footer, Theme, Sprache, Config-Export
- **Config Export** — Auto-Copy in Zwischenablage mit Toast-Feedback „✓ Kopiert"

---

## [3.18.0] — 2026-04-04

### Added
- **demo.html** — 6 Quick-Presets in der Sidebar: Corporate, Kreativ, Minimal, Tech, Natur, Neon
- Jeder Preset setzt alle Parameter gleichzeitig (Layout, Hero, Farbe, Charakter, Font, Buttons,
  Nav-Stil/Verhalten/Layout, Footer, Spacing, Animation, Breite, Theme)
- UI-Controls (Selects + Buttons) werden bei Preset-Wahl synchron aktualisiert
- Aktiver Preset wird visuell hervorgehoben (goldener Rahmen)

---

## [3.17.1] — 2026-04-04

### Fixed
- **VERSION** — Datei zeigte noch 3.16.0 statt 3.17.0 (nicht beim letzten Release mitgekommen)

---

## [3.17.0] — 2026-04-04

### Changed
- **demo.html** — Sidebar wechselt von Overlay auf Push-Modus: Content und fixe Nav werden
  seitlich verschoben statt überlagert (`margin-right` auf `body`, `right` auf `.site-nav`)
- **demo.html** — Tab-Button zeigt jetzt Pfeil-Symbol `›` mit Rotation-Animation statt statischem "Demo"-Text

---

## [3.16.0] — 2026-04-04

### Fixed
- **demo.html** — alle internen IDs und Funktionen von `softness-*` auf `charakter-*` umbenannt
  (Link-ID, Select-ID, JS-Funktion, Event-Listener, URL-State-Key, Kombinations-Counter)
- **Config-Export** — Schlüssel war `softness`, jetzt korrekt `charakter` (konsistent mit config.json)
- **URL-State** — Fallback-Wert war `'balanced'` (legacy, existiert nicht mehr), jetzt `'neutral'`
- **Versionsangaben** — demo.html Title, Sidebar-Label, alle CSS-Cache-Buster auf v3.16.0
- **shared.js** — Version-Kommentar auf v3.16.0

### Removed
- **softness/balanced.css** — Legacy-Datei, ersetzt durch `neutral.css`
- **softness/raw.css** — Legacy-Datei, ersetzt durch `kantig.css`
- **softness/soft.css** — Legacy-Datei, ersetzt durch `sanft.css`

---

## [3.15.0] — 2026-04-04

### Changed
- **Charakter-System** (ehemals "Softness/Zartheit") — 5 Stufen statt 3:
  - `elegant` (NEU) — maximale Rundung, Glow-Schatten, Pill-Buttons, Luxus-Feeling
  - `sanft` (war `soft`) — primärfarb-getönte Schatten, große Rundung
  - `neutral` (war `balanced`) — moderater Standard
  - `markant` (NEU) — wenig Rundung, klare Kanten, definierte Borders
  - `kantig` (war `raw`) — null Rundung, keine Schatten, harte Kanten
- **Farbprofile** — 8 neue Presets (23 total):
  navy, wine, coral, olive, mint, slate, lavender, charcoal
- **config.json** — Feld `softness` umbenannt zu `charakter`, neue Optionen dokumentiert
- **Pipeline** — Charakter-Leitfaden im Generierungs-Prompt aktualisiert,
  Hinweis dass Generator eigene Farben in theme.css setzen kann

### Fixed
- **demo.html** — Version-Anzeige auf v3.15.0 aktualisiert (zeigte noch v3.13.8)
- **demo.html** — Charakter-Auswahl zeigt jetzt alle 5 neuen Optionen (Elegant/Sanft/Neutral/Markant/Kantig)
- **demo.html** — Farb-Select zeigt alle 23 Farbprofile inkl. der 8 neuen

### Note
- Alte Dateinamen (soft.css, balanced.css, raw.css) bleiben als Kompatibilität erhalten
- Neue Dateinamen (elegant.css, sanft.css, neutral.css, markant.css, kantig.css) sind die empfohlenen

---

## [3.14.0] — 2026-04-03

### Changed
- **Navigation** — `.nav-inner` Container eingeführt:
  - Nav-Content (Logo, Links, Hamburger) wird jetzt durch `max-width` begrenzt
  - Auf breiten Screens (>1200px) bleibt der Inhalt zentriert statt gestreckt
  - Vergleichbar mit PawBot-Navleiste
  - `--max-width` in theme.css überschreibbar für site-spezifische Breiten
- **Kontaktseite** — Layout-Reihenfolge getauscht:
  - Kontaktdaten jetzt links, Formular rechts (natürlicher Lesefluss)
- **Width-System** — `compact.css` nutzt jetzt `.nav-inner` statt `.site-nav` Padding-Hack

---

## [3.13.8] — 2026-04-03

### Changed
- **Pricing Cards** — komplett überarbeitetes Design:
  - Grüne Kreis-Checkmarks statt schlichte ✓-Zeichen
  - Keine Trennlinien zwischen Features (cleaner Spacing)
  - "EMPFOHLEN"-Badge schwebt über der Featured-Card (absolute Position)
  - Full-Width CTA-Buttons am Card-Ende
  - Dezenter Glow-Effekt um Featured-Card
  - Links-Ausrichtung statt zentriert
- **Formulare** — modernere Input-Felder:
  - Größeres Padding (0.875rem statt 0.75rem)
  - `bg-alt` Hintergrund, wechselt bei Focus auf `bg`
  - Stärkerer Focus-Ring (0.25rem statt 0.1875rem)

---

## [3.13.7] — 2026-04-03

### Added
- **4 Menü-Layout-Varianten** (`navLayout` Dimension):
  - `standard` — Logo links, Items rechts (Default, keine Overrides)
  - `centered` — Items zentriert, CTA abgetrennt rechts
  - `minimal` — Nur Logo + Hamburger, auch am Desktop
  - `club` — Akzentlinie, Uppercase-Items, dezente Trenner (Gastronomie/Events)
- CTA-Button "Jetzt anfragen" in Demo-Navigation
- `navLayout` in Config-Export aufgenommen

---

## [3.13.6] — 2026-04-03

### Added
- **4 Neon/Vibrant Farb-Presets** — ideal für Tech/Dark Theme:
  - `lime` — Neon-Grün (`#22c55e`)
  - `cyan` — Electric Cyan (`#06b6d4`)
  - `electric` — Electric Indigo-Blau (`#6366f1`)
  - `neon-pink` — Hot Pink (`#ec4899`)
- Kombinations-Counter springt auf **~99,5 Mio.** (war ~73 Mio. bei 11 Farben)

---

## [3.13.5] — 2026-04-03

### Added
- **Tech Theme** (`theme=tech`) — ultra-dunkles Blauschwarz (`#0a0a0f`), kühl-blaue Borders, inspiriert von pawbot.ssi.at. Demo-Button: "⚡ Tech"
- **Minimal-Hero Glow** — Radiales Glow-Overlay bei `theme=tech` und `theme=dark` (pawbot.ssi.at-Stil: weicher Lichtschein hinter dem Titel)
- **Kombinations-Counter** — Demo-Sidebar zeigt Gesamtzahl möglicher Designs (dynamisch aus DOM berechnet); aktuell ~97 Mio. Kombinationen

---

## [3.13.4] — 2026-04-03

### Added
- **Warm Theme** — drittes Theme neben Hell/Dunkel, inspiriert von survivaltraining.at. Warme Creme-Töne (`#f5f2eb`) statt Kalt-Weiß, Text in warmem Dunkelbraun, Borders mit Ocker-Tint. Demo-Switcher: "☕ Warm"-Button. URL-Parameter `theme=warm` funktioniert.
- `base.css` — Nav-Overrides für `[data-theme="warm"]` (analog zu Dark-Overrides)
- URL-State: `theme`-Lesung nutzt jetzt `getAttribute` statt `hasAttribute` — liest auch `warm` korrekt aus

---

## [3.13.3] — 2026-04-03

### Changed
- **Split-Hero** — `min-height` von `100vh` auf `70vh` reduziert. Wirkt kompakter und weniger erdrückend; nächste Section wird sofort sichtbar. Entspricht der üblichen Praxis für Split-Layouts

---

## [3.13.2] — 2026-04-03

### Fixed
- **Veil-Hero heller Schleier** — Softness-Presets (balanced, soft, raw) überschrieben den Veil-Overlay mit dunklem Gradient via `!important`. Fix: `.hero:not(.hero--veil) .hero-bg::after` in allen 3 Presets. Veil-Hero zeigt jetzt korrekt den weißen Schleier (survivaltraining.at-Stil)

---

## [3.13.1] — 2026-04-03

### Fixed
- **Modern-Layout + Veil/Fullscreen-Hero** — `section`-Mindesthöhe (60vh) gilt jetzt nur noch für `:not(.hero)`; Hero-Sektionen behalten ihre eigene Höhe (100vh)

---

## [3.13.0] — 2026-04-03

### Fixed
- **Magazine-Layout** — Komplett überarbeitet: keine sichtbaren Rahmen, kein 4px-Akzentbalken, keine Trennlinien bei Stats. Stattdessen subtile Schatten, leichte Rundungen, mehr Whitespace
- **Split-Hero** — Layout-Bug behoben (`.hero { display:flex }` aus base.css überschrieb `display:grid`); Bild füllt jetzt volle Zeilenhöhe; Gradient-Blend-Edge und Primärfarb-Tint auf Textseite
- **Hero-Hintergrund** — `section:nth-of-type()` in Modern, Classic und Magazine auf `:not(.hero)` beschränkt; Hero-Gradienten werden nicht mehr überschrieben
- **Classic Layout** — `.grid-3` auf `repeat(3, 1fr)` korrigiert (war `1fr 1fr`)
- **Karten-Höhen** — `align-items: start` → `align-items: stretch` in allen Grid-Klassen; Karten in einer Zeile sind jetzt immer gleich hoch

### Summary
Umfassende QA-Runde: 15 Layout×Hero-Kombinationen getestet. Alle Karten gleich hoch, alle Hero-Hintergründe korrekt, Magazine-Layout luftig und modern.

---


## [3.12.2] — 2026-04-03

### Fixed
- **Minimal-Hero** — Kreis-Dekorationen entfernt; Hintergrund jetzt mit elliptischen Radial-Gradient-Blobs (Primärfarbe 18%/10% Opacity) statt sichtbarer Kreisringe
- **Minimal-Hero** — `.hero-decoration` auf `display: none` gesetzt; Veil/Tinted-Varianten beibehalten

### Summary
Minimal-Hero ist jetzt wirklich minimalistisch — kein visueller Lärm, nur subtile Farb-Tiefe.

---


## [3.12.0] — 2026-04-03

### Added
- **Pricing-Section** — 3-Spalten Preistabelle mit `.pricing-card`, `.pricing-featured` (Highlight-Border), `.pricing-badge`, `.pricing-amount`, `.pricing-features` (Checkmark-Liste)
- **URL-State** — Demo speichert alle Einstellungen als URL-Hash (`#layout=modern&color=blue&...`); Link ist direkt teilbar und wiederherstellbar
- **pipeline.sh** — Erweiterte CSS-Klassen-Referenz: Nav-Stile (`nav-dark`, `nav-colored`, `nav-glass`), Footer-Stile (`footer-minimal`, `footer-colored`), Sektionen (`.section-veil`, `.section-alt`), Stats, Reveal
- **config.json** — Vollständig dokumentierte Optionen: `navStyle`, `footerStyle`, `spacing`, `animation` inkl. Branch-Hints; 11 Farben, 8 Fonts, neue Hero-Varianten

### Summary
Pipeline-Generator kennt jetzt alle CSS-Klassen des aktuellen Endoskeletons.
Demo-Links sind per URL teilbar — Layout, Farbe, Font, alle Switcher im Hash.

---

## [3.11.0] — 2026-04-03

### Added
- **Spacing-Presets** (`spacing/`): Compact, Normal, Spacious — steuern `--space-*` Variablen
- **Animation-Presets** (`animation/`): None, Subtle, Dynamic — steuern Scroll-Reveal-Verhalten
- **5 neue Farben** (`colors/`): Brown, Forest, Gold, Pink, Gray — insgesamt 11 Farbpaletten
- **3 neue Schriften** (`fonts/`): Elegant (Playfair+Lato), Mono (JetBrains), Rounded (Nunito) — insgesamt 8 Fonts
- **Export-Button** in Demo-Sidebar — zeigt aktuelle Einstellungen als JSON-Snippet (Klick kopiert)
- **3 neue Demo-Sektionen**: Testimonials (3 Cards), FAQ (Accordion), Gallery (Projekt-Karten)
- Demo-Sidebar: Dichte-, Animation-, Footer-Stil-Switcher

### Summary
Endoskeleton bietet nun ein Konfigurations-Spektrum von **~45.000 Kombinationen**:
3 Layouts × 5 Heroes × 11 Farben × 3 Softness × 3 Spacing × 3 Animation × 8 Fonts × 8 Buttons × 4 Nav-Behavior × 4 Nav-Style × 4 Footer-Style × 2 Themes

---

## [3.10.0] — 2026-04-03

### Added
- **Footer-Stile**: 4 Varianten via CSS-Klassen auf `.site-footer`:
  - `dark` (Standard) — dunkler Hintergrund
  - `light` (`site-footer--light` / `footer-light`) — heller Hintergrund
  - `minimal` (`footer-minimal`) — zentriert, kompakt, nur Logo + Tagline
  - `colored` (`footer-colored`) — Primärfarbe als Hintergrund
- Demo-Sidebar: Footer-Stil-Buttons (Dark/Light/Minimal/Color)

---

## [3.9.0] — 2026-04-03

### Added
- **Nav-Stil**: 4 visuelle Varianten — `light` (Standard), `dark`, `colored`, `glass`
  - CSS-Klassen: `nav-dark`, `nav-colored`, `nav-glass` auf `.site-nav`
  - Konfigurations-Key: `navStyle` in `SITE_CONFIG`
- **Shrink**-Behavior: Nav wird beim Scrollen kleiner (Höhe + Schrift)
- Demo-Sidebar: Nav-Stil-Buttons (Light/Dark/Color/Glass) + Shrink-Option

### Fixed
- **Transparent-Bug**: `.scrolled` hat nun `:not(.nav-transparent)` — wird nicht mehr weiß bei 20px Scroll
- **SITE_CONFIG undefined**: `window.SITE_CONFIG` wird im Demo-Script initialisiert bevor es verwendet wird
- Nav-Wechsel im Demo wirkt sofort (via `window._applyNavState()`) ohne auf Scroll zu warten

---

## [3.8.0] — 2026-04-03

### Changed
- **Demo: Toolbar → Sidebar** — Demo-Controls von oben nach rechts verschoben (fixed, aufklappbar)
- Echter `#site-header` ist jetzt sichtbar — Header/Nav-Verhalten live testbar
- Sidebar hat Tab-Button zum Ein-/Ausklappen

---

## [3.7.0] — 2026-04-03

### Added
- **Nav Behavior** — 3 Modi via `SITE_CONFIG.navBehavior`:
  - `sticky` (Standard) — immer sichtbar, Schatten ab 20px Scroll
  - `autohide` — versteckt sich beim Runterscrollen (`transform: translateY(-100%)`), kommt beim Raufscrollen zurück
  - `transparent` — startet transparent über Hero (Logo/Links weiß), wird solid nach 80px Scroll
- **Demo-Toolbar** — neues Nav-Dropdown zum Live-Wechseln zwischen den 3 Modi
- `config.json` — `navBehavior` als Option mit `_navBehavior_hints` eingetragen

---

## [3.6.0] — 2026-04-03

### Added
- **`.section-veil`** + **`.section-veil-bg`** — Schleier-Hintergrund für beliebige Sektionen (nicht nur Heroes); Kontaktseite nutzt das automatisch
- **`.form-row`** — 2-spaltig für Vorname/Nachname, responsive → 1-spaltig unter 48rem
- **`.form-checkbox`** — Checkbox + Label inline (Newsletter)

### Fixed
- **`.contact-info`** — `bg-alt` durch `card-bg` + `shadow-md` + `align-self:start` ersetzt; Card war auf Veil-Hintergrund unsichtbar
- **`form-row`** margin-bottom fehlte — Zeile klebte am nächsten Feld
- **`.contact-info p`** Spacing zu groß; jetzt kompakt wie Visitenkarte
- **Demo Kontakt-Section** — Inline-CSS entfernt, `section-veil` + korrekte Klassen
- **kontakt.html** — Vorname/Nachname mit `.form-row`, Kontaktdaten mit `.contact-info`
- **Versionen** — `demo.html` Titel und `base.css` Header auf aktuelle Version synchronisiert

---

## [3.5.0] — 2026-04-03

### Added
- **`.hero--split-veil`** — Split-Variante mit sanftem Bild-Fade zur Textseite statt hartem Schnitt; modernerer, luftigerer Look
- **`.hero--minimal-veil`** — Minimal-Variante mit dezenter Hintergrundbild-Unterstützung; Bild nur als Hauch sichtbar (inspiriert von survivaltraining.at)
- **Scroll-Indikator für Split** — `.hero-scroll` Element analog zu fullscreen/veil

### Fixed
- **`veil.css` Schleier-Farbe** — Hardcoded Warm-Weiß `rgba(245,242,235,...)` durch `color-mix(in srgb, var(--bg) X%, transparent)` ersetzt; respektiert jetzt helle und dunkle Themes

---

## [3.4.0] — 2026-04-03

### Added
- **5 Font-Presets** — `fonts/` Verzeichnis: modern (Inter), system (nativ), serif (Playfair Display), humanist (Nunito), editorial (Cormorant Garamond)
  - `_font_hints` in config.json für automatische Zuordnung nach Branche
- **Zartheitsgrad (Softness)** — 3 Presets: `raw` (kantig, keine Schatten), `balanced` (Standard), `soft` (primärfarb-getönte Schatten, Pill-Buttons)
  - Beeinflusst: Cards, Buttons, Hero-Overlay, Sections, Banner, Blockquote, Divider
- **Banner Hero** — kompakter 52vh Hero-Typ (inspiriert von em-gemeinschaft.ssi.at)
- **Kontaktformular-Sektion** — Grid-2 Layout mit Formular + Kontaktdaten-Card
- **Font-Dropdown** + **Softness-Dropdown** in Toolbar (Zeile 2)
- 8.640 visuelle Kombinationen: 3 Layouts × 4 Heroes × 6 Farben × 8 Buttons × 3 Softness × 5 Fonts

### Fixed
- **Ghost-Button Lesbarkeit** — auf Hero-Bildern mit Backdrop-Blur + semi-transparentem Hintergrund
- **Kontaktformular Styling** — `form-group` statt nicht-existierender `form-field` Klasse

---

## [3.3.0] — 2026-04-03

### Added
- **8 Button-Presets** — `buttons/` Verzeichnis: rounded, soft, sharp, outline, bold, ghost, tech, tactical
  - `tech.css` — abgeschrägte Ecken (clip-path chamfer), inspiriert von ssi.at
  - `tactical.css` — Parallelogramm-Form, inspiriert von survivaltraining.at
- **Demo Toolbar 2-zeilig** — Zeile 1: Layout/Hero/Farbe · Zeile 2: Buttons/Theme/Sprache
- **Button-Select** — Dropdown für 8 Preset-Stile in Toolbar
- **config.json** — Generator-Konfigurationsschema: layout, hero, color, buttons
- 288 visuelle Kombinationen: 3 Layouts × 3 Heroes × 6 Farben × 8 Button-Stile

---

## [3.2.0] — 2026-04-03

### Added
- **6 Farb-Presets** — `colors/` Verzeichnis mit green, blue, orange, teal, red, violet
- **Farb-Switcher in Demo** — live wechselbar via Toolbar-Punkte
- 54 visuelle Kombinationen: 3 Layouts × 3 Heroes × 6 Farben

### Fixed
- **Magazine Layout** — komplette Überarbeitung: flache Cards mit Akzent-Border, eckige Buttons, editoriales Spacing
- **section-dark Bug** — CTA-Section in Magazine und Classic hatte weißen Text auf hellem Hintergrund
- **Stats-Banner** — kein Rahmen mehr, nur Trennlinien + Akzent-Linie oben
- **Demo Toolbar** — CSS Cache-Buster verhindert Cloudflare-Cache-Probleme
- **Weißer Rand** — Toolbar sticky, site-header ausgeblendet, body-padding 0

---

## [3.1.0] — 2026-03-31

### Added
- **3 Layouts**: Modern, Classic, Magazine
- **3 Hero-Stile**: Fullscreen, Split, Minimal
- **Dark Mode** via `data-theme="dark"`
- **i18n** DE/EN via `data-i18n` Attribute
- **Demo-Seite** auf skeleton.ssi.at
- **shared.js** generiert Nav, Footer, Cookie-Banner dynamisch

### Fixed
- Magazine masonry Bug (falsche nth-child Selektor)
- Classic serif Schrift für section-title

---

## [3.0.0] — 2026-03-28

### Added
- Initiales Endoskeleton Template
- base.css mit CSS Custom Properties
- Layout-System (layout-* Klassen auf body)
