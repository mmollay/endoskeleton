# Changelog — SSI Endoskeleton

Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)

---

## [3.67.0] — 2026-11-01

### Added
- **Bewegung: vierter Zeichenstil `figur` mit Ausdruck** — Anlass: Martin, 01.11.2026: „so in der Art hätte ich mir das mit der Motion Capture vorgestellt“. Der Stil zeichnet nur die Figur auf die vorhandene Leinwand (keine Bühne, kein Verlauf, kein Regen: den Grund liefert die Seite): großer dunkler Kopf, Gesicht nur aus zwei Lidern und einem Mundstrich, Kleidung in Flächen in der Markenfarbe (`color` der Leinwand), runde Gelenke, Hände als runde Formen, Schuhe, helle versetzte Randlinie wie ein Aufkleber, Schatten unter den Füßen. Die Gesichtspunkte steuern Kopfdrehung und Neigung; die Tiefenreihenfolge folgt `z`.
  - **Katalog:** Feld `stil` des Bausteins `bewegung` hat jetzt sechs Werte (`figur` am Ende); neues Feld `ausdruck` (`neutral` Vorgabe | `muede` | `ueberrascht` | `entschlossen`), nur bei `stil` = `figur` zulässig (sonst sperrend). Das Kit schreibt es als `data-lp-ausdruck` an die Figur (nur bei `figur`).
  - **Player `lp/bewegung.js`:** neue Optionen `ausdruck`, `zeit`, `ruhe` für `zeichne`, vierter Parameter `opt` für `spielen`; neu `LpBewegung.AUSDRUECKE` und `LpBewegung.blinzeln(ms)`. Ein unbekannter Ausdruck zeichnet `neutral`. Die Figur blinzelt gelegentlich (vier Schläge in 15,4 s, je 0,16 s; eine reine Zeitfunktion); bei „weniger Bewegung“ (`prefers-reduced-motion`) nie, auch nicht nach „Abspielen“.
  - **Kontrast** (gemessen und erzwungen): der Kopf ist fast schwarz und trägt auf hellem Grund allein (mindestens 3 : 1); auf dunklem Grund trägt die helle Randlinie um die ganze Figur (die Randfarbe wird auf mindestens 4,5 : 1 gezogen, verlangt sind 3 : 1); Kleid 4,5 : 1, Schuhe 4,5 : 1, Hose 3,2 : 1 gegen den Grund.
  - **Die Kamera-Vorführung liefert keinen Ausdruck:** `lp/bewegung-live.js` ist unverändert; die Körper-Aufnahme enthält keine Mimik (Gesichtspunkte sind gesperrt). Der Ausdruck kommt von der Seite.
  - **Die älteren Stile zeichnen Befehl für Befehl wie in 3.66.0:** Silhouette, Piktogramm, Körper, Strich, Punkte (Prüfsummen der Zeichenbefehle im Test, `tests/bewegung-zeichnen.js`).
  - **Größe des Players und Größenwächter (BW10):** 40 268 Byte, gzip 14 189 Byte (vorher 26 530 / 9 988). Der Stil bringt eine eigene Figur-Zeichnung (Kopf, Gesicht, Körperteile mit Tiefenreihenfolge, Randlinie) mit; deshalb steigt die Grenze von 28 KB auf 41 KB roh und von 10 KB auf 14,5 KB gzip (jeweils etwa 2 % Luft). Der Player wird von der Seite einmal geladen und liegt weiter unter 15 KB gezippt.
  - **Neue `lp-`-Klassen:** keine (die Figur nutzt `lp-bewegung-figur`; Gestaltung unverändert in `lp/lp.css`).
  - **Neue Dateien:** `lp/musterbuch/bewegung--wiedergabe--figur.json` und das gebaute `musterbuch/bewegung--wiedergabe--figur/`. Erzeugte Dateien (`lp/SKILL.md`, `lp/werkstatt.md`, Musterbuch) neu gebaut; `docs/bewegung-format.md` beschreibt Stil und Ausdruck.
  - **Tests:** `tests/bewegung-zeichnen.js` (127 Prüfungen: `figur` erkannt, vier Ausdrücke = vier verschiedene Zeichenfolgen, unbekannter Ausdruck = neutral, Lidschlag, `ruhe`, `spielen()`, Kontrast hell und dunkel, keine Bühne, Reinheit, Bestandsschutz byte-gleich), `BW14`/`BW16` in `tests/bewegung.test.php`, Browserprüfung in `ci/browser-bewegung.js` (887 Browserprüfungen bestanden; im Ausgangsstand 874 bestanden und eine Prüfung „Verlassen“ rot, die im neuen Lauf grün war und mit dem Stil nichts zu tun hat). Acht Gegenproben (Kontrastwächter, Ausdruck, unbekannter Ausdruck, `ruhe`, Rückfall auf die Vorgabe, Lidschlag-Uhr, Feldbedingung, Attribut) bestanden. Bestehende Prüfungen nicht abgeschwächt; nur die Listen der Werte wurden um `figur` ergänzt und die Größengrenzen angehoben.

### Rückweg
Den Zweig `lp/bewegung-figur` nicht zusammenführen oder die Zusammenführung zurücknehmen. Seiten ohne `stil` = `figur` ändern sich nicht (die älteren Stile zeichnen byte-gleich); eine Seite mit `figur` fiele im alten Player auf die Vorgabe `silhouette` zurück, der alte Prüfer des Kits lehnte sie beim Bauen ab.

---

## [3.66.0] — 2026-10-31

### Added
- **Bewegung: Baustein `bewegung`, Player, Format `.bewegung`, drei Figur-Stile (Silhouette Vorgabe, Piktogramm, Körper)** — Martin, 31.10.2026: die Bewegung (Motion Capture) kommt ins Kit. Eine Seite kann eine aufgezeichnete Körperbewegung als Figur abspielen, und Besucher können sich nach dem Klick selbst in der Kamera aufnehmen; alles läuft im Browser, ohne Fremdanfrage, ohne Kamerabild in einer Datei.
  - **Baustein `bewegung`** (Varianten `wiedergabe`, `live`; Katalog `lp/bausteine.json`, Bauer `lp/bausteine/bewegung.php`), Vorlage `lp/vorlagen/motion-capture.json`, Musterbuch `/musterbuch/bewegung--wiedergabe/`, `bewegung--wiedergabe--piktogramm`, `bewegung--wiedergabe--koerper` und `bewegung--live`. Das Feld `stil` ist eine Auswahl: `silhouette` (Vorgabe), `piktogramm`, `koerper`, dazu die älteren `strich` und `punkte`.
  - **Format `.bewegung`** („bewegung v1“, `docs/bewegung-format.md`; Prüfer `lp/src/Bewegung.php` und `lp/bewegung.js`): JSON in `bew/<name>.bewegung`, 33 Körperpunkte je Bild, höchstens 10 s und 300 000 Byte.
  - **Player `lp/bewegung.js`** (26 530 Byte, gzip 9 989 Byte, ohne Abhängigkeit; Grenze der Prüfung BW10: 28 KB / gzip 10 KB) und **Kamera-Vorführung `lp/bewegung-live.js`** (10 986 Byte, gzip 4 576 Byte; erst nach dem Klick geladen). Erkennung mit MediaPipe Pose Landmarker; die Modelldateien liegen **nicht** im Git, sondern werden mit `scripts/modell-holen.sh` geholt und gegen `scripts/modell-summen.txt` geprüft (`scripts/version-ablegen.sh` legt die Prüfsummenliste mit ab).
  - **Neue Klassen** (alle seit 3.66.0, Stil in `lp/lp.css`, Abschnitt BEWEGUNG, nur Tokens): `lp-bewegung` (Abschnitt), `lp-bewegung--wiedergabe` und `lp-bewegung--live` (Varianten), `lp-bewegung-text`, `lp-bewegung-figur`, `lp-bewegung-beschriftung`, `lp-bewegung-leiste`, `lp-bewegung-wahl`, `lp-bewegung-kamera`, `lp-bewegung-hinweis`, `lp-bewegung-meldung`.
  - **Ereignis `lp-bewegung:aufnahme`** und Attribut `data-lp-modell` für Seiten, die die Aufnahme selbst weiterverwenden.
  - **Tests:** neu `tests/bewegung.test.php` (BW01 bis BW15), `tests/bewegung-zeichnen.js`, `tests/bewegung-paritaet.js` und der Browsertest `ci/browser-bewegung.js`; angepasst `katalog`, `texte`, `musterbuch`, `maskierung`, `versionsordner`.

### Changed
- **Zusammenführung zweier Linien:** Die Bewegung (zuvor 3.64.0, 3.64.1 und 3.64.2) hing parallel an 3.62.0; die Hauptlinie ging inzwischen über 3.63.0 und 3.65.0 (Zähler-Vertrag v2) weiter. **Die Vorstufen 3.64.0 bis 3.64.2 waren nie in der Hauptlinie**; ihre Einträge stehen unten unverändert als Entstehungsgeschichte, ihre Zahlen gelten nicht mehr: der Baustein und alle neuen Klassen stehen im Katalog „seit 3.66.0“. Beide Linien sind vollständig erhalten (`statistik.js` und `lp/lp.js` des Zählers, `lp/bewegung*.js` und `lp/lp.css` der Bewegung).
- Die erzeugten Dateien (`lp/SKILL.md`, `lp/werkstatt.md`, Musterbuch) wurden nach dem Zusammenführen neu gebaut, nicht von Hand gemischt.

### Rückweg
Den Zweig nicht zusammenführen oder die Zusammenführung zurücknehmen. Bestehende Seiten bauen weiter mit dem Stand, mit dem sie gebaut wurden (kein Baustein `bewegung` in alten Plänen).

---

## [3.65.0] — 2026-10-30

### Added
- **Zähler-Vertrag v2, Teil 1: mehrfaches Verlassen und „da“** in `statistik.js` (Webseiten) und `lp/lp.js` (Landingpages) — Anlass: Martin, 30.10.2026, Statistik-Plan (Konzept SQ, Abschnitt 5, Punkte 1, 3 und 4; Abschnitt 6). Beide Zähler senden jetzt `v: 2`.
  - **`verlassen` bei jedem Verstecken des Tabs und jedem `pagehide`**, nicht mehr nur das erste Mal. `dauer` ist das Delta: ganze Sekunden sichtbarer Zeit seit dem letzten Senden (mindestens 1, alle Meldungen eines Seitenaufrufs zusammen höchstens 3600; ist die Stunde voll, kommt `dauer` 0 und nur die Tiefe wird weiter gemeldet), `tiefe` ist wie bisher der weiteste untere Fensterrand (Maximum über alle Meldungen). Nach einem `verlassen` steht die Uhr bis zur Rückkehr: Verstecken und `pagehide` hintereinander (jeder Seitenwechsel) senden genau eine Meldung. Bei `visible` läuft die Uhr neu, ohne neuen `aufruf`.
  - **Neue Art `da`**: Wird der Tab nach über 60 Sekunden Abwesenheit wieder sichtbar (auch aus dem Zurück-Zwischenspeicher), geht ein kleines `da` hinaus — derselbe Rahmen wie `aufruf` (`v`, `liste`, `art`, `pfad`, `ref`, `utm`, `breite`), aber ohne `dauer` und `tiefe`. Genau 60 Sekunden zählen nicht; ohne gezählten `aufruf` (nie sichtbar, Rückkehrseite einer Anmeldung) nie ein `da`. Abwesenheit wird wie die Sichtzeit mit `performance.now()` gemessen (keine Wanduhr); schläft das Gerät dazwischen, kann ein `da` ausbleiben.
  - `aufruf`, `formular` und `absenden` (Landingpages) bleiben je Seitenaufruf einmal. Seitenpfad (`pfad`) unverändert. **Keine Klicks** — die kommen mit Etappe 3.
  - Alle Schutzregeln unverändert: nur auf der eigenen Domain senden, Do Not Track, Global Privacy Control und `data-statistik="aus"` schalten ab, jeder Fehler wird verschluckt, kein Cookie, kein Web-Speicher, höchstens 2048 Byte je Nachricht (größter Fall mit Pfad, Verweiser und UTM am Anschlag: gemessen unter 2048 für alle vier Nachrichten).
- **Größe:** `statistik.js` 3958 → 4556 Byte (59 Zeilen), `lp/lp.js` 14446 → 15322 Byte. Keine neue `lp-`Klasse, kein neues Feld, kein neuer Baustein.
- Tests: `statistik` (27 Prüfungen mehr, mit `angebot` und `zusammenspiel` 3886 → 3913: Delta-Rechnung (auch gegen die Gesamtzeit abgegrenzt), Summe höchstens 3600, Tiefe als Maximum, Verstecken+pagehide einmal, `da` an der Grenze 60/61 s, `da` nur einmal, kein `da` ohne `aufruf` oder bei Do Not Track; Größe unter 4600 Byte), `angebot` und `zusammenspiel` (Quelltext von `lp.js`: `v: 2`, Art `da`, 60 s, Summe), Browsertests `ci/browser-statistik.js` (Abschnitt 8: Zählerprobe mit abgefangenem `sendBeacon` und vorgespulter Uhr) und `ci/browser.js` (Landingpage: zweites `verlassen` nach der Rückkehr, `da` bei 30 s und 61 s, ein zweites `da` nach der zweiten Abwesenheit, keines auf der Rückkehrseite).

### Changed
- `statistik.js`: Kopfkommentar auf den Vertrag v2 (Teil 1) gebracht; die Karte `gesendet` entfällt (der `aufruf` hängt an einem Merker). `lp/lp.js`: gleiche Änderung im Statistik-Block; sonst nichts. Musterbuch und `lp/SKILL.md`/`lp/werkstatt.md` neu gebaut.
- **Abwärtskompatibilität — wichtig:** Das Panel, das heute live läuft (`App\Services\Statistik\Treffer::lesen`), kennt nur `v: 1` und lehnt **jede** Nachricht mit `v: 2` ab (`aufruf` eingeschlossen; die Antwort bleibt 204, es wird nichts gezählt), und `art: 'da'` ist dort auch mit `v: 1` unbekannt. **Dieses Kit darf deshalb erst auf Webseiten und Landingpages, wenn das Panel (Bahn E1) `v: 2` und `da` annimmt** — sonst zählen diese Seiten nichts mehr. Webseiten, die `statistik.js` aus 3.63.0 eingebunden haben, bleiben bei `v: 1` und zählen weiter, bis die Datei ersetzt wird; Landingpages behalten ihre Marke, bis sie neu gebaut und veröffentlicht werden.
- **Version:** 3.64.0 (Bewegung) liegt auf einem Schwesterzweig (`lp/bewegung`, Tag `v3.64.0`) und enthält 3.63.0 nicht; dieser Zweig baut auf 3.63.0 auf und nimmt darum die nächste freie Nummer 3.65.0. Beim Zusammenführen beider Zweige stehen `VERSION`, `package.json`, `konfigurator.html`, `lp/bausteine.json`, der CHANGELOG-Kopf und das Musterbuch im Streit (3.65.0 gewinnt, Musterbuch neu bauen).
- **Rückweg:** Zweig `zaehler-v2-teil1` nicht mergen oder den Commit zurücknehmen (`git revert <Commit>`); 3.63.0 (`statistik.js` und `lp/lp.js` mit `v: 1`) bleibt unverändert, solange nichts ausgeliefert wurde.

