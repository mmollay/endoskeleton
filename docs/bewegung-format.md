# Bewegung — Datenformat „bewegung v1“, Player und Kamera-Vorführung (seit 3.64.0)

Eine **Bewegungsdatei** hält fest, wie sich ein Körper bewegt hat: 33 Körperpunkte pro Bild, mehr nicht. Sie enthält **kein Kamerabild,
keine Gesichtsmerkmale (Augen, Mund), keine Namen und keine Personendaten**. Der Player `lp/bewegung.js` spielt sie auf einer Leinwand
ab; `lp/bewegung-live.js` nimmt sie in der Kamera-Vorführung auf. Beide laufen komplett im Browser, ohne Fremdanfrage.

## Wo die Datei liegt

`bew/<name>.bewegung` im Seitenordner (Name: Kleinbuchstaben, Ziffern, Bindestrich, höchstens 40 Zeichen). Der Inhalt ist JSON, die Endung
aber **nicht** `.json`: der Webserver der Seitenordner sperrt `*.json` als 404 (Sammelordner), damit nie ein Profil o. Ä. ausgeliefert wird.
Der Baustein `bewegung` nimmt den Pfad im Feld `bewegung`; der Prüfer des Bauens (`Endo\Lp\Bewegung`) prüft die Datei vor dem Bauen.
Im Seitenordner gilt eine Datei in `bew/` nur dann als „benutzt“, wenn ihr Name dem Muster entspricht **und** ein Feld `bewegung` (Bauplan) sie
nennt; alles andere in `bew/` (`.htaccess`, `.php`, Unterordner, ein Pfad nur im Feld `alt`) ist sperrend.

**Einstieg für das Panel (Upload, Bibliothek):** `Bewegung::pruefenText(string $text): ?string` — prüft zuerst die Länge (höchstens 300 000 Byte,
vor dem Dekodieren), dekodiert mit Tiefe 8 und gibt die Kennung des ersten Verstoßes zurück (`null` = gültig). Wer nur ein Stück der Datei gelesen
hat, braucht höchstens `MAX_BYTES + 1` Byte: mehr kann das Ergebnis nicht ändern (`groesse`).

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

Wertebereiche: x, y und Sichtbarkeit 0…1, z −1…1; höchstens **drei** Nachkommastellen — genauer: der mit 1000 malgenommene Wert darf höchstens um
`1e-6` neben einer ganzen Zahl liegen (Rundungsfehler beim Schreiben). Dadurch gelten Werte wie `0.5000000001` (bis zu neun Nachkommastellen, wenn sie
höchstens `1e-9` neben einem Wert mit drei Stellen liegen) als gültig, `0.12345678` nicht. Beim `verhaeltnis` gilt dasselbe mit vier Stellen. Ein Punkt mit Sichtbarkeit `0` ist ganz `0,0,(0),0`
(nicht erkannt). Größe der Datei höchstens **300 000 Byte** (10 s bei 30 Bildern/s und zwei Achsen sind rund 120–180 KB). Alle Zahlen
müssen endliche Zahlen sein, keine Zeichenketten, kein `null`.

### Keine Gesichtsmerkmale

Die Punkte 1–6 (Augen) und 9–10 (Mund) sind **immer** `0,0,0`; der Prüfer lehnt Dateien mit sichtbaren Gesichtspunkten ab (Kennung
`gesicht`). Nase und Ohren bleiben (Kopfhaltung), sie lassen keinen Menschen erkennen. Die Kamera-Vorführung schreibt die Gesichtspunkte
schon beim Aufnehmen als `0,0,0` — sie verlassen den Speicher des Browsers nie.

### Die Verbindungen der Figur gehören zum Player

Welche Punkte durch Linien verbunden werden (Schulter–Ellbogen–Handgelenk …), steht **nicht** in der Datei, sondern im Player (`LpBewegung`).
So bleibt die Datei klein und die Darstellung austauschbar (Silhouette, Piktogramm, Körper, Strichfigur oder Punkte, andere Farben, andere Strichstärken).

### Fehlerkennungen

Der Prüfer meldet die Kennung des **ersten** Verstoßes, immer in dieser Reihenfolge: `groesse`, `form`, `schluessel`, `v`, `name`, `quelle`,
`verhaeltnis`, `fps`, `dim`, `punkte`, `rahmen.anzahl`, `rahmen.laenge`, `wert`, `bereich`, `stellen`, `unsichtbar`, `gesicht`, `dauer`
(`json`, wenn der Text kein JSON ist). PHP (`lp/src/Bewegung.php`) und JavaScript (`lp/bewegung.js`) haben dieselben Regeln; der Test
`bewegung` lässt beide über dieselbe Fall-Liste laufen und vergleicht die Kennungen.

### Der PHP-Prüfer ist maßgeblich

Beide Prüfer haben dieselben Regeln, aber **entscheidend ist PHP** (Bauen, Prüfer des Seitenordners, `Bewegung::pruefenText`). Der JavaScript-Prüfer
im Player schützt nur die Seite vor einer kaputten Datei und ist keine Sicherheitsgrenze. Die bekannten Unterschiede, im Test BW04 festgehalten:

