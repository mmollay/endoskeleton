# Changelog — SSI Endoskeleton

Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)

---

## [5.0.6] — 2026-04-06

### Improved
- **Sidebar UX**: Aktives Preset mit blauem Hintergrund + Checkmark hervorgehoben. Preset wird bei URL-Hash-Load korrekt markiert.
- **Variation-Counter** kompakter und dezenter (grau statt prominent blau).
- **Tab-Navigation**: Aktiver Tab in blau statt nur fett, besser erkennbar.
- **Preset-Beschreibungen** kürzer und mit tatsächlichen Font-Namen (z.B. "Bebas Neue" statt "Cinematic Display").
- **Preset-Reihenfolge** optimiert: Survival und Corporate zuerst (meistgenutzt).

## [5.0.5] — 2026-04-06

### Fixed
- **Nav-Font professioneller**: Nav-Links nutzen jetzt var(--font-body) statt System-Font. Logo nutzt var(--heading-weight). Nav-CTA mit expliziter font-family.
- **Button-Kontrast**: Secondary-Buttons mit stärkerer Border (0.125rem statt 0.0625rem). Dark-Theme Buttons mit expliziter Sichtbarkeit.
- **CTA-Arrow Buttons**: Primary hat jetzt Solid-Background statt nur Text. Secondary mit Underline-Differenzierung.
- **Feature-Cards Dark Themes**: Cards haben jetzt sichtbare Borders auf allen Dark-Themes (tech/dark/midnight). Card-Icons mit stärkerer Border (1.5px, 25% opacity).
- **Minimal NavLayout**: Zeigt jetzt Links auf Desktop (minimalistisch, uppercase, 0.8rem) statt nur Hamburger.
- **Luxury-Thin Font**: Heading-Weight von 400 auf 600 erhöht für bessere Lesbarkeit. Display-Weight von 300 auf 400.
- **Outdoor Preset**: Differenzierung von Survival — jetzt classic-serif Font + shadow-lift Buttons statt brand-bold + rect-solid.


## [5.0.0] — 2026-04-05

### BREAKING
- **Preset-System komplett umgebaut** — Presets sind jetzt pure JS-Config-Objekte (PRESETS in demo.html) statt CSS-Dateien. Jedes Preset definiert 15 Parameter (layout/hero/color/charakter/spacing/animation/font/buttons/navBehavior/navLayout/navStyle/footerStyle/width/theme). Legacy `presets/*.css` Dateien entfernt.
- **CSS-Ownership neu geordnet:** Buttons owned ALL button styles. Softness owned cards/shadows/chrome (keine .btn rules mehr). Layouts owned structural layout only (keine .btn rules mehr). Fonts owned typography inkl. hero-title signature (text-transform, letter-spacing).

### Added
- **12 Font-Module** (fonts/): brand-bold, cinematic, classic-serif, editorial-grand, geometric-black, luxury-thin, magazine, mono-hacker, rounded-friendly, softcraft, swiss-precision, tech-display. Jedes Modul self-contained mit @import + CSS vars + hero-title signature.
- **6 Button-Module** (buttons/): brutalist, cta-arrow, minimal-link, pill-thin, rect-outline, rect-solid. Eigene Ownership, keine Konflikte mit softness/layouts.
- **3 neue Presets:** cinematic, softcraft, startup (zusammen 12 Presets total).
- **URL-Hash-Preset-System:** `#preset=X` expandiert zu allen 15 Parametern via restoreUrlState(). Hash-Params überschreiben einzeln (`#font=X&buttons=Y`).