---

## [3.64.2] — 2026-10-31

### Added
- **Bewegung: die Figur gibt es jetzt in drei Darstellungen, wählbar** (Anlass: Martin, nach der Vergleichsseite „noch mal C, aber wählbar“; Bahn BS). `silhouette` (Vorgabe: kräftige Figur mit Kontur, auch klein gut lesbar), `piktogramm` (ruhig, wie ein Notausgang-Schild) und `koerper` (Figur mit Tiefe, braucht etwas mehr Rechenzeit). `strich` und `punkte` bleiben gültig und zeichnen Befehl für Befehl wie vorher (Prüfsummen im Test). Ein unbekannter Wert fällt auf `silhouette` zurück, nie ein Fehler im Browser.
- **Katalog: das Feld `stil` des Bausteins `bewegung` ist eine Auswahl** (`silhouette`, `piktogramm`, `koerper`, `strich`, `punkte`; Vorgabe `silhouette`, vorher `strich`); das Kit schreibt es als `data-lp-stil`, der Prüfer lehnt jeden anderen Wert ab. `lp/SKILL.md`, `lp/werkstatt.md` und das Musterbuch sagen, wann man welche Darstellung nimmt; neu im Musterbuch: `bewegung--wiedergabe--piktogramm`, `bewegung--wiedergabe--koerper`.
- **Farben ohne neue Einstellung:** der Player liest die Figurfarbe (`color` der Leinwand) und den Hintergrund (`background-color` der Leinwand oder des nächsten Elternelements) und mischt daraus Kontur, Verlauf, Schatten und Lücke; die tragende Farbe hat gegen den Grund mindestens 3 : 1. Keine neue CSS-Variable, kein neues Token, keine neue `lp-`-Klasse (kein Eingriff in `lp/lp.css`). Neu: `LpBewegung.farben(stil, farbe, grund)`, `LpBewegung.STILE`, `LpBewegung.VORGABE`; `zeichne` kennt die Option `grund` (ohne Angabe gilt `schatten`). Aufruf und Rückgabe von `zeichne` sind sonst unverändert (ein Short-Renderer kann sie weiter so rufen).
- **Doku:** `docs/bewegung-format.md`, Abschnitt „Darstellungen der Figur“.

### Changed
- **Player `lp/bewegung.js` wächst** von 11,2 KB (gzip 4,5 KB) auf 26,6 KB (gzip 9,9 KB): drei Zeichenstile samt Farbrechnung; Grenzen in BW10 auf 28 KB / gzip 10 KB angehoben.
- **Test `texte`:** die Wertelisten der Auswahlfelder (`werte`) zählen nicht mehr als Text; sie sind technische Namen wie `vor_ort` und jetzt `koerper` (der Wert steht in `data-lp-stil`, nie auf der Seite sichtbar; die Prüfliste für ASCII-Umlaute bleibt unverändert).
- **Tests:** BW01/BW05/BW06 angepasst (neue Werte, neue Vorgabe), neu BW14 (Katalogfeld, Bau, Skill, Werkstatt, Musterbuch) und BW15 (ruft `tests/bewegung-zeichnen.js`: aufzeichnender Kontext, drei Stile, Bestandsschutz, Rückfall, Farbregeln); `ci/browser-bewegung.js` prüft im Browser alle fünf Darstellungen (Pixel, Unterschied, Rückfall, Spiegeln, Pause, Tempo, Standbild, hell/dunkel, 1280/390 Punkte, zwei Markenfarben, keine Fremdanfrage).

## [3.64.1] — 2026-10-30

