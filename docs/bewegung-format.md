# Bewegung — Datenformat „bewegung v1“, Player und Kamera-Vorführung (seit 3.64.0)

Eine **Bewegungsdatei** hält fest, wie sich ein Körper bewegt hat: 33 Körperpunkte pro Bild, mehr nicht. Sie enthält **kein Kamerabild,
keine Gesichtsmerkmale (Augen, Mund), keine Namen und keine Personendaten**. Der Player `lp/bewegung.js` spielt sie auf einer Leinwand
ab; `lp/bewegung-live.js` nimmt sie in der Kamera-Vorführung auf. Beide laufen komplett im Browser, ohne Fremdanfrage.

## Wo die Datei liegt

`bew/<name>.bewegung` im Seitenordner (Name: Kleinbuchstaben, Ziffern, Bindestrich, höchstens 40 Zeichen). Der Inhalt ist JSON, die Endung
aber **nicht** `.json`: der Webserver der Seitenordner sperrt `*.json` als 404 (Sammelordner), damit nie ein Profil o. Ä. ausgeliefert wird.
Der Baustein `bewegung` nimmt den Pfad im Feld `bewegung`; der Prüfer des Bauens (`Endo\Lp\Bewegung`) prüft die Datei vor dem Bauen.

## Aufbau

```json
{"v":1,"name":"Winken (synthetisch)","quelle":"synthetisch","verhaeltnis":0.5625,"fps":30,"dim":2,"dauer_ms":3000,
"punkte":["nase","auge-links-innen", … 33 Namen in fester Reihenfolge …],
"rahmen":[
[x0,y0,s0, x1,y1,s1, … 33 Punkte …],
…
]}
```

| Feld | Regel |
|------|-------|
| `v` | immer `1` |
| `name` | 1–60 Zeichen, beginnt mit Buchstabe/Ziffer; erlaubt Buchstaben, Ziffern, Leerzeichen und `. , : ( ) + _ / – -` |
| `quelle` | **Pflicht:** `eigene-aufnahme` (aus der Kamera-Vorführung), `einwilligung` (andere Person, die schriftlich zugestimmt hat) oder `synthetisch` (rechnerisch erzeugt, kein Mensch) |
| `verhaeltnis` | Breite : Höhe des Bildes, 0,25 bis 4, bis **vier** Nachkommastellen (9:16 = 0.5625). Damit stimmt die Figur ohne Verzerrung, weil x und y auf die Bildbreite/-höhe normiert sind |
| `fps` | ganze Zahl 10–60 |
| `dim` | `2` (x, y) oder `3` (x, y, z) |
| `dauer_ms` | muss `round(Bilderzahl · 1000 / fps)` sein und höchstens **10 000** (10 s) |
| `punkte` | genau die 33 Namen des MediaPipe-Pose-Modells in dessen Reihenfolge (nase … fussspitze-rechts) |
| `rahmen` | 2 bis 600 Bilder; jedes Bild ist eine flache Liste mit `33 · (dim + 1)` Zahlen: je Punkt x, y, (z,) Sichtbarkeit |

Wertebereiche: x, y und Sichtbarkeit 0…1, z −1…1; höchstens **drei** Nachkommastellen. Ein Punkt mit Sichtbarkeit `0` ist ganz `0,0,(0),0`
(nicht erkannt). Größe der Datei höchstens **300 000 Byte** (10 s bei 30 Bildern/s und zwei Achsen sind rund 120–180 KB). Alle Zahlen
müssen endliche Zahlen sein, keine Zeichenketten, kein `null`.

### Keine Gesichtsmerkmale

Die Punkte 1–6 (Augen) und 9–10 (Mund) sind **immer** `0,0,0`; der Prüfer lehnt Dateien mit sichtbaren Gesichtspunkten ab (Kennung
`gesicht`). Nase und Ohren bleiben (Kopfhaltung), sie lassen keinen Menschen erkennen. Die Kamera-Vorführung schreibt die Gesichtspunkte
schon beim Aufnehmen als `0,0,0` — sie verlassen den Speicher des Browsers nie.

### Die Verbindungen der Figur gehören zum Player

Welche Punkte durch Linien verbunden werden (Schulter–Ellbogen–Handgelenk …), steht **nicht** in der Datei, sondern im Player (`LpBewegung`).
So bleibt die Datei klein und die Darstellung austauschbar (Strichfigur oder Punkte, andere Farben, andere Strichstärken).

### Fehlerkennungen

Der Prüfer meldet die Kennung des **ersten** Verstoßes, immer in dieser Reihenfolge: `groesse`, `form`, `schluessel`, `v`, `name`, `quelle`,
`verhaeltnis`, `fps`, `dim`, `punkte`, `rahmen.anzahl`, `rahmen.laenge`, `wert`, `bereich`, `stellen`, `unsichtbar`, `gesicht`, `dauer`
(`json`, wenn der Text kein JSON ist). PHP (`lp/src/Bewegung.php`) und JavaScript (`lp/bewegung.js`) haben dieselben Regeln; der Test
`bewegung` lässt beide über dieselbe Fall-Liste laufen und vergleicht die Kennungen.

## Datenschutz

- **Bewegungsmuster können personenbezogen sein.** Wer sich bewegt, hat einen Gang, eine Haltung, ein Tempo. Für **fremde Personen** braucht
  eine Aufnahme deshalb eine **Einwilligung** (`quelle: "einwilligung"`), und sie gehört nicht ohne Zustimmung auf eine Seite. Für die eigene
  Bewegung (`eigene-aufnahme`) entscheidet die Person selbst.