### Fixed
- **Survival-Preset matches survivaltraining.at 1:1:** Cream-Veil-Overlay auf Wald-Hero, Opaque Nav (solid beige), Oswald uppercase H1, grünes WEB Accent, sharp rect-solid Buttons, keine parallelogram tactical Buttons mehr.
- **shared.js theme-init race condition:** initThemeToggle überschrieb data-theme aus restoreUrlState mit localStorage/prefersDark. Fix: `hasAttribute('data-theme')` Check erhält preset-theme.
- **softness/*.css:** Alle .btn Rules + .hero-overlay Overrides entfernt (5 Dateien).
- **layouts/*.css:** Alle .layout-X .btn {...} Rules per Python-Regex entfernt (3 Dateien).
- **buttons/pill-thin.css:** Outline-Contrast auf warmen Themes (1.5px border rgba primary 0.45 + color: var(--heading)).

### Removed
- 8 Legacy Preset-CSS-Dateien: `presets/{corporate,creative,editorial,elegant,minimal,playful,survival,tech}.css`

---

## [4.6.10] — 2026-04-05

### Changed
- **PRESETS.survival** — `buttons: 'sharp'` → `buttons: 'tactical'`. Tactical hat das exakte survivaltraining.at-Button-Profil: Primary-Buttons sind Parallelogramme (clip-path angled sides, `padding: 2.2rem`, `letter-spacing: 0.18em`, uppercase), Ghost-Buttons haben rechteckige 1px zarte Rahmen `rgba(primary, 0.4)`. Sharp war zu generisch (nur border-radius:0). Visual-Match zum Referenz-Design jetzt 1:1.

---

## [4.6.9] — 2026-04-05

### Fixed
- **heroes/playful.css — Wolken/Blob-Deko entfernt:** 4 decorative pseudo-elements (2 White-Cloud radial-gradients auf `::before`/`.hero-bg`, 2 Peach organic Blobs auf `.hero-content::before/::after` mit `blobMorph` Animation) rendern auf light themes (sunset/warm/pastel) wie broken Placeholder-Rechtecke: graue Kästen top-left, peach Kreise die zufällig wirken. Entfernt. Nur die 3 SVG-Hügel-Layer bleiben — ergeben saubere "Landschafts"-Welle am Hero-Boden.
- **Dark-Overlay-Regression:** Als Seiten-Effekt der Wolken-Entfernung zeigte `.hero-bg::after` (aus base.css:998: `rgba(0,0,0,0.45)` overlay für Hero-Image-Sections) einen 45% schwarzen Vollflächen-Overlay über dem playful Hero an, obwohl kein Image da war. Fix: `.hero--playful .hero-bg { display: none !important }`. Hero-BG DIV bleibt im DOM aber rendert nicht mehr.

---

## [4.6.8] — 2026-04-05

### Fixed
- **KRITISCHER BUG: Sections unsichtbar für 6s** (Regression aus v4.6.4): Das CSS-Safety-Net war auf `6s forwards` gesetzt — bei `hero=fullscreen` + `spacing=spacious` sind alle Body-Sections below-fold. IntersectionObserver feuert zwar, aber für einen kurzen Moment nach Page-Load sind sie opacity:0. Bei Martins Test erschien der Hero + direkt Footer, dazwischen leere Cream-Fläche. Fix: Safety-Net-Delay von 6s auf 0.3s gesenkt + Duration 0.5s. Content wird garantiert innerhalb von 0.8s sichtbar, auch ohne Scroll.
- **.nav-cta ignorierte Button-Preset Border-Radius:** `.nav-cta` hatte `border-radius: var(--radius-full) !important` hardcoded — alle Button-Presets mit angular shape (sharp/tactical/tech/bold) wurden vom nav-cta NICHT übernommen, CTA-Button blieb pill-shaped. Fix: `.nav-cta` liest jetzt `var(--cta-radius, var(--radius-full))` — Fallback ist rund, aber Button-Presets setzen eigenen Wert:
  - sharp/tactical/tech → `--cta-radius: 0` (angular)
  - bold/ghost/outline/outline-thin/outline-elegant → `--cta-radius: 4px` (leichte Rundung)
  - rounded/soft/pill → bleibt var(--radius-full) (rund)

---

## [4.6.7] — 2026-04-05

### Added
- **tests/preset-qa.mjs** — Playwright-basiertes WCAG-AA-Build-Gate. Testet alle 9 Presets auf Kontrast-Konformität. Nutzt **Pixel-Sampling** (statt naivem DOM-walkup): versteckt Text-Color kurz, screenshotet die Element-BBox, mittelt RGB der sichtbaren Hintergrund-Pixel. Funktioniert robust bei Hero-over-Image, Gradient-Overlays, ::after-Pseudo-Elementen und transparenten Cards. Fällt zurück auf alpha-aware DOM-walk wenn Pixel-Sample fehlschlägt. Exit-Code 1 bei FAIL, 0 bei PASS. Output: `tests/qa-report.md` mit Tabellen je Preset.
- **tests/README.md** — Usage-Doku: `npm run qa` oder `node tests/preset-qa.mjs`.
- **package.json** — Scripts + devDependencies (`playwright`, `pngjs`).
- **.gitignore** — `tests/screenshots/` + `tests/qa-report.md` (Artifacts, werden bei jedem Run neu erzeugt).

### Verified
Alle 9 Presets PASS: corporate 12-16:1 / editorial 12-17:1 / minimal 11-17:1 / tech 4-17:1 / creative 4-16:1 / outdoor 12-15:1 / playful 10-16:1 / elegant 10-14:1 / **survival 3.64-16.10:1** — alle ≥ WCAG AA (4.5:1 body, 3:1 large).

---

## [4.6.6] — 2026-04-05

### Added
- **9. Preset: `survival`** — Direkte survivaltraining.at-Nachbildung. Cream-BG (theme=warm `#f5f2eb`), Oswald+Source-Serif Typography (font=survival), Survival-Grün `#2d7a2d` (neuer `colors/survival.css`), sharp angular border-radius:0 Buttons, fullscreen Hero mit Dark-Overlay, transparent Nav. Löst Martins Beschwerde #4 "nicht wie survivaltraining.at" — jetzt 1:1 Design-Referenz als Preset nutzbar.
- **colors/survival.css** — Ember-Green Palette: primary `#2d7a2d`, dark `#1e5c1e`, light `#4a8c3f`. RGB-Tokens für rgba-Transparenzen.
- **base.css Harmonielehre** — `body[data-color="survival"]` farbgetönter Text `#253525` / Heading `#0f2010` (forest-ähnlich, dunkel mit Grünstich).
- **demo.html** — Sidebar Color-Swatch für Survival Green + PRESET_META-Eintrag "Outdoor · Grün · Oswald + Source Serif".

---

## [4.6.5] — 2026-04-05

### Added
- **shared.js Lang-Switch** — Universeller DE/EN-Toggle in der Site-Nav (konfigurierbar via `SITE_CONFIG.languages = ['de','en']`). Vorher war der Toggle nur in der Demo-Sidebar versteckt — für User auf der generierten Seite unsichtbar. Jetzt permanent in der Nav, themefähig (nutzt `--primary`/`--text-muted`/`--border`), Tastaturbedienbar (`aria-pressed`, `focus-visible`), responsive (verkleinert auf Mobile, wird NICHT ausgeblendet).
- **shared.js `sssi:langchange`** — Custom Event das bei Sprachwechsel feuert (Detail: `{lang}`). Host-Pages können Listener registrieren. Zusätzlich wird `window.applyLang(lang)` aufgerufen falls definiert.
- **shared.js Lang-Priorität** — URL-Hash (`?lang=en`) > localStorage.siteLang > `<html lang>` > `'de'`. Wählt bei Page-Load automatisch die richtige Sprache.
- **base.css `.nav-lang` + `.nav-lang-btn`** — Kompakte Segment-Control-Optik, active-Button in Primary-Farbe, Hover-State, Mobile-angepasst.
- **demo.html** — `applyLang` als `window.applyLang` global exponiert, damit shared.js den Demo-Translator ansteuern kann.

---

## [4.6.4] — 2026-04-05

### Changed
- **base.css .reveal** — Animation-System wiederbelebt: opacity-Fade (0→1) zurück im Standard-`.reveal`, kombiniert mit translateY(1.25rem) und 0.6s ease. v4.6.0 hatte opacity entfernt ("Contrast-Schutz") was die Seiten statisch wirken ließ. Neu: CSS-Safety-Net `@keyframes reveal-safety-net` garantiert Sichtbarkeit nach 6s falls IntersectionObserver nicht feuert — kein Risiko unsichtbaren Texts.
- **base.css Hero-Stagger** — 4-stufiges on-load fadeUp auf `.hero-eyebrow` (0.2s), `.hero-title` (0.4s), `.hero-subtitle` (0.6s), `.hero-ctas` (0.8s) delays. Entspricht dem survivaltraining.at-Referenzmuster. `animation-fill-mode: both` stellt Endzustand sicher.
- **base.css reduced-motion** — `@media (prefers-reduced-motion: reduce)` deaktiviert alle Animationen und Transitions für Accessibility.
- **animation/dynamic.css** — Verstärkt: cubic-bezier(0.22,1,0.36,1) easing, translateY(2.5rem)+scale(0.98) transform, 0.8s duration, Hero-Animationen 1.0-1.1s mit sanftem Bounce-Ease. 4 stagger-delays (-1 bis -4).
- **animation/subtle.css** — Kommentar aktualisiert (nutzt neue base.css-Defaults mit opacity).

---

## [4.6.3] — 2026-04-05

### Fixed
- **base.css** — Harmonielehre-Regression auf dunklen Themes: `body[data-color=X]` (v4.5.2) setzt farbgetönte dunkle `--text`/`--heading`-Werte, die auf dunklen Themes (dark/tech/midnight/forest) dark-on-dark-Kontrast erzeugen (tech-preset: 1.66:1, creative-preset: 1.36:1 — beide WCAG FAIL). Fix: Reset-Regeln `[data-theme="dark|tech|midnight|forest"] body[data-color]` stellen für dunkle Themes helle, theme-konforme Textfarben wieder her. Specificity 0,2,1 schlägt `body[data-color]` (0,1,1). Verifiziert: tech-preset 1.66:1 → 16.21:1 AAA, creative-preset 1.36:1 → 14.42:1 AAA.

---

## [4.6.2] — 2026-04-05

### Fixed
- **shared.js** — Theme-Switcher Race Condition: `initThemeToggle()` überschrieb URL-Hash-`theme`-Parameter mit System-`prefers-color-scheme` bzw. `localStorage.theme`. Dadurch ignorierten 6 von 8 Presets ihr deklariertes Theme und renderten dark-on-dark mit WCAG-Kontrast 1.0–1.2:1 (FAIL). Fix: URL-Hash hat jetzt höchste Priorität (Hash > localStorage > System), unterstützt alle 10 Themes (light/dark/warm/tech/pastel/earth/ocean/sunset/midnight/forest). `prefers-color-scheme`-change-Listener wird bei aktivem Hash-Theme deaktiviert, damit Preset-Links deterministisch rendern.

---

## [4.3.1] — 2026-04-05

### Changed
- **heroes/playful.css** — Playful Hero v2: SVG-Hügellandschaft mit 3 Tiefen-Layern ersetzt Wellen-Divider, schwebende CSS-Wolken (`::before`), Theme-Support für alle 10 Themes (dark, tech, warm, midnight, forest, ocean, sunset, pastel, earth). Animationen überarbeitet (`cloudFloat`, `blobMorph`). Responsive verbessert.

---

## [4.0.0] — 2026-04-04

### Changed
- **demo.html** — Komplett überarbeitetes Stil-Tab mit visuellen Pickern:
  - **Farb-Swatches**: 23 Farben als klickbare Kreise statt Dropdown
  - **Font-Previews**: Jede Schrift in ihrer eigenen Typeface gerendert (Aa Modern, Aa Serif, etc.)
  - **Button-Previews**: Jeder Button-Stil als Mini-Button mit echtem Styling (Pill, Ghost, Tech, etc.)
  - **Charakter-Visualisierung**: 5 Formen zeigen Rundung (Elegant→Kantig)
  - **Dichte + Animation**: Visuelle Buttons mit Icons statt Dropdown
- **Preset-System** — Erweitert auf 16 Built-in-Presets mit CSS-Mini-Previews:
  - Neu: Restaurant, Arzt, Fotograf, Immobilien, Startup, Agentur, Blog, Portfolio, Event, Wellness
  - Jedes Preset zeigt eine Mini-Vorschau (Nav-Balken, Hero, Content-Linien) mit echten Farben
- **Custom Presets** — Eigene Designs speichern/laden/löschen via localStorage:
  - "Aktuelles Design speichern" Button unter der Preset-Grid
  - Eigene Presets mit gestricheltem Rahmen, Lösch-Button auf Hover
  - Auto-generierte Beschreibung (Farbe · Font · Theme)
- Alle Google Fonts für Sidebar-Previews vorgeladen

---

## [3.19.9] — 2026-04-04

### Fixed
- **navlayouts/centered.css** — Nav-Overlap behoben: `justify-content: center` war auf `.site-nav` gesetzt (kein Flex-Container), muss auf `.nav-inner` (der tatsächliche Flex-Container). Dadurch starteten nav-links links auf gleicher Position wie das absolut positionierte Logo
- **navlayouts/club.css** — Gleicher Fix: `.site-nav` → `.nav-inner` für justify-content Regeln

---

## [3.19.8] — 2026-04-04

### Added
- **base.css** — Dropdown-Pfeile (Chevrons) für Submenü-Einträge via CSS `::after` Pseudo-Element
- **buttons/outline-thin.css** — Neues Button-Preset mit 1px-Linie für edlen, dezenten Look

### Fixed
- **buttons/outline.css** — Button-Bug behoben: Buttons wurden im Split-Hero unsichtbar (weiße Farbe auf weißem Hintergrund), weil die Hero-Kontrast-Regeln fälschlich auch für `.hero--split` galten

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