### Added
- **Kamera-Vorführung („bewegung“, Variante `live`): die fertige Aufnahme geht als Ereignis an die Seite** (Anlass: ein Verwaltungsbereich soll die Aufnahme ohne Umweg über den Download-Ordner ablegen; Bahn S0). Am Ende einer Aufnahme (nach „Stopp“ oder nach der Höchstdauer von 300 Bildern), wenn sie den Formatprüfer besteht, sendet `lp/bewegung-live.js` am `<figure class="lp-bewegung-figur">` ein `CustomEvent` **`lp-bewegung:aufnahme`** (`bubbles: true`, `detail: { text }`). `text` ist genau die Datei, die „Als Datei speichern“ schreibt. Bei „keine Person erkannt“, bei einem Fehler und beim Abbruch kommt kein Ereignis. **Ohne Fänger ändert sich nichts**: der Knopf „Als Datei speichern“ bleibt, das Kit sendet weiterhin nichts selbst.
- **Optionales Attribut `data-lp-modell="<Ordner>"` am `<figure>`:** Ordner der Modelldateien (MediaPipe-Modell und WASM), wenn er nicht `../modell/` neben dem Skript ist. Erlaubt sind nur gewöhnliche Pfade derselben Herkunft aus `A–Z a–z 0–9 . _ ~ / -`, relativ zur Seite oder absolut; alles andere (`javascript:`, `data:`, `https:`, `//host/…`, `\`, `?`, `#`, `%`-Codes, Leerraum) wird still verworfen, und es gilt der Standard. Ohne das Attribut bleibt alles wie in 3.64.0. Der Baukasten schreibt es nicht selbst (kein Katalogfeld); es ist für eigene Seiten gedacht.
- **Neue Funktion `modellordner(wert, seite, standard)`** (Export von `lp/bewegung-live.js`, rein, ohne Browserzugriff) für die Pfadregeln; das Modell wird je Ordner einmal geladen (vorher: einmal je Seitenaufruf).
- **Doku:** `docs/bewegung-format.md`, Abschnitt „Aufnahme an die eigene Seite übergeben“ mit einem Fänger in sechs Zeilen. Keine neue `lp-`-Klasse, keine neue Abhängigkeit.

### Changed
- **Baustein „bewegung“ ist als „geändert 3.64.1“ vermerkt** (nur das Live-Skript; Katalogfelder, Aufbau und CSS sind unverändert). `lp/SKILL.md`, `lp/werkstatt.md` und das Musterbuch sind neu gebaut (Versionsmarke, `es/bewegung-live.js`).
- **Tests:** neu `BW13` in `tests/bewegung.test.php` (Quelltext, Pfadregeln von `modellordner()` mit 27 Werten über node, Doku); `ci/browser-bewegung.js` prüft im Browser: Ereignis nach „Stopp“ (genau einmal, am `<figure>`, steigt auf, Text besteht JS- und PHP-Prüfer und ist gleich dem Download), nach der Höchstdauer, nicht bei ungültiger Aufnahme, ein werfender Fänger stört nicht, `data-lp-modell` (relativ, absolut, ohne Schrägstrich am Ende) bestimmt die Pfade für Skript, WASM und Modell, neun unsichere Werte werden verworfen, ohne Attribut gilt `../modell/`.

## [3.64.0] — 2026-10-30

### Added
- **Bewegung (Motion Capture), Stufe 1: eine Seite kann eine aufgezeichnete Körperbewegung als Strichfigur abspielen — und Besucher können sich selbst in der Kamera aufnehmen** (Anlass: Martin, Bahn MC). Alles läuft im Browser, ohne Fremdanfrage, ohne Kamerabild in einer Datei. Vier Teile:
- **Datenformat „bewegung v1“** (`docs/bewegung-format.md`, Prüfer `lp/src/Bewegung.php` und `lp/bewegung.js`): JSON in `bew/<name>.bewegung` (Endung nicht `.json`, weil der Sammelordner `*.json` als 404 sperrt). 33 Körperpunkte je Bild (x, y, Sichtbarkeit), 10–60 Bilder/s, 2–600 Bilder, höchstens 10 s und 300 000 Byte, höchstens 3 Nachkommastellen. Pflichtfeld `quelle` (`eigene-aufnahme`, `einwilligung`, `synthetisch`). Gesichtspunkte (Augen, Mund) sind immer `0,0,0`; Kamerabilder, Namen und Personendaten gibt es nicht. PHP und JS haben dieselben Regeln und dieselben Fehlerkennungen (Test vergleicht beide über eine gemeinsame Fall-Liste). Datenschutz-Textvorschlag steht in der Dokumentation (kein Rechtsrat).
- **Player `lp/bewegung.js`** (11,2 KB, gzip 4,5 KB, ohne Abhängigkeit): `LpBewegung.zeichne(…)` ist eine reine Zeichenfunktion ohne Uhr (Strichfigur oder Punkte, Farben aus dem CSS der Leinwand, Spiegeln, durchsichtiger oder dunkler Grund), `LpBewegung.spielen(…)` fügt Schleife, Pause und Tempo hinzu. Bei `prefers-reduced-motion` ein Standbild mit „Abspielen“, Tastatur (Leertaste, Pfeile, Home), `alt` als Bildunterschrift. Fehler werden verschluckt und enden in einem Hinweis.
- **Kamera-Vorführung `lp/bewegung-live.js`** (9,6 KB, gzip 4,0 KB): wird erst nach dem Klick auf „Kamera starten“ geladen (der Player importiert es dann); erst danach fragt der Browser nach der Kamera. Erkennung mit MediaPipe Pose Landmarker (`@mediapipe/tasks-vision` 0.10.35, Modell `pose_landmarker_lite`, beides Apache-2.0), **Modell und WASM liegen im Seitenordner unter `modell/`, kein CDN**. Das Kamerabild bleibt auf dem Gerät und wird nur auf die Leinwand gezeichnet (das `<video>` hängt nie im Dokument); kein Cookie, kein `localStorage`/`sessionStorage`. „Aufnehmen“ legt höchstens 300 Bilder an, „Als Datei speichern“ ist ein Download auf das Gerät des Besuchers. Ohne Kamera, Erlaubnis, WebGL oder WASM: Hinweis und die Beispielbewegung läuft weiter. Die Kamera geht beim Stopp, beim Tab-Wechsel und beim Verlassen der Seite aus. `scripts/modell-holen.sh` lädt Paket und Modell mit fester Version und Prüfsumme (sha512 des Pakets, SHA-256 je Datei aus `scripts/modell-summen.txt`) in einen Ordner **außerhalb** des Kits; die ~17,5 MB gehören nicht ins Git.
- **Baustein `bewegung`** (Varianten `wiedergabe`, `live`; Katalog `lp/bausteine.json`, Bauer `lp/bausteine/bewegung.php`): Felder `titel`, `text`, `bewegung` (Pflicht, Pfad `bew/….bewegung`), `alt` (Pflicht, wird auch als Bildunterschrift gezeigt), `stil` (`strich`|`punkte`), `knopf_text` (nur `live`). Vorlage `lp/vorlagen/motion-capture.json`, Musterbuch `/musterbuch/bewegung--wiedergabe/` und `/bewegung--live/`, Beispieldatei `lp/musterbuch/bew/beispiel-winken.bewegung` (synthetisch, erzeugt von `ci/bewegung-beispiel-bauen.php`). `lp/SKILL.md` und `lp/werkstatt.md` neu erzeugt.
- **Neue Klassen** (alle seit 3.64.0, Stil in `lp/lp.css`, Abschnitt BEWEGUNG, nur Tokens, keine festen Farben): `lp-bewegung` (Abschnitt), `lp-bewegung--wiedergabe` und `lp-bewegung--live` (Varianten), `lp-bewegung-text` (Titel und Text), `lp-bewegung-figur` (Rahmen der Leinwand mit Bildverhältnis aus der Datei), `lp-bewegung-beschriftung` (der `alt`-Text unter der Leinwand), `lp-bewegung-leiste` (Pause, Spiegeln, Tempo), `lp-bewegung-wahl` (Auswahlfelder „Tempo“ und „Darstellung“: Skelett über dem Kamerabild oder auf dunklem Grund), `lp-bewegung-kamera` (Block mit Kamera-Knopf, erst nach geladener Beispieldatei sichtbar), `lp-bewegung-hinweis` (Datenschutz-Hinweis: das Kamerabild bleibt auf dem Gerät und wird weder gespeichert noch übertragen) und `lp-bewegung-meldung` (Status: lädt, Aufnahme, fertig, keine Kamera, leer, Fehler; `aria-live`).

### Changed
- **Prüfer:** Im Seitenordner sind `bew/` (nur genutzte `.bewegung`-Dateien, jede wird mit dem Formatprüfer gelesen) und `modell/` (nur die acht Dateien aus `scripts/modell-summen.txt`, jede mit richtiger Prüfsumme; sonst sperrend) erlaubt; bei der Variante `live` ohne `modell/` ein Hinweis, dass die Dateien noch fehlen. Das Skript `es/bewegung.js` ist erlaubt (Muster `es/(lp|bewegung).js?v=…`).
- **Tests:** neue Dateien `tests/bewegung.test.php` (BW01–BW12) und `tests/bewegung-paritaet.js`; `tests/hilfe.php` kann Bewegungsdateien in den Testordner legen (`H::bewegungen`); `ci/browser-bewegung.js` prüft im Browser mit Prüfkamera: ohne Kamera und ohne Erlaubnis Hinweis plus Player, kein Cookie, kein Web-Speicher, keine Fremdanfrage, Einwilligungsklick vor dem Kamerazugriff. Die Maskierungsprüfung überspringt Felder, die nur bei einer anderen Variante gelten.
- **`scripts/version-ablegen.sh` legt ab 3.64.0 auch die Prüfsummenliste `scripts/modell-summen.txt` mit ab** (Anlass: Bahn BL, Auslieferung der Modelldateien auf Landingpages). Ohne sie fehlt dem abgelegten Stand die Grundlage für `Bewegung::modell`, und der Prüfer meldete jede Datei in `modell/` als fremd. Tags vor 3.64.0 haben die Datei nicht und werden wie bisher abgelegt (Test `versionsordner`).

- **Nachbesserung nach unabhängiger Prüfung (13 Befunde, Bahn KN):**
  - **Prüfer:** In `bew/` gilt eine Datei nur als benutzt, wenn ihr Name `Bewegung::MUSTER` erfüllt und ein Feld `bewegung` sie nennt (vorher genügte irgendein Textfeld: `bew/.htaccess`, `bew/x.php`, ein Pfad in `alt` und ein Unterordner waren „benutzt“). `Bewegung::modell()` sperrt einen Link statt eines Ordners und fängt einen nicht lesbaren Ordner ab (Befund statt Ausnahme). Bei `live` meldet der Prüfer ein unvollständiges `modell/` als Hinweis („es fehlen: …“, nicht sperrend).
  - **Neuer Einstieg `Bewegung::pruefenText(string $text)`** (Länge vor dem Dekodieren, Tiefe 8, dann `pruefen`); `fehler()` liest die Datei mit Obergrenze (`MAX_BYTES + 1`) und nutzt ihn. Das Panel kann ihn für Upload und Bibliothek direkt aufrufen.
  - **Katalog: zwei neue allgemeine Regelschlüssel** `nicht_leer` (im Feld: nur Leerzeichen zählen als leer) und `hoechstens_variante` (an der Bausteinregel `regeln`: `{"live": 1}` = Variante `live` höchstens 1-mal je Seite). Gebrauch: `knopf_text` ist nicht leer, die Kamera-Vorführung gibt es höchstens einmal je Seite; `lp/SKILL.md` und `lp/werkstatt.md` nennen beides (und raten zum Wort „Kamera“ im Knopftext). Keine neue `lp-`-Klasse.
  - **Kamera-Vorführung:** Wechselt der Besucher den Tab, während noch geladen wird, die Erlaubnis aussteht oder das Kamerabild startet, schaltet sich nichts mehr ein und geöffnete Spuren werden beendet; ein Klick während des Ladens bricht ab (Knopf „Kamera ausschalten“ schon ab dem Laden). Fehler in der Erkennung oder beim Beenden der Aufnahme: Kamera aus, Hinweis `keinkamera`, keine Fehlerschleife.
  - **Player:** Dateien über 300 000 Zeichen werden nicht mehr mit `JSON.parse` gelesen; die Tastatur auf der Leinwand ruht, solange die Kamera läuft.
  - **`scripts/modell-holen.sh`:** lädt in einen temporären Ordner, prüft mit `sha256sum --strict -c`, verschiebt erst dann ins Ziel (bei Fehler bleibt das Ziel unberührt), holt nur über https (`--proto`, `--proto-redir`) mit `--max-filesize`, und prüft das Ziel (auch über Links) vor dem Anlegen.
  - **Doku `docs/bewegung-format.md`:** „Der PHP-Prüfer ist maßgeblich“ mit den bekannten Unterschieden des JS-Prüfers (BOM, Unicode-Buchstaben im Namen, andere Kennung bei Tiefe/UTF-8/Surrogat/NUL) und der Nachkommastellen-Toleranz von `1e-6` (bis zu neun Stellen).
  - **Tests:** BW04 um die Fälle erweitert, die in beiden Prüfern anders oder gleich ausgehen; neu BW11 (`modell()` mit richtiger Summe, Link, kein Ordner; `fehlt()`; `pruefenText()`, `fps` 10/60, `verhaeltnis` 0,25/4, 2 und 600 Bilder) und BW12 (`modell-holen.sh` offline mit curl-Attrappe: falsche Summe lässt das Ziel unberührt); `ci/browser-bewegung.js` prüft zusätzlich Tab-Wechsel in allen drei Startphasen, Abbrechen beim Laden, Erkennung wirft, Aufnahme mit NaN, Tastatur bei laufender Kamera und eine zu große Datei.

### Rückweg
Den Zweig `lp/bewegung` nicht zusammenführen (oder verwerfen). Bestehende Seiten bauen weiter mit der Marke ihres Standes (`ENDOSKELETON`), sie kennen `bewegung` nicht; es wurde nichts an `base.css`, `shared.js`, Presets, `pages/` oder `api/` geändert. Modell und WASM liegen außerhalb des Kits (`scripts/modell-holen.sh`) und können gelöscht werden.

---

## [3.63.0] — 2026-10-30

### Added
- **`statistik.js`: Besucherzähler für Webseiten** (Anlass: Martin, 29./30.10.2026, Statistik-Plan Weg A — Webseiten wie survivaltraining.at zählen bisher nichts, das Panel kennt seit rc1340 aber den Schalter „Besucher zählen“ je Webseite). Eine Datei im Wurzelordner neben `shared.js` (55 Zeilen, ohne Abhängigkeiten), eingebunden mit einer Zeile unmittelbar vor `</body>`: `<script src="statistik.js" data-quelle="web:beispiel.at" defer></script>`. Sie schickt per `navigator.sendBeacon` (`text/plain`, höchstens 2048 Byte) an `https://panel.ssi.at/lp/treffer` den Vertrag v1 der Landingpages (`lp/lp.js`) ohne Formular: `v`, `liste` (`web:<domain>`), `art` (`aufruf` | `verlassen`), `pfad`, `ref` (nur Host, die eigene Domain bleibt leer), `utm`, `breite` — bei `verlassen` dazu `dauer` (ganze Sekunden sichtbar, 0–3600) und `tiefe` (0|25|50|75|100).
- **Regeln im Zähler:** Er sendet nur, wenn der Host der Seite genau die Domain der Quelle oder `www.<Domain>` ist — Vorschau (`develop.`/`vorschau.`), lokale Kopien und Sicherungsordner zählen nie. Der Aufruf zählt erst, wenn die Seite sichtbar ist (ein vorab geladener Tab nicht); `aufruf` und `verlassen` je höchstens einmal je Seitenaufruf. `navigator.doNotTrack`, `navigator.globalPrivacyControl` und `<body data-statistik="aus">` schalten ihn ab. Kein Cookie, kein `localStorage`/`sessionStorage`/IndexedDB, keine Kennung; jeder Fehler wird verschluckt, die Seite bleibt ohne das Skript voll nutzbar.
- Tests: `statistik` (109 Prüfungen: Quelltext und eine Seiten-Attrappe in Node, `tests/statistik-lauf.mjs`; die Quellen-, Host-, Abschalter-, Grenzen- und Fehlerfälle; dieselben Grenzen wie `lp/lp.js`), Browsertest `ci/browser-statistik.js` (49 Prüfungen in Chromium: Scheinadresse `beispiel.test`, die Anfrage an das Panel wird abgefangen — nie geht etwas hinaus). Ohne Node läuft der Test `statistik` nur über den Quelltext und sagt es auf STDERR.
- `CLAUDE.md`: Architektur-Zeile und ein Absatz „Besucherzähler für Webseiten“ (Einbindungszeile, Hostprüfung, Abschalter, Hinweis auf die Content-Security-Policy `connect-src https://panel.ssi.at`).

### Changed
- Sonst nichts: `shared.js`, `lp/`, `.htaccess`, Presets und Seiten bleiben unverändert (Test `bestand` grün). Die `.htaccess` dieses Repos erlaubt `https://panel.ssi.at` nicht in `connect-src`; wer den Zähler auf einer Seite mit dieser Richtlinie einsetzt, muss sie ergänzen. Das Skript steht neben `lp/lp.js` in etwa 35 Zeilen doppelt (Vertrag v1 und Grenzen); ein gemeinsamer Baustein wäre ein eigener Anlass.
- **Rückweg:** Zweig `web/statistik-zaehler` nicht mergen oder den Commit zurücknehmen (`git revert <Commit>`); Webseiten, die `statistik.js` schon einbinden, behalten ihre Kopie und zählen weiter, bis die Zeile vor `</body>` entfernt wird (dann bleibt die Seite ohne Zähler, nichts anderes ändert sich).

---

## [3.62.0] — 2026-10-29

### Added
- **Eine Landingpage kann ein eigenes Video zeigen — als Datei, nicht von YouTube** (Anlass: Martin, 29.10.2026, Video-Datei-Plan Etappe 1). Zwei Stellen: der Baustein `video` mit dem neuen Wert `datei` im Feld `anbieter` und dem neuen Feld `datei`, und der Kopfbereich `hero` (Variante `bild-rechts`) mit dem neuen freiwilligen Feld `video_datei` („Eigenes Video (Datei)“). Im Bauplan steht nur ein Pfad; die Datei selbst liegt im Seitenordner unter `vid/`. Das Kit nimmt nur ein MP4 mit festem Namen: `vid/eigen-<16 Hexzeichen>.mp4` (Muster `^vid/eigen-[0-9a-f]{16}\.mp4\z`, `\z` statt `$`), eine gewöhnliche Datei (weder die Datei noch `vid/` ein Link), `ftyp` an Stelle 4–8 der Datei (mindestens 12 Byte), höchstens 40 MiB (41 943 040 Byte). Das Video startet nie von selbst: `<video controls preload="none" playsinline poster="…">`, ohne `autoplay`, `muted` und `loop`; erst der Klick auf die Bedienleiste lädt die Datei — vorher keine Anfrage. Das Startbild (`poster`) ist beim Baustein das Feld `vorschau`, beim Kopfbereich das Bild; die Textalternative ist `aria-label` (beim Baustein der Titel). Unter dem Video steht ein Ersatzlink „als Datei öffnen“ (du/Sie), falls der Browser das Video nicht abspielt.
- **Neue Klasse `lp-video-datei`** (seit 3.62.0): das `<video>`-Element füllt den 16:9-Rahmen (`.lp-video-rahmen`, derselbe wie beim YouTube-Weg), `object-fit: contain` — das Video wird nie abgeschnitten (ein 3:2-Startbild zeigt dadurch Balken); eigener Fokusring (`:focus-visible`, nur Token `--focus`). Sonst nur Klassen, die es schon gibt (`lp-video-rahmen`, `lp-video-link`, `lp-hero-medien`). `lp.js` bleibt unverändert.
- **Katalog: drei allgemeine Regeln und ein Feldtyp.** `pflicht_wenn` (Feld ist Pflicht, wenn ein anderes Feld einen Wert hat), `nur_wenn` (Feld gibt es nur bei diesem Wert) und `nicht_mit` (Feld schließt ein anderes aus); ein Feld ohne Angabe gilt mit seinem `standard`. Neuer Feldtyp `videodatei` (prüft den Pfad und die Datei über `Videodatei::fehler()`). `anbieter` heißt jetzt `youtube` oder `datei` (Standard `youtube`, nicht mehr Pflicht), `kennung` ist nur noch Pflicht, wenn `anbieter` `youtube` ist. Der Kopfbereich schließt `video_kennung` und `video_datei` gegenseitig aus. Die Werte `datei` und das Feld `video_datei` gibt es erst ab Marke 3.62.0. `lp/SKILL.md` zeigt die Bedingungen in der Feldtabelle („wenn …“, „nur wenn …“, „nicht zusammen mit …“, „Standard …“).
- **`lp/src/Videodatei.php` (neu):** eine einzige Prüfung (Muster, Link, vorhanden, Größe, MP4-Kopf) für den Prüfer und — beim Bauen noch einmal — für den Kontext, damit keine Datei am Prüfer vorbeiläuft. Fehler tragen einen Kundentext („Das Video ist zu groß (41,0 MB, erlaubt sind 40 MB).“, „Die Datei ist kein MP4-Video.“ …).
- **Prüfer:** Im Seitenordner ist der Ordner `vid/` erlaubt; eine Videodatei, die der Bauplan nicht nennt, ist sperrend („Im Videoordner der Seite liegt eine Datei, die die Seite nicht verwendet — bitte entfernen.“), wie bei `img/`. Ein Link auf einen gültigen `vid/`-Pfad im Text ist erlaubt. Neue sperrende Regeln für die gebaute Seite: ein `<video>` mit `autoplay`, `loop` oder `muted`, ein `<video>` ohne Bedienleiste, ein Startbild von fremder Quelle oder nicht als WebP oder über 400 KB.
- **Musterbuch:** Probedatei `lp/musterbuch/vid/eigen-0a1b2c3d4e5f6071.mp4` (128×72, 2 s, 3720 Byte) und zwei Zusatz-Fixtures `hero--bild-rechts--video-datei` und `video--breit--datei` (Name `<Baustein>--<Variante>--<Zusatz>`); `ci/musterbuch-bauen.php` kopiert die Probedateien aus dem Bauplan in jede Musterseite. Die YouTube-Fixtures bleiben unverändert.
- Tests: `herovideodatei` (VD01–VD10: Katalog, Muster, Magic Bytes, Größe, Link, Feldregeln, HTML ohne Autoplay, Prüferregeln, du/Sie, CSS nur Tokens, Skill-Text), `katalog` kennt den Feldtyp, `musterbuch` kennt Zusatz-Fixtures, `ci/browser.js` (Video-Datei bei 1440 und 390 px: Startbild, Bedienleiste, kein waagrechtes Scrollen, kein Autoplay, keine Anfrage vor dem Start).

### Changed
- `Kontext::bild()` ist in `bildDaten()` (privat) und die Ausgabe geteilt, damit Kopfbereich und Video dasselbe Startbild nutzen; das HTML des Bausteins `video` mit `anbieter` `youtube` bleibt Byte für Byte gleich.
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`), bis sie neu gebaut werden. Seiten ohne Videodatei bauen unverändert.
- **Offen (nicht Teil dieser Version):** Hochladen, Umrechnen, Auslieferung und der Livegang der Videodatei (Etappen 2–4) — das Kit prüft und baut nur. Der Rechtstext der Seite wird hier nicht geändert; ein Satz dafür steht im Bericht.

## [3.61.0] — 2026-10-29

### Added
- **Der Kopfbereich kann ein YouTube-Video zeigen** (Anlass: Martin, 29.10.2026, „ich möchte alternativ zum Bild in der Landingpage auch ein Video wählen können“ — „Ja, YouTube“). Neues freiwilliges Feld `video_kennung` („YouTube-Video (Kennung)“, die 11 Zeichen aus der Adresse, Muster `^[A-Za-z0-9_-]{11}\z`) im Baustein `hero`, nur bei der Variante `bild-rechts` und nur zusammen mit dem Bild. Das Bild bleibt als **Startbild mit Play-Knopf** stehen; erst der Klick lädt das Video von `youtube-nocookie.com` — derselbe Zwei-Klick-Weg wie beim Baustein `video`: vorher kein iframe und keine Anfrage an YouTube, ohne JavaScript bleibt der Link „auf YouTube ansehen“ mit dem Hinweis darüber.
- **Startbild im 16:9-Rahmen:** Das Video sitzt in dem Rahmen des Bausteins `video` (`.lp-video-rahmen`, 16:9, nie abgeschnitten, auch bei 390 px); das Startbild füllt ihn aus der Mitte (ein 3:2-Bild verliert oben und unten etwas). Neue Klasse `lp-hero-medien` (seit 3.61.0): ordnet Rahmen und Hinweis untereinander und lässt die Rasterspalte schrumpfen (`min-width: 0`). Sonst nur Klassen, Tokens und der Handler, die es schon gibt (`lp-video-start`, `lp-video-bild`, `lp-video-link`, `lp.js`) — keine zweite Kopie.
- **Prüfer:** `Schema` kennt für Felder die Einschränkung `nur_bei` (Varianten): `video_kennung` bei `bild-hinten` oder `schlicht` ist sperrend („… gibt es nur bei der Darstellung „bild-rechts““), ebenso ein falsches Format (auch eine ganze Adresse oder ein Zeilenumbruch am Ende — `\z` statt `$`) und ein Video ohne Bild (das Bild ist bei `bild-rechts` Pflicht). Die Kennung gilt für die Sperrliste nicht als Text. Katalog-Felder tragen außerdem ein `seit`; `lp/SKILL.md` zeigt `nur bei …` und `seit …` in der Feldtabelle.
- Tests: `herovideo` (97 Prüfungen: Katalog, Format, Variantenregel (auch im Baustein allein, am Prüfer vorbei), HTML mit und ohne Video, du/Sie, Sperrliste, CSS nur Tokens, kein Inline-Style, ein einziger Handler), `ci/browser.js` (Kopfbereich-Video bei 1440 und 390 px: vor dem Klick keine Anfrage, 16:9, Play-Zeichen, per Tastatur, nach dem Klick nur `youtube-nocookie`, ohne JavaScript bleibt der Link). Das Musterbuch `hero--bild-rechts` zeigt das Video.

### Changed
- `Kontext::videoStart()` (neu) schreibt Rahmen, Startbild-Knopf und Ersatzlink für den Baustein `video` und den Kopfbereich gemeinsam; der Baustein `video` bleibt Byte für Byte gleich (Musterbuch unverändert bis auf den Stempel von `lp.css`).
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`), bis sie neu gebaut werden. Das Feld gibt es erst ab Marke 3.61.0; Seiten ohne `video_kennung` bauen unverändert.
- **Offen (nicht geändert):** Die `.htaccess` des Hauptordners erlaubt als `frame-src` nur `www.google.com` und `maps.google.com`, nicht `www.youtube-nocookie.com` (gilt für den Baustein `video` genauso). Die `.htaccess` des Sammelordners (`lp/src/Sammelordner.php`) setzt keine Content-Security-Policy; ob die ausgelieferten Seiten eine aus anderer Quelle bekommen, ist hier nicht geprüft.
- Der Baustein `video` prüft seine Kennung weiter mit `$` (ein angehängter Zeilenumbruch fiele durch); das neue Feld nutzt `\z`.