- **JS nimmt mehr an:** eine Datei mit **UTF-8-BOM** (`fetch().text()` entfernt das BOM, PHP meldet `json`); und **Buchstaben neuerer
  Unicode-Versionen im `name`** (die Buchstabenklasse hängt von der Unicode-Version der Engine ab, bei PHP von der des PCRE — ein Name mit einem
  ganz neuen Buchstaben kann in einem Browser gültig sein und in PHP nicht, oder umgekehrt).
- **Beide lehnen ab, aber mit anderer Kennung:** Tiefe über 8, ein Schlüssel mit NUL, ungültiges UTF-8 und ein einzelnes Surrogat (`\ud800`).
  PHP meldet `json`; JavaScript dekodiert Ersatzzeichen statt zu scheitern und meldet je nach Fall `schluessel` oder `name`.
- **Gleich in beiden:** doppelte Schlüssel (der letzte gilt), `1e999` (`wert`), `−0` (gültig) und die Nachkommastellen-Toleranz oben.

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
Fehlermeldung im Browser. Der Player liest nie eine Datei über 300 000 Zeichen (die Länge wird vor `JSON.parse` geprüft), und die
Tastatur-Bedienung der Leinwand ruht, solange die Kamera läuft (sonst zeichnete der Beispiel-Player in dieselbe Leinwand).

**Zeichnen und Zeitsteuerung sind getrennt:** `LpBewegung.zeichne(ctx, daten, bild, breite, hoehe, optionen)` ist eine reine Zeichenfunktion
(Optionen: `farbe`, `grund`, `stil` = `silhouette`|`piktogramm`|`koerper`|`strich`|`punkte`, `spiegeln`, `linie`, `ueber`, `schatten`). Sie kennt keine Uhr. `LpBewegung.spielen(cv, stil, bei)`
fügt Zeitsteuerung, Schleife, Pause und Tempo hinzu. Ein Renderer (z. B. für die Shorts) ruft `zeichne` Bild für Bild selbst auf.

## Darstellungen der Figur (seit 3.64.2)

Der Katalog-Baustein `bewegung` hat das Feld `stil`; das Kit schreibt es als `data-lp-stil` an die Figur. Ohne Angabe gilt `silhouette`. Ein unbekannter
Wert fällt im Player auf die Vorgabe zurück (kein Fehler); der Prüfer des Kits lehnt ihn beim Bauen ab.

| Wert | Aussehen | Wann |
|------|----------|------|
| `silhouette` (Vorgabe) | kräftige Figur mit Kontur, Körper etwas gedämpft, Boden-Linie | fast immer: auch klein gut lesbar |
| `piktogramm` | einfarbige Fläche, Kopf getrennt, dünne Lücke in der Farbe des Hintergrunds, wo sich Teile überdecken | ruhig, wie ein Notausgang-Schild |
| `koerper` | Figur mit Tiefe: Verlauf von links oben, Schatten unter den Füßen | wenn die Figur Hauptsache ist; braucht etwas mehr Rechenzeit |
| `strich`, `punkte` | Strichfigur / nur die Körperpunkte (die älteren Darstellungen, unverändert) | Bestand |

**Farben ohne neue Einstellung:** Der Player kennt eine Figurfarbe (`color` der Leinwand) und liest den Hintergrund (`background-color` der Leinwand oder des
nächsten Elternelements). Daraus entstehen alle Töne: gedämpft wird zu Schwarz (dunkler Grund) oder Weiß (heller Grund), die Lücke und der Schatten kommen vom Grund. Die
tragende Farbe jedes Stils (Kontur bei der Silhouette, Fläche beim Piktogramm, Mitte des Körpers) hat gegen den Grund mindestens 3 : 1 (WCAG 1.4.11); ist die
Markenfarbe zu schwach, wird sie dafür abgedunkelt oder aufgehellt. Die Hauptfarbe bleibt, wie sie ist, wenn sie schon genügt. `LpBewegung.farben(stil, farbe, grund)` liefert die Töne.

## Kamera-Vorführung (`lp/bewegung-live.js`)

Wird erst nach dem Klick auf „Kamera starten“ geladen. Erkennung: **MediaPipe Pose Landmarker** (`@mediapipe/tasks-vision` 0.10.35, Modell
`pose_landmarker_lite`), beides **Apache-2.0** (Google LLC). Es gibt **kein CDN**: Skript, WASM und Modell liegen im Seitenordner unter
`modell/`. Ohne Kamera, ohne Erlaubnis, ohne WebGL/WASM oder wenn die Modelldateien fehlen, erscheint ein Hinweis und die Beispielbewegung
läuft weiter.