- Die Kamera-Vorführung speichert nichts im Browser (kein Cookie, kein `localStorage`/`sessionStorage`), lädt nichts hoch und zeigt das
  Kamerabild nur auf der Leinwand des Besuchers. Die Kamera startet erst nach einem Klick und geht beim Stopp, beim Wechsel des Tabs und beim
  Verlassen der Seite aus.
- Der Besucher bekommt die Datei nur, wenn er „Als Datei speichern“ klickt (ein Download auf sein Gerät).

**Vorschlag für den Datenschutztext der Seite** (nur ein Vorschlag, keine Rechtsberatung; die Seite entscheidet selbst):

> Kamera-Vorführung: Wenn Sie „Kamera starten“ klicken, fragt Ihr Browser nach dem Zugriff auf Ihre Kamera. Das Kamerabild wird nur in
> Ihrem Browser verarbeitet, zur Erkennung der Körperhaltung. Es wird nicht gespeichert und nicht an uns oder Dritte übertragen. Die
> Erkennung läuft mit einem Modell, das auf unserem eigenen Server liegt (MediaPipe, Apache-Lizenz 2.0). Wenn Sie die Aufnahme als Datei
> speichern, liegt sie nur bei Ihnen. Sie können die Kamera jederzeit mit „Kamera stoppen“ ausschalten.

## Player (`lp/bewegung.js`)

Der Baustein verdrahtet den Player über `data-lp-bewegung` am `<figure>`; Farben kommen aus dem CSS der Leinwand (`color` = Figur,
`background-color` = Grund), Bedienung über die Knöpfe der Leiste (Pause, Spiegeln, Tempo) und die Tastatur auf der Leinwand (Leertaste/Enter =
Pause, Pfeiltasten = Bild, Home = Anfang). Bei `prefers-reduced-motion: reduce` zeigt er ein Standbild und einen „Abspielen“-Knopf. Der
`alt`-Text steht als Beschriftung unter der Leinwand (`aria-labelledby`). Fehler beim Laden führen zu einem freundlichen Hinweis, nie zu einer
Fehlermeldung im Browser.

**Zeichnen und Zeitsteuerung sind getrennt:** `LpBewegung.zeichne(ctx, daten, bild, breite, hoehe, optionen)` ist eine reine Zeichenfunktion
(Optionen: `farbe`, `stil` = `strich`|`punkte`, `spiegeln`, `linie`, `ueber`, `schatten`). Sie kennt keine Uhr. `LpBewegung.spielen(cv, stil, bei)`
fügt Zeitsteuerung, Schleife, Pause und Tempo hinzu. Ein Renderer (z. B. für die Shorts) ruft `zeichne` Bild für Bild selbst auf.

## Kamera-Vorführung (`lp/bewegung-live.js`)

Wird erst nach dem Klick auf „Kamera starten“ geladen. Erkennung: **MediaPipe Pose Landmarker** (`@mediapipe/tasks-vision` 0.10.35, Modell
`pose_landmarker_lite`), beides **Apache-2.0** (Google LLC). Es gibt **kein CDN**: Skript, WASM und Modell liegen im Seitenordner unter
`modell/`. Ohne Kamera, ohne Erlaubnis, ohne WebGL/WASM oder wenn die Modelldateien fehlen, erscheint ein Hinweis und die Beispielbewegung
läuft weiter.

### Modelldateien (`modell/`)

`scripts/modell-holen.sh [Zielordner]` lädt das npm-Archiv (Prüfsumme sha512) und das Modell mit fester Version, packt genau sieben Dateien aus
(kein `npm install`, keine Paketskripte) und prüft jede gegen `scripts/modell-summen.txt` (SHA-256). Zielordner ist **außerhalb** des Kits
(Standard `../endoskeleton-modelle`); die Dateien gehören nicht ins Git.

| Datei in `modell/` | Größe |
|--------------------|------:|
| `vision_bundle.js` (aus `vision_bundle.mjs`, ES-Modul) | 137 KB |
| `wasm/vision_wasm_internal.js` / `.wasm` | 322 KB / 11,2 MB |
| `wasm/vision_wasm_module_internal.js` / `.wasm` | 322 KB / 11,2 MB |
| `wasm/vision_wasm_nosimd_internal.js` / `.wasm` | 322 KB / 10,5 MB |
| `pose_landmarker_lite.task` | 5,8 MB |

Der Browser lädt davon nur, was er braucht (je nach Gerät eine der drei WASM-Varianten): rund 6 MB Modell plus rund 11 MB WASM.

### Stufe 2 (noch nicht gebaut): Kopieren beim Veröffentlichen

Das Bauen prüft `modell/` nur, wenn es im Seitenordner liegt; es **kopiert nichts**. Beim Veröffentlichen einer Seite mit der Variante `live`
muss das Panel (Stufe 2) `modell/` aus dem Ordner von `modell-holen.sh` in den Seitenordner legen (die Dateien sind unveränderlich und tragen
Prüfsummen, ein Kopieren genügt) und `bew/*.bewegung` mit der Seite ausliefern. Der Prüfer lässt in `modell/` nur Dateien der Summenliste mit
richtiger Prüfsumme zu (sonst gesperrt) und weist bei `live` ohne `modell/` hin, dass die Dateien noch fehlen. Webserver: `.wasm` als
`application/wasm`, `.js` als `text/javascript`.