## [3.60.0] — 2026-10-29

### Added
- **Newsletter-Anmeldung als fester Baustein für Webseiten** (Anlass: Martin, 29.10.2026, „… soll das entsprechend als fester Bestandteil über Sprache eingebunden werden können“). `modules/newsletter.js` ist ein echter Anmelde-Baustein statt des Platzhalters: Eine Seite trägt `modules: ["newsletter"]` und `newsletter: { liste: "…" }` in `SITE_CONFIG` ein, der Baustein setzt den Abschnitt (Sprungmarke `#anmeldung`) selbst direkt über den Fuß. Optional: `titel`, `einleitung`, `tag`, `einwilligung`, `knopf`, `danke`, `fehler`, `datenschutz`.
- **Gleicher Weg wie die Landingpage:** normaler Formular-POST an `https://panel.ssi.at/lp/anmelden` (Vorname Pflicht, Nachname freiwillig, E-Mail, Einwilligungs-Häkchen nie vorangehakt, Honigtopf, verstecktes `liste` und `einwilligung_kurz` = erste 12 Hex-Zeichen von SHA-256 des Einwilligungstexts). Kein `fetch`, keine eigene API-Adresse, keine Nummern von Gruppe oder Absender — die stehen im Panel bei der Liste.
- **Rückkehr vom Panel:** `?anmeldung=ok|fehler` zeigt eine Meldung (Live-Region, Fokus darauf); `ok` blendet die Felder aus, `fehler` lässt sie stehen; Parameter und Sprungmarke verschwinden aus der Adresse. Beide Meldungen tragen „Falls Sie schon angemeldet waren, bekommen Sie keine neue E-Mail.“ — der Vertrag nach außen kennt nur ok und fehler.
- **Barrierefrei:** Labels zu jedem Feld, Fehlertexte am Feld (`aria-invalid`, `aria-describedby`), Fokus auf das erste Fehlerfeld, sichtbarer Fokusring, Kontrast mindestens 4,5:1 hell und dunkel, ab 390 px einspaltig.
- **`modules/newsletter.css`** (neu, vom Baustein selbst geladen): nur Tokens, keine Inline-Stile. Neue Klassen, alle mit Präfix `newsletter-`: `newsletter`, `newsletter-karte`, `newsletter-felder`, `newsletter-einwilligung`, `newsletter-meldung` (`--ok`, `--fehler`), `newsletter-nachsatz`. Es gibt keine neue `lp-`-Klasse.
- **`pages/_newsletter.html`** (neu): Referenz für die Webseiten-Werkstatt (Einbau, Schlüssel, Panel-Hinweis, Datenschutz-Abschnitt, Content-Security-Policy: `form-action` muss `https://panel.ssi.at` erlauben).
- Tests: `newsletter` (Quelltext-Verträge, 58 Prüfungen), `ci/browser-newsletter.js` (Verhalten im Browser auf `tests/fixtures/newsletter-seite.html`, 81 Prüfungen, kein echter Aufruf des Panels), `neutral` erlaubt die eigene Adresse `panel.ssi.at`.
- Grenzen: Der Baustein braucht JavaScript und https (WebCrypto für die Kurzsumme; sonst bleibt er aus, statt ein totes Formular zu zeigen). Das Panel nimmt Namen nur mit Buchstaben, Leerzeichen, Bindestrich und Punkt an — ein Apostroph führt zu „fehler“. Ändert sich der Einwilligungstext, muss die Liste im Panel neu eingetragen werden. Kein Eingriff in `base.css`, `shared.js`, Presets, `api/`.

## [3.59.0] — 2026-10-28

### Added
- **Der Titel des Kopfbereichs passt seine Schriftgröße der Textmenge an** (Anlass: Martin, 28.10.2026, „Wenn der User in den Titel mehr reinschreibt, dann soll die Textgröße automatisch etwas kleiner werden, damit der Text sich ausgeht. Dynamische Textgrößenveränderung je nach Textmenge“). Vier Stufen nach Zeichenzahl (Zeichen, nicht Bytes; Leerraum am Rand zählt nicht): bis 36 Zeichen Stufe 0 (Normalgröße, genau wie bisher), 37–52 Stufe 1, 53–68 Stufe 2, ab 69 Stufe 3. Die Regeln stehen in `lp/lp.css` (`.lp-hero-titel[data-lp-groesse="1"]` bis `"3"`): `max(var(--fs-xl), calc(var(--fs-hero) * 0.86 | 0.73 | 0.62))`, dazu eine Zeilenhöhe von 1,12 | 1,15 | 1,2 — nur Tokens, deshalb gilt es in jeder Schriftvorgabe und auf dem Handy mit (die Untergrenze `--fs-xl` ist dort 25 px). Gemessen (Testprofil, Kopfbereich bild-rechts, Titel mit 78 Zeichen aus echten Wörtern): 3 statt 4 Zeilen bei 1440 px, 3 statt 5 bei 390 px.
- **Ohne Skript sofort richtig:** `lp/bausteine/hero.php` schreibt die Stufe beim Bau ins HTML (`data-lp-groesse` am `<h1>`, berechnet von `Kontext::schriftStufe()` mit `mb_strlen`) — die Seite stimmt auch ohne JavaScript und ohne Flackern. Dazu `data-lp-stufen="36 52 68"`: `Kontext::TITEL_STUFEN` ist die einzige Liste der Schwellen.
- **Live beim Tippen im Bild:** `lp/lp.js` zieht dieselbe Regel nach, wenn sich der Text eines Titels ändert (`MutationObserver` auf `[data-lp-stufen]`; sieht Tippen, Einfügen und das Zurücksetzen mit Escape gleichermaßen). Es liest die Schwellen aus `data-lp-stufen`, hat also keine zweite Liste. Das Panel braucht dafür keine Änderung: Das Skript im Rahmen macht den `<h1>` selbst bearbeitbar, und `lp.js` läuft in der Vorschau ohnehin mit.
- Keine neue `lp-`-Klasse (die Stufen hängen an Attributen), kein Eingriff in `base.css`, Presets, `pages/`, `api/`. Tests: `titelgroesse` (Stufen bei 20/45/80/140 Zeichen, Umlaute und Emoji als ein Zeichen, HTML ohne Inline-Style, CSS nur mit Tokens, keine zweite Schwellenliste in `lp.js`) und `ci/browser.js` (gemessene Schriftgröße nimmt ab, bleibt über `--fs-xl`, kein waagrechter Überlauf bei 1440/1024/390 px, hell und dunkel; Größe ändert sich beim Tippen ohne Neuladen).
- **Nach dem Sprung zum Formular steht der Cursor im ersten Feld** (Anlass: Martin, 28.10.2026, „Wenn ich oben auf den Button klicke, dann springe ich herunter zum Formular. Dann sollte der Cursor gleich im ersten Eingabefeld springen, also Focus standardmäßig. Damit erspart sich der User einen Klick.“). `lp/lp.js`: Ein Klick auf einen Knopf oder Link mit Anker (`a[href^="#"]`), dessen Ziel das Formular ist (`#anmeldung`), setzt den Fokus auf das erste sichtbare, bedienbare Eingabefeld — erst wenn der Sprung steht (das sanfte Scrollen wird abgewartet, bei „weniger Bewegung“ gleich) und ohne selbst zu scrollen (`focus({ preventScroll: true })`). Nur nach diesem Klick: nicht beim Laden, nicht bei `#anmeldung` in der Adresse, nicht nach einer Rückmeldung (`?anmeldung=…`; dort fokussiert weiter die Meldung). Enter auf dem Knopf wirkt wie ein Klick; Klick mit Strg, Cmd, Umschalt oder Alt, Mittelklick, ein im Bearbeiten-Modus des Panels abgefangener Klick (`defaultPrevented`) und ein Link zu einem Ziel ohne Formular tun nichts; wer währenddessen schon selbst ein Feld gewählt hat, behält es. Der Honigtopf (`.form-hp`) sowie gesperrte und ausgeblendete Felder (Fieldset gesperrt, Felder nach der Rückmeldung weg) sind nie das erste Feld; ohne bedienbares Feld passiert nichts. Beschriftung (`<label for>`) und der Fokusring (`:focus-visible`, `base.css`) waren schon da.
- **Die Statistik zählt diesen Fokus nicht:** `formular` (Beginn des Ausfüllens) meldet jetzt der erste Fokus durch den Besucher selbst, das erste Tippen oder das erste Anklicken im Formular (`focusin`, `input`, `click`; auch ins schon aktive Feld) — der Fokus nach dem Sprung ist über die Marke `selbstFokus` ausgenommen, der Klick auf den Knopf zählt nicht, und wer nur springt und wieder geht, ergibt `aufruf` ohne `formular`. Neu gegenüber 3.58.0 ist, dass Tippen und Anklicken des ohnehin schon aktiven Feldes zählen (vorher nur ein neuer Fokus). Vertrag mit dem Panel unverändert: gleiche Ereignisse, je Besuch einmal.
- **Grenzen:** Auf dem Handy öffnet der Fokus die Bildschirmtastatur (gewollt, das spart den Tipp); ob sie das Feld verdeckt, lässt sich nur auf einem echten Gerät sehen. Safari auf iOS setzt den Fokus nach dem Warten unter Umständen ohne Tastatur, weil er nicht mehr direkt aus dem Tipp kommt. Tests: `formularfokus` (Regeln im Skript, kein `autofocus` in den Bausteinen) und `ci/browser.js` (drei Kopfbereiche bei 1440 und 390 px: Cursor im ersten Feld, Feld im Bild, Fokusring, Fokus erst nach dem Sprung; „weniger Bewegung“; Enter; kein Fokus beim Laden, mit `#anmeldung`, nach `?anmeldung=fehler|ok`; Honigtopf und Attrappen vor dem ersten Feld; abgefangener Klick; Link ohne Formularziel; Statistik: Tippen, Anklicken und Tab ergeben je genau ein `formular`, der Sprung keins).

### Changed
- **Die Dachzeile darf zwei Zeilen füllen** (Anlass: Martin, 28.10.2026, „… auch zweizeilig möglich sein. Wenn der User mehr schreibt, kann es auch zu einem Zeilenumbruch kommen“ und „Grenzung etwas anheben, dass eine zweite Zeile volllaufen kann“; „Achte Zeile“ ist als Dachzeile gedeutet). Die Grenze steigt von 60 auf **100 Zeichen** (`lp/bausteine.json`, Feld `dachzeile`). Messung bei 1440 px in der Textspalte des Kopfbereichs mit Bild (587 px): Eine Zeile fasst Ø 54,5 Großbuchstaben-Zeichen, zwei Zeilen Ø 108,8 (vier Beispieltexte, 104–112); im schmalsten Kopfbereich (Bild dahinter, 560 px) Ø 103,5. 100 ist die glatte Zahl, mit der auch dort zwei Zeilen reichen; mit 110 liefen zwei der vier Beispiele in eine dritte. Auf dem Handy (390 px) fasst eine Zeile nur etwa 29–34 Zeichen: 100 Zeichen brauchen dort drei bis vier Zeilen.
- `.lp-hero-dachzeile`: `text-wrap: balance` (kein einzelnes Wort allein in der zweiten Zeile) und `line-height: 1.4` (vorher geerbt) — auch eine einzeilige Dachzeile ist dadurch ein paar Pixel niedriger. Schriftgröße, Buchstabenabstand und Abstand zum Titel bleiben.
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`), bis sie neu gebaut werden; erst dann wirken Stufen und die neue Dachzeilen-Grenze. Das Panel liest die Grenze aus dem Katalog der Marke einer Seite (`config/landingpage-vorlagen.php` nennt keine eigene): Es zieht mit, sobald eine Seite auf diese Marke gehoben ist.

### Fixed
- **Der Link „Zum Inhalt springen“ setzt den Cursor nicht mehr ins Formular** (Prüfer-Fund vor dem Festschreiben, WCAG 2.4.1 „Bereiche umgehen“): Der Sprung-Fokus fragte erst `ziel.closest(…)` und dann `ziel.querySelector('form.lp-formular')` auf JEDES Ziel — und `<main id="inhalt">`, wohin der Link des Seitenrahmens zeigt, enthält das Formular. Wer per Tastatur oder Screenreader den Seitenkopf überspringen wollte, landete im Vornamen-Feld (und der Screenreader las das Feld statt des Inhalts). Jetzt zählt nur der Anmeldeabschnitt selbst als Ziel (`ziel.matches('.lp-anmeldung')`; der Abschnitt trägt `id="anmeldung"`), nicht mehr jeder Vorfahre des Formulars und kein Aufstieg aus dem Formular heraus. Die Knöpfe zur Anmeldung (Kopf, Abschluss) wirken unverändert. Tests: Browser (Enter und `.click()` auf den Skip-Link, Link auf den Kopfabschnitt, Anker auf ein Feld im Formular, der Knopf wirkt danach weiter), `formularfokus` FF01 (Regel im Quelltext).
- **Randleerraum im Titel in PHP und JS gleich:** `Kontext::schriftStufe()` zog nur ASCII-Leerraum ab, `String.trim()` in `lp.js` zieht auch geschütztes Leerzeichen, ideographisches Leerzeichen, Geviert und Byte-Order-Mark ab — wer einen solchen Rand am Titel hatte, sah die Stufe beim ersten Tippen im Bild springen. Jetzt zieht PHP genau diese Zeichen ab (Unicode-Klasse Zs, Tabulator bis Zeilenumbruch, U+2028/2029, U+FEFF); NEL (U+0085) und das Nullbreiten-Leerzeichen (U+200B) bleiben stehen, wie bei `trim()`. Ungültiges UTF-8 zählt wie bisher mit dem Originaltext. Test: `titelgroesse` TG02.
- **Konfigurator: die `?v=`-Stempel an `css/konfigurator.css` und `js/scanner-client.js`, `js/content-injector.js`, `js/konfigurator.js` standen noch auf 3.58.0** (nur `ENDO_VERSION` war angehoben; ein Browser mit Zwischenspeicher hätte die alten Dateien behalten). Jetzt 3.59.0. Test: `versionen` prüft alle Stempel an `css/` und `js/` gegen VERSION (der alte Test kannte nur die Marke 3.49.3).

## [3.58.0] — 2026-10-28

### Changed
- **Zusammenführung der Marken 3.54.5 und 3.55.0–3.57.0** (kein neues Verhalten, keine neue `lp-`-Klasse; Anlass: beide Zweige gingen von 3.54.4 aus und änderten dieselben Dateien, `lp-fabrik` konnte nur eine Seite übernehmen). Diese Marke enthält: aus 3.54.5 den Zustand „geschlossen“ der Anmeldung (`?anmeldung=fehler&grund=geschlossen` zeigt „Die Anmeldung ist im Moment geschlossen …“ statt des Fehlertexts, ohne Felder und ohne Plätze-Zahl; Klasse `lp-anmeldung-geschlossen`; der längere Fehlertext); aus 3.55.0 die zwei Häkchen (freiwilliges Newsletter-Häkchen nur mit `"newsletter": true` im Bauplan; Klassen `lp-einwilligung--freiwillig`, `lp-freiwillig`); aus 3.56.0 und 3.57.0 den anonymen Statistik-Beacon in `lp/lp.js` (`aufruf`, `formular`, `absenden`, `verlassen`). Das Zusammenspiel: Auf der Rückkehrseite mit „geschlossen“ bleibt es bei „geschlossen“ (auch mit dem zweiten Häkchen im Bauplan, dessen Feld in den ausgeblendeten Feldern steht), und der Beacon sendet dort nichts, weil die Rückkehrseite schon gezählt ist; auf der Seite mit Formular sendet er `aufruf` und beim Verlassen `verlassen`, aber kein `absenden`, wenn nichts abgesendet wurde. In `ci/browser.js` blieb der Hilfsparameter `umschreiben` (3.56.0); der gleichwertige `umbau` aus 3.54.5 entfällt. Neuer Test `zusammenspiel` (Quelltext von `lp.js` und `anmeldung.php`) und neue Browserfälle für „geschlossen“ mit zweitem Häkchen und Beacon. Das Musterbuch und `lp/SKILL.md`/`lp/werkstatt.md` sind neu gebaut, nicht von Hand gemischt.
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`), bis sie neu gebaut werden; Seiten der Marken 3.54.5 bis 3.57.0 sind einzeln unverändert gültig.