**Abbrechen und Tab-Wechsel:** Der Knopf heißt schon beim Laden „Kamera ausschalten“; ein Klick währenddessen bricht den Start ab (die Kamera wird
dann gar nicht erst angefragt). Verlässt der Besucher den Tab, während noch geladen wird, die Erlaubnis aussteht oder das Kamerabild startet, wird
nichts mehr eingeschaltet und schon geöffnete Kameraspuren werden beendet. Wirft die Erkennung oder das Beenden der Aufnahme einen Fehler, geht die
Kamera aus und es erscheint der Hinweis, dass die Kamera hier nicht verfügbar ist — ohne Fehlerschleife.

**Auf der Seite:** Es gibt höchstens einen `live`-Baustein je Seite (Katalogregel `hoechstens_variante`), und `knopf_text` darf nicht leer sein (Katalogregel
`nicht_leer`). Der Knopftext soll das Wort „Kamera“ enthalten, damit der Besucher weiß, was der Klick auslöst — das steht als Hinweis in
`lp/werkstatt.md`, nicht als Zwang.

### Aufnahme an die eigene Seite übergeben (seit 3.64.1)

Eine Seite, die die Aufnahme selbst weiterverwendet (etwa ein Verwaltungsbereich, der sie auf dem eigenen Server ablegt), braucht den Umweg über den
Download nicht. Zwei Hilfen, beide optional — **ohne sie ändert sich nichts**, der Knopf „Als Datei speichern“ bleibt:

- **Ereignis `lp-bewegung:aufnahme`:** Am Ende einer Aufnahme (nach „Stopp“ oder nach der Höchstdauer von 300 Bildern), wenn sie den Formatprüfer besteht
  und die Vorschau läuft, sendet der Baustein am `<figure class="lp-bewegung-figur">` ein `CustomEvent` mit `bubbles: true` und
  `detail: { text }`. `text` ist **genau** die Datei, die „Als Datei speichern“ schreiben würde (Format „bewegung v1“, höchstens 300 000 Byte). Bei
  „keine Person erkannt“, bei einem Fehler und beim Abbruch kommt **kein** Ereignis. Ein Fänger, der wirft, stört die Seite nicht. Der Server darf dem Text
  nicht vertrauen: `Endo\Lp\Bewegung::pruefenText($text)` prüft ihn dort noch einmal.
- **Attribut `data-lp-modell="<Ordner>"`** am selben `<figure>`: der Ordner mit den Modelldateien (Tabelle unten), wenn er nicht `../modell/` neben dem Skript
  ist. Erlaubt sind nur gewöhnliche Pfade derselben Herkunft aus `A–Z a–z 0–9 . _ ~ / -`, relativ zur Seite (`modell/`, `../modell/`) oder absolut
  (`/js/modell/`); ein fehlender Schrägstrich am Ende wird ergänzt. Alles andere (Schema wie `javascript:`, `data:`, `https:`, `//host/…`, `\`, Anfrageteil `?`,
  `#`, `%`-Codes, Leerraum) wird **still verworfen** und der Standard gilt. Der Baukasten schreibt das Attribut nicht selbst; es ist für eigene Seiten gedacht.

Ein Fänger in sechs Zeilen (die Seite trägt dazu einen `<figure data-lp-bewegung=… data-lp-modus="live" data-lp-modell="/js/modell/">` im Aufbau des Bausteins):

```js
// Die Aufnahme geht an den eigenen Server – nicht an Dritte, nicht in den Web-Speicher
document.addEventListener('lp-bewegung:aufnahme', (e) => {
  const formular = new FormData();
  formular.append('datei', new Blob([e.detail.text], { type: 'application/json' }), 'aufnahme.bewegung');
  fetch('/aufnahmen/annehmen', { method: 'POST', body: formular });
});
```

### Modelldateien (`modell/`)

`scripts/modell-holen.sh [Zielordner]` lädt das npm-Archiv (Prüfsumme sha512) und das Modell mit fester Version, packt genau sieben Dateien aus
(kein `npm install`, keine Paketskripte) und prüft jede gegen `scripts/modell-summen.txt` (SHA-256). Zielordner ist **außerhalb** des Kits
(Standard `../endoskeleton-modelle`); die Dateien gehören nicht ins Git. Das Skript lädt alles zuerst in einen **temporären Ordner**, prüft dort jede
Datei (`sha256sum --strict -c`) und verschiebt erst danach `modell/` ins Ziel — bei einer falschen Summe oder einem Abbruch bleibt das Ziel, wie es war.
Geladen wird nur über `https` (auch bei Weiterleitungen) mit Größengrenze (`curl --proto '=https' --proto-redir '=https' --max-filesize`). Der Zielordner
(und `Ziel/modell`) darf nicht im Kit liegen, auch nicht über einen Link; das wird geprüft, **bevor** ein Ordner angelegt wird.

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
richtiger Prüfsumme zu (sonst gesperrt; ein Link statt eines Ordners oder ein nicht lesbarer Ordner ist ein Befund, keine Ausnahme) und weist bei `live`
darauf hin, dass `modell/` fehlt (Hinweis) oder unvollständig ist („es fehlen: …“, ebenfalls nur ein Hinweis, nicht sperrend). Webserver: `.wasm` als
`application/wasm`, `.js` als `text/javascript`.