## [3.57.0] — 2026-10-27

### Added
- **Statistik: Verweildauer und Scrolltiefe beim Verlassen** (`lp/lp.js`, im vorhandenen Beacon-Block von 3.56.0; Anlass: Martin, 27.10.2026, „A + B als Schalter je Seite“, Nachtrag 1 zum Statistik-Vertrag; Gegenstück im Panel: Annahme von `art: "verlassen"`). Beim ersten `pagehide` oder Verstecken des Tabs (`visibilitychange` → `hidden`) geht **einmal je Seitenaufruf** eine Meldung `art: "verlassen"` mit `dauer` (ganze Sekunden SICHTBARER Zeit, 0–3600) und `tiefe` (weitester unterer Fensterrand in Prozent der Seitenhöhe, auf 0 | 25 | 50 | 75 | 100 gerundet) an dieselbe Adresse hinaus: `sendBeacon`, `text/plain`, derselbe Rahmen (`v: 1`, `liste`, `pfad`, `ref`, `utm`, `breite`). `v` bleibt 1 (additiv).
- **Sichtbare Zeit, keine Wanduhr:** Die Uhr (`performance.now()`) beginnt, wenn die Seite zum ersten Mal sichtbar ist — derselbe Moment wie `aufruf`. Was ein Tab im Hintergrund oder beim Vorab-Laden verstreicht, zählt nicht. Eine Seite, die nie sichtbar war, und die Rückkehrseite einer Anmeldung (`?anmeldung=…`, kein `aufruf`) senden auch kein `verlassen`. Rückkehr aus dem Zurück-Zwischenspeicher (`pageshow persisted`) zählt wie bei `aufruf` nicht als neuer Seitenaufruf.
- **Grenzen, die man wissen muss:** Gesendet wird beim ERSTEN Verstecken. Wer den Tab nur kurz wechselt, beendet damit die Messung dieses Aufrufs (die Zeit danach fehlt) — das ist der Preis dafür, dass die Meldung auf dem Handy überhaupt ankommt (dort ist das Verstecken oft das letzte Ereignis). Eine Seite, die ganz ins Fenster passt, gilt als bis unten gesehen (`tiefe` 100).
- **Bremst nichts:** Der Scroll-Hörer ist passiv, Scroll-Ereignisse kommen höchstens einmal je Bildwechsel, und im Hörer wird nur die Position gelesen (keine Abfrage der Seitenhöhe); die Seitenhöhe wird einmal beim Verlassen gelesen. Alle Regeln aus 3.56.0 gelten unverändert: kein Cookie, kein Web-Speicher, „Do Not Track“/„Global Privacy Control“ und `data-lp-statistik="aus"` senden nichts, Seiten ohne Anmeldeformular senden nichts, alle Fehler werden verschluckt, nichts verzögert Absenden oder Verlassen.
- Keine neue `lp-`-Klasse, keine neue Einstellung, kein Eingriff in `base.css`, Presets, `pages/`, `api/`. Tests: `angebot` (Quelltext: Art, Ereignisse, passiver Hörer, Obergrenze, keine Wanduhr) und `ci/browser.js` (Form und Rahmen, genau eine Meldung bei `pagehide` + Verstecken + echter Navigation, Sichtzeit statt Wanduhr mit gefälschter Uhr und Tab-Wechsel, Obergrenze 3600, Scrolltiefe 0 → 100 mit Zurückscrollen, passiver Hörer ohne Höhenabfrage beim Scrollen, DNT/GPC/`aus` und Rückkehrseite ohne Meldung, kein Cookie/Web-Speicher mit Spion). Musterbuch und Skill neu erzeugt.
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`) und messen Dauer und Tiefe erst nach einem Neubau mit diesem Stand. Der Datenschutz-Satz aus 3.56.0 („anonym, ohne Cookies, ohne Speicherung Ihrer IP-Adresse“) bleibt wahr: Dauer und Scrolltiefe sind keine Kennung und werden nirgends auf dem Endgerät gespeichert.

## [3.56.0] — 2026-10-27

### Added
- **Statistik der Landingpages: der Beacon in `lp/lp.js`** (Anlass: Martin, 27.10.2026, Statistik für die Landingpages ohne Cookies; Gegenstück im Panel: Annahme `POST /lp/treffer` und Tab „Statistik“). Ein kleiner abgegrenzter Block am Ende von `lp.js` meldet mit `navigator.sendBeacon` (Inhaltstyp `text/plain`, also eine einfache Anfrage ohne Preflight) drei Ereignisse: `aufruf` (einmal je Seitenaufruf, sobald die Seite sichtbar ist — bei Vorab-Laden erst dann), `formular` (einmal beim ersten Fokus in einem Feld des Anmeldeformulars; das Lockfeld zählt nicht) und `absenden` (einmal, nachdem die Prüfung der Seite das Absenden durchgelassen hat; das Senden selbst wird weder verzögert noch verändert).
- **Anonym by design:** kein Cookie, kein `localStorage`/`sessionStorage`/IndexedDB (die Einmal-Sperren sind Variablen der Seite), keine Kennung des Besuchers. Gesendet werden nur Kennung der Anmeldeliste (wie das Formular sie mitschickt), Pfad, Host des Verweisers (nie Pfad oder Anfrage; der eigene Host zählt als „direkt“), `utm_source`/`utm_medium`/`utm_campaign` (auf 60 Zeichen gekürzt, nur `A–Z a–z 0–9 Leerzeichen _ . -`) und die Fensterbreite — zusammen weit unter 1 KB, jedes Feld unter 400 Zeichen.
- **Adresse und Verzicht ohne neue Einstellung:** Die Adresse folgt dem Formularziel der Seite (`…/lp/anmelden` → `…/lp/treffer`, gleicher Host). Seiten ohne Anmeldeformular oder ohne Formularziel senden nichts. „Do Not Track“, „Global Privacy Control“ und `data-lp-statistik="aus"` am `<body>` schalten die Messung ab. Jeder Fehler wird verschluckt: ein werfender `sendBeacon` stört das Absenden nicht. Die Rückkehrseite einer Anmeldung (`?anmeldung=…`) sendet keinen zweiten `aufruf`.
- Keine neue `lp-`-Klasse, keine neue Einstellung im Profil, kein Eingriff in `base.css`, Presets, `pages/`, `api/`. Tests: `angebot` (lp.js: Beacon, Verzicht, kein Speicherzugriff) und `ci/browser.js` (Doppelgänger für `/lp/treffer`: Reihenfolge und Einmal-Sperren, Felder und Größe, UTM, Verweiser nur als Host, Vorab-Laden, DNT/GPC/Abmeldung, Seite ohne Formular, werfender `sendBeacon`, Spione für Cookies und Web-Speicher). `lp/SKILL.md` neu erzeugt, Musterbuch neu gebaut.
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`) und messen erst nach einem Neubau mit diesem Stand. Die Datenschutzerklärung steht im Profil des Kunden (`rechtliches/datenschutz.txt`), nicht im Baukasten: Der Satz „Wir zählen Aufrufe dieser Seite anonym — ohne Cookies und ohne Speicherung Ihrer IP-Adresse.“ gehört dort hinein (Vorschlag; den Wortlaut vorher mit der Gegenstelle abgleichen — auch ein Webserver-Protokoll führt Adressen).

## [3.55.0] — 2026-10-20

### Added
- **Anmeldung: zwei Häkchen statt eines** (`lp/bausteine/anmeldung.php`, `lp/src/Profil.php`, `lp/src/Pruefer.php`, `lp/bausteine.json`, `lp/lp.css`; Anlass: Martin, 19./20.10.2026: „zwei Häkchen: eines für den Newsletter und das andere ohne Newsletter“ — bisher war der Newsletter Bedingung für die Anmeldung, Kopplungsverbot Art. 7 Abs. 4 DSGVO). Das Pflicht-Häkchen `einwilligung` (Text `einwilligung` des Profils, wie bisher `required`, nie vorangehakt) deckt nur noch Zugangslink und Erinnerung. Neu darunter das **freiwillige** Häkchen `newsletter` (Wert `ja`, nie `required`, nie vorangehakt, im Label das Wort „(freiwillig)“ — nicht nur eine Farbe) mit eigenem Wortlaut: `einwilligung_newsletter` des Profils (neues, freiwilliges Profilfeld, 20–600 Zeichen), sonst der Standard `Profil::NEWSLETTER_STANDARD` „Ja, ich möchte zusätzlich Neuigkeiten von <Name der Marke> per E-Mail bekommen. Jederzeit abbestellbar.“ (`Profil::newsletterText`). Die Kurzsumme dieses Wortlauts (erste 12 Hex-Zeichen von sha256) geht als verstecktes Feld `newsletter_kurz` mit — das Panel legt für den Newsletter einen eigenen Nachweis mit eigenem Wortlaut an.
- **Nur mit Ziel:** Das zweite Häkchen steht nur, wenn der Bauplan in der Anmeldung `"newsletter": true` trägt (neues Feld `newsletter`, `janein`, fehlt = kein Häkchen). Das setzt das Panel beim Bau, wenn für die Seite eine allgemeine Newsletter-Gruppe gewählt ist — kein Häkchen ohne Ziel. Ohne das Feld ist das Formular Byte für Byte wie in 3.54.4 (gleiche Felder `einwilligung`, `einwilligung_kurz`); ältere Weiterleitungsstellen verstehen es weiter.
- **Prüfer:** Ein Newsletter-Häkchen mit `required` oder `checked` oder ohne `newsletter_kurz` sperrt (`Newsletter-Häkchen als Pflicht`, `… vorangehakt`, `newsletter_kurz fehlt`). Die Pflicht-Einwilligung wird weiter wie bisher geprüft.
- Neue Klassen: `lp-einwilligung--freiwillig` (Hülle des freiwilligen Häkchens), `lp-freiwillig` (das Wort „(freiwillig)“, gedämpft, ohne Umbruch). Vertrag an die Weiterleitungsstelle: zusätzlich zu den bisherigen Feldern `newsletter=ja` (nur wenn angehakt) und `newsletter_kurz` (immer, wenn das Häkchen da ist).
- Musterbuch `anmeldung--karte` zeigt beide Häkchen (Testprofil mit `einwilligung_newsletter`), `anmeldung--breit` weiter nur eines; `lp/SKILL.md` und `lp/werkstatt.md` neu erzeugt. Tests: `bausteine` (Markup, Reihenfolge, Kurzsummen, Standardwortlaut, Sie-Form, `false`), `pruefen` (vier Sperren), `profil` (Feldregeln, `newsletterText`), `schema` (`newsletter` nur ja/nein); Browsertest `ci/browser.js`: beide Häkchen bei 360/390/1280 px sichtbar und bedienbar, Absenden nur mit Häkchen 1 (Newsletter allein genügt nicht), Newsletter nie `aria-invalid`, per Tab und Leertaste erreichbar, Kontrast hell und dunkel, ohne `newsletter` nur ein Häkchen.
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`) und damit das alte Formular mit einem Häkchen, bis sie neu gebaut werden. Der Wortlaut der Pflicht-Einwilligung steht im Profil des Kunden (`einwilligung`) — dort muss „und Neuigkeiten“ heraus, damit das Pflicht-Häkchen wirklich nur Zugangslink und Erinnerung deckt.

## [3.54.5] — 2026-10-27

### Changed
- **Anmeldung: nach dem Anmeldeschluss steht „geschlossen“ statt „prüfe deine Angaben“** (`lp/bausteine/anmeldung.php`, `lp/lp.js`, `lp/lp.css`; Anlass: Martin, 27.10.2026 — eine Seite meldete nach Ablauf der Anmeldefrist „Das hat leider nicht geklappt. Bitte prüfe deine Angaben …“, obwohl die Angaben stimmten). Die Weiterleitungsstelle hängt bei abgelaufener Frist `&grund=geschlossen` an den Rücksprung (`?anmeldung=fehler&grund=geschlossen#anmeldung`; `anmeldung=fehler` bleibt, damit ältere Seiten weiter den Fehlertext zeigen). Neu ist ein versteckter Absatz neben „ausgebucht“: „Die Anmeldung ist im Moment geschlossen. Das liegt nicht an deinen Angaben. Bitte melde dich direkt bei uns.“ (Sie-Form über die Anrede des Profils). `lp.js` zeigt ihn und fokussiert ihn, blendet die Felder aus (wie bei „voll“), blendet keine Plätze-Zahl ein und entfernt `anmeldung` und `grund` aus der Adresse. Nur der eine Wert `geschlossen` wirkt; jeder andere `grund`-Wert, und `?anmeldung=geschlossen` allein, ändert nichts. Fehlt der Absatz (Seite aus einer älteren Marke), bleibt es beim bisherigen Fehlertext.
- Neue Klasse: `lp-anmeldung-geschlossen` (gleiche Optik wie `lp-anmeldung-voll`: dieselbe CSS-Regel, kein neuer Stil, Farben aus `--primary`/`--heading` wie bisher — Paar schon in der Kontrast-Matrix).
- Der Fehlertext „Das hat leider nicht geklappt …“ bekommt den Zusatz „Klappt es wieder nicht, melde dich bitte direkt bei uns.“ (Sie: „… melden Sie sich bitte direkt bei uns.“) — für alle anderen Fehler, bei denen es der Besucher noch einmal versuchen soll.
- Dazu im Panel: Rücksprung mit `grund=geschlossen` ab rc1276 (Zweig `lp/geschlossen`). Das Musterbuch ist neu gebaut; Tests `bausteine`/`angebot` prüfen Wortlaut und Markup, `ci/browser.js` das Verhalten (geschlossen, anderer `grund`-Wert, Seite ohne den Absatz).
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`) und damit den alten Fehlertext, bis sie neu gebaut werden.

## [3.54.4] — 2026-10-19

### Changed
- **Anmeldung: die Danke-Meldung ist besser formatiert** (`lp/lp.css`, `lp/bausteine/anmeldung.php`; Anlass: Martin, 19.10.2026, Bildschirmfoto der Meldung nach der Anmeldung: „auch hier bitte, besser formatieren“). Vorher standen Dank und Zusatzsatz („Falls du schon angemeldet warst …“) als ein fetter Block im umrandeten Kasten, und „E-Mail“ brach am Bindestrich um („E-“ am Zeilenende). Jetzt: ein runder Haken in der Hauptfarbe (CSS-Zeichen, für Vorleseprogramme stumm), daneben der Dank in größerer Schrift und der Zusatzsatz klein und normal darunter, der Kasten in der Akzentfarbe (`--lp-akzent`) mit Rand in der Hauptfarbe. „E-Mail“ bleibt zusammen. Die Meldung bleibt ein `<p>` mit `role="status"` (Fokus und Ablauf in `lp.js` unverändert); Text und Reihenfolge sind gleich.
- Neue Klassen: `lp-anmeldung-danke-text`, `lp-anmeldung-danke-nachsatz` (die zwei Sätze der Meldung) und `lp-ohne-umbruch` (hält „E-Mail“ zusammen).
- Kontrast: `--heading`/`--text` auf `--lp-akzent` und `--text-on-primary` auf `--primary` stehen schon in der Farbpaar-Matrix des Katalogs — kein neues Paar.
- Das Musterbuch ist neu gebaut; Test `danke` prüft Markup und Regeln (auch: `display: grid` nur mit `:not([hidden])` — der Browsertest hatte gezeigt, dass die Meldung sonst schon beim Laden sichtbar war). Der Browsertest `ci/browser.js` misst den Kontrast jetzt an den zwei Spannen (391 Prüfungen grün).
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`) und damit die alte Meldung, bis sie neu gebaut werden.

## [3.54.3] — 2026-10-19

### Changed
- **Landingpages: auch am Computer kompaktere Abstände** (`lp/lp.css`; Anlass: Martin, 19.10.2026, Bildschirmfoto am Computer zur Seite „Der Weg zum gesunden Pferd“: „auch hier die Abstände entsprechend besser“). Ab 48 rem hat jeder Block 3 rem Innenabstand (`--space-2xl`, auch beim Kopfbereich mit Bild dahinter) statt 4 rem — zwischen zwei Blöcken auf gleichem Grund 6 rem statt 8 rem. Unter 48 rem bleibt es bei den 2 rem aus 3.54.2. Gemessen an der MUSTER-Seite: bei 1280 px 2.925 → 2.704 px Höhe, bei 768 px 3.333 → 3.141 px, bei 390 px unverändert gegenüber 3.54.2 (3.017 px).
- Das Musterbuch ist neu gebaut; Test `kompakt` prüft die neuen Werte (21 Prüfungen).
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`) und damit die alten Abstände, bis sie neu gebaut werden.

## [3.54.2] — 2026-10-19

### Changed
- **Landingpages: kompakter auf dem Handy** (`lp/lp.css`, neuer Abschnitt „KOMPAKTER AUF DEM HANDY“ in `@media (max-width: 47.99rem)`; Anlass: Martin, 19.10.2026, Bildschirmfoto einer MUSTER-Seite: „die Abstände sind zu groß“). Unter 48 rem hatte jeder Block 4 rem Innenabstand oben UND unten — zwischen zwei Blöcken auf gleichem Grund 8 rem Leere — und 1,5 rem Raster-Abstand. Jetzt: 2 rem je Block (`--space-xl`, auch beim Kopfbereich mit Bild dahinter), 1 rem im Raster (`--space-md`), 1,5 rem zwischen Text und Bild des Kopfbereichs, die Überschrift behält 0,5 rem Abstand nach unten. Nur Abstandsstufen: die Voreinstellungen compact/spacious skalieren mit. Ab 48 rem ändert sich nichts. Gemessen an der MUSTER-Seite „Der Weg zum gesunden Pferd“: bei 390 px 3.433 → 3.017 px Höhe, bei 360 px 3.493 → 3.077 px, bei 768 px unverändert (3.333 px).
- Das Musterbuch (`musterbuch/`) ist neu gebaut (`php ci/musterbuch-bauen.php`): `es/lp.css` und die Versionszeile im Kopf ändern sich.
- Test `kompakt` (Block unter 48 rem mit genau diesen Werten, Stufen vorhanden, außerhalb des Blocks gilt der alte Wert, Reihenfolge im Stylesheet).
- Bestehende Seiten behalten ihre Marke (Datei `ENDOSKELETON`) und damit die alten Abstände, bis sie neu gebaut werden.

## [3.54.1] — 2026-10-11

### Fixed
- **Prüfer: Lücken aus der unabhängigen Prüfung von 3.54.0 — jetzt in beiden Kopien der Faltung gleich** (das Panel hatte dieselben und bringt dieselben Änderungen in rc1092; `lp/src/TextFaltung.php` und panel `app/Support/TextFaltung.php` sind wieder Token für Token gleich — der Hinweis auf eine Abweichung im Kopf der Datei entfällt):
  - Ein Zalgo-Haufen mit Umlautmarke (`ga` + Marke ¨ + Akzent + `rantiert`) wurde nicht gefunden, wenn davor ein Nullbreitenzeichen, ein weiches Trennzeichen, ein Sternchen oder ein Verwechslungszeichen stand: Ob der Text mehrdeutig ist, wurde am Rohtext entschieden, nicht an dem Text, der verglichen wird. Jetzt auch am Text nach dem Wegräumen von Unsichtbarem, Vollbreit und Verwechslungszeichen (der Füller wird weiter im Rohtext erkannt).
  - Ein Satzende vor einem schließenden Anführungszeichen oder einer Klammer („Das hilft.“ Gegen die Angst“) und das Auslassungszeichen „…“ trennten eine Wendung nicht — Fehlalarm in Zitaten. Zeichenfolgen wie „ .. “ umgingen die Regel „Leerzeichen vor dem Satzzeichen = kein Satzende“.
  - „Kosten – frei wählbar“ (Strich mit Leerzeichen) galt in einem Text mit Preis als „kosten-frei“, weil die Faltung Leerzeichen um Satzzeichen zusammenzieht. `TextFaltung::lesartenOhnePunkte()` (bisher nur für den Satzstrang) ist öffentlich; `Pruefer::preis()` sucht darin.
- **Buchstaben mit Haken, Strich, Tilde, Schwanz …, die Unicode nicht zerlegt, sind für den Leser der Grundbuchstabe:** „Wirkstoƒƒ“, „ɠarantiert“, „ɍisikofrei“, „garanƭiert“, „hɨlft gegen“ (215 Zeichen in der neuen Tabelle `HAKEN`, bisher nur ø đ ħ ł ŧ ǥ), auch „Balken“-Formen wie ʉ („unhackbar“) und ꬳ. Erzeugt aus der Unicode-Datenbank (alle Zeichen „LATIN SMALL|CAPITAL LETTER X WITH …“, „X BAR …“, „BARRED X“ und „LONG S WITH …“ ohne Zerlegung); `tests/fixtures/sperrliste-haken.json` hält alle 222 Paare. Nicht dabei: gedrehte, offene und Kapitälchen-Formen, kyrillisch ө ү қ, ĸ, ɟ, ꬰ. Kein Zeichen ist ein deutscher Buchstabe, keines steht in einer anderen Tabelle.
- **Schmale Leerzeichen (U+2006, U+2009 Schmalspatium, U+200A Haarspatium, U+202F) ZWISCHEN ZWEI BUCHSTABEN** gelten wie der Hangul-Füller in beiden Lesarten (Lücke oder nichts), als eigene Lesart: „hei“ + Haarspatium + „lt“ trifft den kurzen Eintrag „heilt“. Neben Satzzeichen, Ziffern und Währung („z. B.“, „5 €“, „Mo – Fr“, „Kosten – frei wählbar“) bleibt es eine Lücke; die Lesart nimmt nur die schmalen Leerzeichen weg, nie Umlautmarken (NFD-„Blüten“ bleibt „Blüten“). Mehrere schmale Leerzeichen hintereinander sind ein Lauf (zwei Haarspatien sind schmaler als ein Schmalspatium), eine Marke unmittelbar davor oder dahinter stört nicht, und ein Haufen Marken mit Umlautmarke UND ein schmales Leerzeichen im selben Wort werden zusammen gelesen. Bekannte Nebenwirkung, im Test festgehalten: ein schmales Leerzeichen zwischen zwei Wörtern lässt einen KURZEN Eintrag über die Wortgrenze treffen („Ich“ + Schmalspatium + „eilte“ → „heilt“); ein gewöhnliches Leerzeichen nicht. Längere Einträge (ab 8 Zeichen) wurden schon bisher auch über einen Wortabstand gefunden.
- Tests: `faltung` FA12 (B2–B4, auch schließende Zeichen ” ’ » › " ' und „hilft .. gegen“), FA13 (Haken, schmale Leerzeichen, Strich, NFD; Fixture) und `pruefen-sperrliste` (Prüfer: Strich mit Leerzeichen und mit schmalen Leerzeichen, ƒ und ʉ im Text, Haarspatium); gegen die Fassung davor rot, danach grün. Die zweite Fassung der schmalen Leerzeichen und die Balken-Formen gehen auf die unabhängige Prüfung der ersten zurück. Neue Marke 3.54.1 (enthält 3.54.0); Seiten bekommen sie erst beim Neubau.

## [3.54.0] — 2026-10-11

### Added
- **Prüfer: Sperrliste und Grenzwörter gelten über Schreibweisen hinweg.** Bisher verglich der Prüfer nur „`**` weg, weiche Trennzeichen und Nullbreitenzeichen weg, Leerraum zusammen“ und suchte dann den Eintrag. Das ließ sich umgehen, ohne dass es ein Leser merkt: kyrillisches „а“ statt „a“ (und griechische und weitere Zeichen, die genauso aussehen), Vollbreit („ｇarantiert“), mathematische, eingekreiste und hoch- oder tiefgestellte Schriften, Umlaut in NFD (a + kombinierender Punkt), Ligaturen, Akzente und Zierzeichen (Zalgo), unsichtbare Zeichen (auch Hangul-Füller, Braille-Leerzeichen, nicht zugewiesene Zeichen), Buchstabenreihen („g a r a n t i e r t“, „g.a.r…“) und Trenner mitten in einem langen Wort („gar antiert“, „ga-rantiert“); Wendungen („hilft gegen“, „100 % sicher“) auch mit gespreizten Wörtern, Füller statt Leerzeichen und anderem Leerraum. Der Prüfer ist die letzte Schranke vor dem Livegang — und die einzige Prüfung für Texte, die über das Browser-Formular der Assistenten-Schritte kommen —, und er fand das alles nicht.
- **Zwei neue Klassen, eine eigenständige Kopie des Panels (keine Abhängigkeit zum Panel):** `Endo\Lp\TextFaltung` (`lp/src/TextFaltung.php`) ist die Kopie von panel `app/Support/TextFaltung.php` (rc1084) — gleiche Tabellen, gleiche Schritte, gleiche Reihenfolge, im Quelltext (ohne Kommentare) Token für Token gleich; **beide müssen gleich bleiben**. `Endo\Lp\Sperrliste` (`lp/src/Sperrliste.php`) ist die Suche aus `Antworten::sperrwort()` des Panels: `wort()` liefert wie dort das erste getroffene Wort der Liste oder `NICHT_PRUEFBAR`, `treffer()` alle getroffenen Wörter (der Prüfer meldete schon immer jedes); `lesarten()` und `eintraege()` sind die einzige Tür des Prüfers zur Faltung. Reines PHP mit mbstring und PCRE, ohne `intl`, ohne Composer; alle Schritte brauchen Zeit linear zur Länge des Texts.
- **Was „dasselbe Wort“ heißt:** (1) Kompatibilität (NFKC): jedes Zeichen, das Unicode selbst zu einem ASCII-Buchstaben oder einer Ziffer zerlegt; (2) kanonische Zerlegung (NFC = NFD): Buchstabe mit Akzent = Grundbuchstabe + kombinierendes Zeichen, ä/ö/ü zur Umschrift ae/oe/ue, ß = ss; (3) Verwechslung: kyrillische und griechische Zeichen, die wie ein lateinischer Buchstabe aussehen. Verglichen wird wie bisher als **Teilwort** („garantiert“ steckt auch in „ungarantiertes“; der Eintrag „Heilkräuter“ trifft den Text „Kräuter“ nicht, wohl aber „Heilkräutertee“); ein Eintrag ab acht Buchstaben wird zusätzlich im Text ohne alle Zeichen zwischen den Buchstaben gesucht (Einzelwort) bzw. im Satzstrang (Wendung: „Durch fall verschwunden“ ja, „Das hilft. Gegen alles“ nein).
- **Ein Text, der sich nicht prüfen lässt, ist ein sperrender Befund — nie still frei:** zu lang (über 64 KB), kein gültiges UTF-8, ein Richtungs-Steuerzeichen (U+202A–202E, U+2066–2069: kehrt die Anzeige um), ein Regex-Schritt, der scheitert (PCRE-Grenzen), oder eine interne Kodierung von PHP, die nicht UTF-8 ist (`default_charset`; nur im Baukasten — die Faltung würde sonst still falsch rechnen). Ein Befund je Feld: „Dieser Text lässt sich nicht auf unerlaubte Wörter prüfen — er ist zu lang oder enthält ungewöhnliche Schriftzeichen. Bitte kürzen und gewöhnliche Buchstaben verwenden.“ (technik `Sperrliste: Text nicht prüfbar`). Ein **Eintrag** der Sperrliste oder der Grenzwörter im Profil, der sich nicht prüfen lässt, ist ein sperrender Befund am Profil (`profil.sperrliste` / `profil.grenzwoerter`, technik `Sperrliste: Eintrag nicht prüfbar (N Bytes)`); die übrigen Einträge prüfen weiter. Ein Eintrag, der gefaltet nichts übrig lässt (nur „*“, nur Unsichtbares), zählt nicht. Ohne Sperrliste und Grenzwörter gibt es nichts zu vergleichen.
- **„Kostenlos trotz Preis“** (Marke 3.52.0) sucht jetzt im gefalteten Text: „Grаtis“ mit kyrillischem а, „ｋostenlos“, „g r a t i s“, „Gebuehrenfrei“ und „Eintritt“ + Füller + „frei“ werden gefunden; ein nicht prüfbarer Text sperrt auch dort, ebenso ein Regex, der scheitert. Die Treffer stehen in Kleinbuchstaben im Befund. Nebenbei behoben: „ohne Gebühr“ (die Einzahl) wurde bisher nicht gefunden, „ohne Gebühren“ schon.

### Changed
- `Pruefer::normal()` entfällt (keine zweite Normalisierung neben der Faltung); `Pruefer::texte()` sucht Sperrliste und Grenzwörter über `Sperrliste::treffer()`, `Pruefer::preis()` über `Sperrliste::lesarten()`.
- **Geändert für den Kunden:** Eine Seite mit getarntem Sperrwort kann nicht mehr freigegeben werden (sperrend, am Feld, mit dem Satz „… darf auf der Seite nicht stehen (Sperrliste).“); ein getarntes Grenzwort gibt den Hinweis „bitte gegenlesen“. Neu ist nur der Befund „Text nicht prüfbar“ (Abschnitt und Feld stehen im Satz) und der Befund am Profil. Die technik-Zeilen der bisherigen Befunde (`Sperrliste: „Wort“`, `Grenzwort: „Wort“`) und ihre Kundensätze sind unverändert — das Panel liest sie; die neuen technik-Zeilen erkennt das Panel nicht und zeigt dann den Satz des Baukastens (ohne Ortsangabe) bzw. „technisches Problem, SSI wurde informiert“ (Profil). Alle 18 Seiten des Musterbuchs prüfen mit dem alten und dem neuen Prüfer **Zeichen für Zeichen dasselbe** (auch mit zwei Beispiellisten, die viele Treffer erzeugen: 7 und 94 Befunde, gleich).

### Kompatibilität
- Neue Marke 3.54.0 (sie enthält 3.53.0). Seiten der Marken 3.51.0 bis 3.53.0 prüfen weiter mit ihrem eigenen Stand und bekommen die neue Prüfung **erst, wenn sie mit 3.54.0 neu gebaut werden** (`ENDOSKELETON` der Seite, `scripts/version-ablegen.sh`). Gebaute Dateien sind Byte für Byte die der Marke 3.53.0 (außer der Versionsangabe und der CSS-Prüfsumme); nur der Prüfer ist neu. Voraussetzung ist dieselbe wie bisher: PHP 8.2 oder neuer mit mbstring und PCRE (neu genutzt: `mb_convert_kana`, `MB_CASE_FOLD`, `mb_str_split`, `mb_ord`); `php8.5` ohne mbstring (wie auf server7 vorhanden) taugt weiterhin nicht.

### Bewusste Entscheidungen und Grenzen (wie im Panel, rc1084)
- **Nicht gefaltet:** Leetspeak („g4rantiert“), der Umlaut ohne Punkte („Krauter“ ≠ „Kräuter“), Kapitälchen, Regionalzeichen, Armenisch/Cherokee/Koptisch u. a. (die Verwechslungstabelle ist eine kleine Auswahl, nicht die Liste des Unicode-Konsortiums), vertauschte oder ergänzte Buchstaben, sichtbare Trenner mitten in einem kurzen Wort („hei-lt“, ab acht Zeichen fängt der Strang sie).
- **Nebenwirkungen:** Ein Eintrag mit ß oder ö trifft auch die ss-/oe-Wörter („Maße“ sperrt „Masse“, „Öl“ „fotoelektrisch“); ein langer Eintrag wird auch aus zwei Nachbarwörtern gefunden („Das Risiko frei zu entscheiden“ trifft „risikofrei“); ein Satzzeichen zwischen den Wörtern einer Wendung trennt sie („Es hilft, gegen den Strom“ ist keine Wendung „hilft gegen“). Akzente zählen nicht (garántiert = garantiert).
- **Unicode-Stand:** Die Tabellen stammen aus Unicode 13, die Fixtures zusätzlich aus Unicode 17 (Zeichen ab 14); PCRE auf den Servern kennt Unicode 14. Was später dazukommt und noch kein Buchstabe ist, löscht der Schritt „Unsichtbares“ als „nicht zugewiesen“ — ein Zeichen ab Unicode 15, das wie ein Buchstabe aussieht und nicht in den Tabellen steht, lässt den Buchstaben verschwinden.

### Bekannte Lücken (auch im Panel; aus der unabhängigen Prüfung, bewusst NICHT einseitig geändert, damit beide Kopien gleich bleiben)
- Ein Zalgo-Haufen mit Umlautmarke wird nicht gefunden, wenn davor ein Nullbreiten- oder Verwechslungszeichen steht (`ga` + U+200B + U+0308 + U+0301 + `rantiert`): Ob der Text mehrdeutig ist, wird am Rohtext entschieden, nicht am gefalteten. Ein Satzende vor einem schließenden Anführungszeichen oder einer Klammer („Das hilft.“ Gegen die Angst“) und das Auslassungszeichen „…“ trennen eine Wendung nicht (Fehlalarm). „Kosten – frei wählbar“ in einem Text mit Preis gilt als „kosten-frei“ (die Faltung zieht Leerzeichen um Satzzeichen zusammen). Etwa 200 lateinische Buchstaben mit Haken, Strich oder Tilde (ƒ ƶ ȼ ɨ …) werden nicht zum Grundbuchstaben. Dünne Leerzeichen (U+2009, U+200A) mitten in einem kurzen Eintrag trennen ihn. Ein ä/ö/ü auf einem Buchstaben, der keinen Umlaut hat („gärantiert“), ist ein anderes Wort. Der Zweig `lp-pruefer-haertung` enthält die ersten drei Korrekturen samt Tests; sie sollten in Panel und Baukasten zugleich übernommen werden.
- Unverändert aus dem alten Stand: „Eintritt ist frei“ und „Eintritt: frei“ werden bei einem Termin mit Preis nicht erkannt; „Parkplätze: gratis“ oder „kostenlose Stornierung“ gelten als „kostenlos trotz Preis“.

### Tests
- `php tests/run.php faltung` (70 Prüfungen: Umgehungen, Abstände, Fehlalarme, Teilwort-Logik, Pathologie in Sekunden, jeder Regex-Schritt scheitert geschlossen — Schadensattrappe in eigenem PHP-Prozess, auch für den Regex „kostenlos“ —, interne Kodierung nicht UTF-8, NFC = NFD für alle 790 Akzentbuchstaben, 994 + 265 Kompatibilitätszeichen, 290 mathematisch-griechische, 41 ab Unicode 14, jede Marke und jedes Formatzeichen mitten im Wort, Stabilität für jeden Codepunkt) und `php tests/run.php pruefen-sperrliste` (151 Prüfungen: getarntes Sperrwort in Titel, Fließtext, Alt-Text aus Bauplan und Bildbestand, Listenpunkt, Knopf, Beschreibung; Grenzwort; gewöhnliche Texte bleiben frei; nicht prüfbar; „kostenlos trotz Preis“; das Befundformat, das das Panel liest). Fixtures `tests/fixtures/sperrliste-*.json` stammen aus der Unicode-Datenbank. Gegen den Stand 3.53.0 rot (46 Prüfungen im Prüfer-Test), danach grün; keine bisherige Prüfung geändert oder gelockert.
- Die Listen in den Tests sind erfundene Beispiele; keine Liste eines Kunden steht im Repo.

### Neue Klassen
- Keine (nur der Prüfer; Gestaltung, Bausteine und `lp.css` sind unverändert).

## [3.53.0] — 2026-10-11

### Added
- **Vorschaukarte beim Teilen des Seitenlinks** (WhatsApp, Telegram, Facebook, Messenger, LinkedIn, Slack, X): Bisher gab die Seite nur `og:type`, `og:title` und `og:description` aus. Mit dem neuen Feld `meta.adresse` (die absolute Adresse der Live-Seite, `https://<host>/`; setzt der Betreiber, nie der Kunde) kommen `og:url`, `og:image` (+ `og:image:type`, `:width`, `:height`, `:alt`) und `twitter:card` = `summary_large_image` dazu — nur auf der Startseite, nicht auf Impressum und Datenschutz. Crawler lösen nichts Relatives auf und holen nur absolute Adressen; der Baukasten kennt seinen Host nicht, deshalb kommt die Adresse aus dem Bauplan. Host: ein Eintrag aus `link_hosts` des Profils oder eine Unteradresse davon (genau an der Punkt-Grenze); sonst Hinweis und keine Karte.
- **`es/vorschau.jpg`**: Das Bild der Karte ist `meta.vorschaubild`, sonst das Bild des Kopfbereichs. Der Bau macht daraus mit GD ein JPEG von 1200 × 630 (1,91:1), aus der Mitte zugeschnitten, Durchsichtiges auf `--bg` des Profils, Qualität 85 abwärts, bis es höchstens 300 KB hat (WhatsApp zeigt größere oft nicht); Baseline, ohne Metadaten (keine Standortdaten aus Handyfotos). JPEG, weil es das einzige Format ist, das alle Netzwerke lesen — und weil Apache auf dem Webserver `.webp` ohne `Content-Type` ausliefert (Debians `mime.types` kennt `image/webp` nicht). Die Adresse trägt `?v=<Prüfsumme>` (neues Bild = neue Adresse, Facebook speichert Bilder 30 Tage nach Adresse). Gleiches Bild, gleiche Bytes.
- **Nie eine kaputte Karte**: Ohne Bild (Kopfbereich „schlicht“) gibt es `og:url` ohne Bild und ohne `twitter:card`; ein Bild, das GD nicht lesen kann (auch bewegtes WebP) oder das über 16 Megapixel hat, ergibt einen Hinweis statt eines Abbruchs; fehlt GD ganz, endet der Bau mit einem technischen Fehler (Ende 2). Hinweise: Adresse außerhalb der freigegebenen Websites, Vorschaubild ohne Adresse, Bild kleiner als 600 × 315 („zu klein für die große Karte“), Bild nicht aufbereitbar.
- **Prüfer**: `og:url` und `og:image` zeigen nur auf die eigene Adresse (https, kein Port, kein Benutzer, gleicher Host wie `meta.adresse` — ein fremdes `og:url` nähme der Seite ihre geteilten Verweise); `es/vorschau.jpg` muss da sein, ein JPEG, genau 1200 × 630 und höchstens 300 KB groß sein. Sperrend.
- **robots.txt**: Eine Seite ohne Suchmaschine (`indexieren` aus, die Vorgabe des Panels) MIT Karte lässt zusätzlich facebookexternalhit, Facebot, Twitterbot, LinkedInBot, Slackbot(-LinkExpanding), TelegramBot, WhatsApp und Discordbot zu; alle anderen bleiben ausgesperrt, die Seite bleibt `noindex`. Facebook, LinkedIn, X und Slack halten sich an robots.txt — mit „Disallow: /“ für alle gäbe es dort keine Karte. Ohne Karte und bei `indexieren` an bleibt robots.txt, wie sie war.
- `SKILL.md` und `werkstatt.md` nennen die Regeln (Karte, Adresse nur vom Betreiber, Bild).

### Kompatibilität
- Neue Marke 3.53.0. Seiten der Marken 3.51.0 und 3.52.0 bauen weiter mit ihrem Stand; sie kennen `meta.adresse` nicht und lehnen es ab — der Betreiber setzt es nur bei einer Marke, deren Katalog (`lp/bausteine.json`, `seite.felder`) das Feld nennt. Ohne `meta.adresse` ist jede gebaute Datei Byte für Byte die der bisherigen Marke (außer der Versionsangabe); das Musterbuch hat keine Karte.

### Neue Klassen
- Keine (die Karte liegt im `<head>`; Gestaltung, Bausteine und `lp.css` sind unverändert).

## [3.52.0] — 2026-10-06

### Added
- Neue Marke für den Gesprächs-Assistenten des Panels (Entwurf `docs/superpowers/specs/2026-10-06-landingpage-gespraech-design.md` im Panel-Repo). Bestehende Seiten der Marke 3.51.0 bauen weiter mit ihrem Stand.
- **Preis im Termin** (`preis`, ganze Euro 0–9999): Zeile „Teilnahme: 25 €“ unter dem Ort; JSON-LD `isAccessibleForFree: false` und `offers` (EUR). „Teilnahme kostenlos“ und `isAccessibleForFree: true` gibt es nur bei ausdrücklich `preis` 0. Fehlt `preis`, steht gar keine Preiszeile und das JSON-LD nennt weder Kostenfreiheit noch einen Preis (Entscheidung 07.10.2026). `lp.js` blendet die Zeile mit aus, wenn kein Termin mehr offen ist.
- **Prüfung „kostenlos trotz Preis“** (sperrend): Hat ein Termin einen Preis ab 1 Euro (bei `preis` 0 passen „kostenlos“-Texte, ohne Angabe prüft die Regel nichts), meldet `pruefen.php` jedes Textfeld, das Kostenfreiheit verspricht, mit Abschnitt und Feld: „kostenlos…“, „kostenfrei…“ (auch „kosten-los“), „gratis…“ (auch in Zusammensetzungen wie „Gratisabend“), „gebührenfrei…“, „unentgeltlich…“, „umsonst“ (nicht „nicht umsonst“), „Nulltarif“, „ohne Kosten/Gebühr(en)“, „Eintritt frei“ und „freier Eintritt“ — Groß- und Kleinschreibung zählen nicht, auch am Anfang zusammengesetzter Wörter. Andere Umschreibungen erkennt die Regel nicht. Die Vorlage `zoom-vortrag` sagt in der Anmeldung „Jetzt anmelden“.
- **Was danach passiert** (`anmeldung.danach`, Absatz bis 300 Zeichen): ein Satz über dem Formular. Wie jeder Absatz mit `**fett**` und Leerzeile = neuer Absatz; jeder Absatz trägt die Klasse `lp-anmeldung-danach` (`Text::absaetze()` nimmt dafür optional eine Klasse).
- **Plätze** (`anmeldung.plaetze`, 1–10000, fehlt = unbegrenzt): „Begrenzt auf N Plätze.“ im Formular; `lp.js` fragt `GET <Host des Formularziels>/lp/plaetze?liste=<kennung>` ohne Cookies ab und zeigt „Noch X von N Plätzen frei“ bzw. „Ausgebucht“ (Felder weg). Neue Rückmeldung `?anmeldung=voll` („Leider ausgebucht — alle Plätze sind vergeben.“). Datenschutz: Die Abfrage schickt die IP-Adresse des Besuchers beim Laden an den Host des Formularziels. Rückmeldung und Stand widersprechen sich nie (der Stand ist im Panel bis 60 s zwischengespeichert, die Anmeldung zählt live): Nach `ok` oder `voll` fragt `lp.js` nicht und blendet die Anzeige aus; meldet die Abfrage nach `fehler` „voll“, ersetzt „Leider ausgebucht …“ die Fehlermeldung und bekommt den Fokus.
- **Folgen fehlender Angaben in `SKILL.md` und `werkstatt.md`:** Ein Katalogfeld kann `fehlt` tragen; die Tabelle nennt es in der Spalte „Grenzen“. `termin.preis`: ohne Angabe keine Preiszeile und kein Preis im JSON-LD; „kostenlos“ nur bei ausdrücklich 0 — damit wissen auch Text-Wizard und Vespera, dass ein Termin seinen Preis (auch die 0) nennen muss. `anmeldung.plaetze`: unbegrenzt.
- **Baustein „person“** (Vortragende Person): Titel, Name, Rolle, zwei bis drei Sätze, Bild aus dem freigegebenen Bestand (Variante `bild-links`). Ohne Namen baut er nichts; die Prüfung gibt einen Hinweis, wenn Rolle, Text oder Bild ohne Namen dastehen.

### Neue Klassen
- `lp-termin-preis`
- `lp-anmeldung-danach`
- `lp-anmeldung-plaetze`
- `lp-anmeldung-voll`
- `lp-person`
- `lp-person--schlicht`
- `lp-person--bild-links`
- `lp-person-inhalt`
- `lp-person-name`
- `lp-person-rolle`
- `lp-person-text`
- `lp-person-bild`

### Bewusste Entscheidungen (06.10.2026)
- Der Block „person“ steht in der Vorlage zwischen Anmeldung und Abschluss: So bleibt die Anmeldung an Stelle 4 (Index 3), auf die das Panel seine Fragen richtet. Ohne Namen baut er nichts.
- `preis` und `plaetze` sind Zahlen; fehlt `plaetze`, gilt „unbegrenzt“. Fehlt `preis`, zeigt die Seite keine Preisangabe — „kostenlos“ ist nur `preis` 0 (Martin, 07.10.2026). Die Musterseite `termin--karte` hat `preis` 0, `termin--band` 25.

## [3.51.0] — 2026-09-27

### Added
- **Landingpage-Baukasten `lp/`** — Seiten aus festen Bausteinen (`seite.json`), gebaut von `lp/bauen.php` ohne Netz,
  KI und Uhr; Aussehen nur aus dem Kundenprofil. Prüfungen vor der Freigabe (`lp/pruefen.php`), Musterbuch, Skill.
- **Tests** `php tests/run.php` (ohne Abhängigkeiten) und `node ci/browser.js` (Scheinadresse `lp.test`, 320–1280 px).
- **Versionsmarke** `ENDOSKELETON` je Landingpage und `<meta name="generator" content="SSI Endoskeleton 3.51.0">`;
  `scripts/version-ablegen.sh` legt getaggte Stände schreibgeschützt nach `endoskeleton-versionen/<X.Y.Z>/` —
  bestehende Seiten bauen immer mit dem Stand ihrer Marke.
- Befehl `/endoskeleton` (`.claude/commands/endoskeleton.md`) neu: Arbeitskopie, Test zuerst, Gegenprobe, VERSION zuletzt.
- Optionales Profil-Token `--lp-logo-hoehe` (Höhe des Logos im Kopf, Vorgabe `3rem`).
- **Rückmeldung nach dem Absenden:** Die Anmeldung trägt zwei versteckte Meldungen (`role="status"`): Dank aus dem
  Bauplan mit dem festen Zusatzsatz „Falls du schon angemeldet warst, bekommst du keine neue E-Mail.“ und „Fehler“
  (du/Sie aus dem Profil). Die Weiterleitungsstelle leitet mit `?anmeldung=ok|fehler#anmeldung` zurück; `lp.js` zeigt
  und fokussiert die passende Meldung und blendet bei `ok` die Felder aus. Datenschutz (Entscheidung 27.09.2026): Die
  Seite verrät nicht, ob eine Adresse schon auf der Liste steht — keine eigene Meldung „bereits angemeldet“; ein
  `?anmeldung=bereits` zeigt genau dasselbe wie `ok` (Abwehr in der Tiefe). Unbekannte Werte bleiben ohne Wirkung.
  Ins Bild kommt die Meldung durch den Fokus, nicht durch die Sprungmarke: Die nimmt `lp.js` vorher aus der Adresse
  (Browsertest bei 390×300 px). Ohne JavaScript springt der Browser nur zum Formular; ein `<noscript>`-Hinweis
  verweist auf die E-Mail. Die Prüfung sperrt eine Anmeldung ohne `"anker": "anmeldung"`.

### Bewusste Abweichungen vom Entwurf (27.09.2026)
- Golddateien sind das gebaute Musterbuch (`musterbuch/`), keine eigenen `tests/gold/`.
- Kontrast als Matrix im Rahmen (jede Schriftfarbe auf jeder Fläche, Knopfschrift auch auf der Hover-Farbe,
  Fokusrahmen auf jeder Fläche) statt Farbpaaren je Baustein — die Paare je Baustein ließen genutzte Kombinationen aus.
- `--lp-akzent` ist eine helle Fläche, nie Schriftfarbe.
- `bauen.php` baut immer an Ort und Stelle (kein `--ziel`); der Prüfer vergleicht den Bau im Speicher.
- `meta.vorschaubild` wird geprüft, aber erst ab Teil 4 (feste Adresse) als `og:image` ausgegeben.
- Das Formularziel muss auf einem Host aus `link_hosts` liegen.

### Fixed (nur Landingpages)
- base.css schaltet bei „weniger Bewegung“ die Übergänge der Knöpfe nicht ab (die `*`-Regel verliert gegen `.btn`);
  `lp.css` gleicht das für Landingpages aus. Bestehende Websites bleiben unverändert (Test `bestand`).

### Neue Klassen
- Rahmen: `lp-seite`, `lp-kopf`, `lp-kopf-innen`, `lp-logo`, `lp-inhalt`, `lp-block`, `lp-block-innen`, `lp-titel`, `lp-knopf`, `lp-fuss`, `lp-fuss-innen`, `lp-fuss-firma`, `lp-fuss-links`, `lp-rechtsseite`
- Kopfbereich (`hero`): `lp-hero`, `lp-hero--bild-rechts`, `lp-hero--bild-hinten`, `lp-hero--schlicht`, `lp-hero-text`, `lp-hero-dachzeile`, `lp-hero-titel`, `lp-hero-bild`
- Termin (`termin`): `lp-termin`, `lp-termin--karte`, `lp-termin--band`, `lp-termin-liste`, `lp-termin-eintrag`, `lp-termin-zeit`, `lp-termin-ort`, `lp-termin-leer`
- Themen (`themen`): `lp-themen`, `lp-themen--haken`, `lp-themen--zwei-spalten`, `lp-themen-liste`, `lp-themen-punkt`
- Video (`video`): `lp-video`, `lp-video--breit`, `lp-video--mit-text`, `lp-video-rahmen`, `lp-video-start`, `lp-video-bild`, `lp-video-link`, `lp-video-text`
- Erfahrungen (`erfahrungen`): `lp-erfahrungen`, `lp-erfahrungen--karten`, `lp-erfahrungen--einzeln`, `lp-erfahrungen-liste`, `lp-erfahrung`, `lp-erfahrung-text`, `lp-erfahrung-name`, `lp-erfahrung-quelle`
- Häufige Fragen (`faq`): `lp-faq`, `lp-faq--standard`, `lp-faq-liste`, `lp-faq-frage`, `lp-faq-antwort`
- Anmeldung (`anmeldung`): `lp-anmeldung`, `lp-anmeldung--karte`, `lp-anmeldung--breit`, `lp-formular`, `lp-feld`, `lp-feld-fehler`, `lp-einwilligung`, `lp-anmeldung-hinweis`, `lp-anmeldung-danke`, `lp-anmeldung-fehler`
- Abschluss (`abschluss`): `lp-abschluss`, `lp-abschluss--akzent`, `lp-abschluss--schlicht`, `lp-abschluss-text`

### Changed
- Versionsangaben vereinheitlicht: VERSION, Konfigurator (`ENDO_VERSION`, `?v=`) und package.json nennen 3.51.0
  (vorher 3.50.1 / 3.49.3 / 4.6.7).

## [3.50.1] — 2026-09-24

### Security
- **Schadcode aus `package.json` entfernt** — Commit `eb8a34d` („chore: deps",
  22.05.2026, unsigniert) hatte einen `postinstall`-Hook eingeschleust, der bei
  jedem `npm install` ein fremdes Binary nach `/tmp/.sshd` lud und startete.
  Hook entfernt. Wer seit dem 22.05. `npm install` in diesem Repo ausgeführt
  hat, muss den Rechner prüfen.

### Fixed
- **Dark-Modus greift wieder** — Die Theme-Bloecke (`dark`, `tech`, `warm`,
  `pastel`, `forest`) standen auf `[data-theme="x"]` und hatten damit dieselbe
  Spezifitaet wie das `:root` aus der generierten `theme.css`. Weil `theme.css`
  zuletzt geladen wird, gewann immer die helle Palette: Nur die Nav wurde dunkel,
  der Rest blieb weiss. Die Bloecke stehen jetzt auf `html[data-theme="x"]`
  und schlagen `:root`. Betraf jede generierte Website.
- **Outline- und Ghost-Buttons auf dunklen Themes** — Die Markenfarbe aus
  `theme.css` ist bei vielen Kunden dunkel und verschwand als Text- und
  Rahmenfarbe auf dunklem Grund. Gleiche Behandlung wie in `.section-dark`.
  Ebenso die Icon-Striche in `.card-icon svg`.
- **Footer-Wortmarke ohne Logo-Bild** — Ist `logo` in SITE_CONFIG leer, rendert
  `shared.js` den Namen als `<strong>`. Der erbte die Textfarbe und war dunkel
  auf dem dunklen Footer. Jetzt explizit hell, im hellen Footer via
  `.site-footer--light` wieder dunkel.
- **ACME-Challenge nicht mehr umgeleitet** — Die HTTPS-Weiterleitung in der
  `.htaccess` fing auch `/.well-known/acme-challenge/` ab. Certbot konnte
  dadurch beim ersten Aufsetzen einer Domain kein Zertifikat holen, solange
  noch keines vorhanden war.
- **Kaputte CSS-Regeln in `base.css`** — Beim Entfernen des Themes `midnight`
  (v5.1.1) wurden in „Dark Theme Button Fixes" die Zeilen mit der öffnenden
  Klammer mitgelöscht. Die drei überzähligen `}` ließen den Browser auch die
  folgende Regel `[data-theme="tech"] .stat-number` verwerfen. Die Blöcke sind
  entfernt; die Button-Farben auf dunklen Themes regelt jetzt der Fix oben.

### Changed
- `api/data/refine-cache/` in `.gitignore` (Laufzeit-Cache der KI-Texte).

---

## [3.50.0] — 2026-04-15

### Added
- **Konfigurator Soft Warm Theme** — Komplettes UI-Redesign: cremefarbener Hintergrund, warme Brauntöne (#7c6a4f), abgerundete Karten statt kaltem Blau/Grau
- **Welcome-Seite neu gestaltet** — Weiße Card mit SSI-Logo, Gradient-Hintergrund, Feature-Badges, warme Eingabefelder
- **KI-Design Button** — Vollständige KI-Pipeline: Preset-Wahl + Text-Optimierung + Hero-Bild in einem Klick
- **KI-Texte Button** — Separater Button nur für Text-Refinement
- **Design-Varianten-Navigation** — 4 visuell diverse Varianten (dunkel/hell/warm/dynamisch) mit direkten Buttons zum Durchblättern
- **Neu-Scannen Button** — Domain komplett frisch crawlen + Gemini neu analysieren lassen, mit Fortschrittsanzeige
- **Hero-Wallpaper-Persistenz** — ContentInjector.inject() überschreibt das gewählte Hintergrundbild nicht mehr

### Changed
- Sidebar-Tab von 8px auf 20px verbreitert (leichter klickbar)
- Buttons kompakter: kleineres Padding und Schrift
- Domain-Bar in warmem Dunkelbraun statt Schwarzblau
- Scanning-Screen hell mit Card-Design statt dunklem Vollbild
- Preset-Recommendation Badge zeigt `design_reasoning` von Gemini
- `?fullscreen=1` URL-Parameter wird automatisch entfernt statt Sidebar zu verstecken
- Alle Cache-Bust Versionen auf 3.49.x aktualisiert

### Fixed
- Hero-Hintergrundbild verschwindet nicht mehr nach ContentInjector.inject (Wrapper-Pattern)
- KI-Design/KI-Texte Buttons werden auch bei gecachten Domains enabled
- Konfigurator.js wird korrekt aus js/ Unterordner geladen (nicht Root)

---

## [3.49.0] — 2026-04-15

### Added
- **KI-gesteuerte Design-Wahl** — Gemini wählt das optimale Design-Preset aus 23 verfügbaren basierend auf Branche, Zielgruppe und Textstimmung. Ersetzt das starre Keyword-Matching.
- **Preset-Overrides** — Gemini kann einzelne Properties (Farbe, Charakter, Theme) fein-tunen wenn das Basis-Preset fast, aber nicht perfekt passt.
- **Design-Begründung** — `design_reasoning` erklärt warum ein bestimmtes Preset gewählt wurde. Wird im Konfigurator als Badge angezeigt.

### Changed
- `_resolvePreset()` priorisiert jetzt `ki.recommended_preset` (Gemini-Empfehlung) vor dem alten Branch-Keyword-Matching (Fallback für alte Analysen).
- `_highlightRecommendedPreset()` wendet Overrides (switchColor/switchCharakter/switchTheme) nach Preset-Anwendung an.
- `TEXT_ANALYSIS_PROMPT` in analyze.py enthält jetzt die vollständige Preset-Liste mit Beschreibungen für Gemini.

---

## [3.48.0] — 2026-04-15

### Added
- **KI-gesteuerte Bildauswahl** — Gemini bewertet jedes Bild (Qualität 1-10, beste Verwendung: hero/about/services/skip). Generator wählt automatisch die besten Bilder pro Bereich.
- **Intelligente Bildverteilung** — Unbenutzte Bilder werden content-basiert auf Subpages verteilt. Jede Seite bekommt ein Bild.
- **Bilder-Resize** — Eingebettete Bilder werden auf max 1200x900px resized (PHP GD). ZIP-Größe drastisch reduziert.
- **Bankdaten auf Spenden-Seite** — IBAN/BIC werden als formatierter Block eingebaut wenn Scanner Bankverbindung findet.
- **Vollständige Kontaktadresse** — Straße + PLZ + Ort ("Gartengasse 13, 7020 Loipersbach") statt nur Stadtname.

### Fixed
- **Bilder nicht abgeschnitten** — `object-fit: contain` statt `cover`. Hochformat-Bilder zeigen das komplette Motiv.
- **Grid-Layout auf Subpages** — Text + Bild nebeneinander (grid-2) statt untereinander. Leerer Container wird entfernt wenn kein Bild.
- **EN-Button entfernt** — `languages: ["de"]` in SITE_CONFIG für einsprachige Seiten.
- **Öffnungszeiten ausgeblendet** — Block wird entfernt wenn keine Öffnungszeiten vorhanden.
- **analyze.py Image-Bug** — "Is a directory" Fehler behoben. Bilder werden jetzt korrekt aus `images/`-Ordner geladen.

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
