# Landingpage bearbeiten (Endoskeleton 3.68.1)

_Erzeugt aus `lp/bausteine.json` mit `php ci/skill-bauen.php` — nicht von Hand ändern._

- Du änderst ausschließlich `seite.json`, den Bauplan der Seite. HTML, CSS und das Profil fasst du nie an.
- Die Seite besteht aus Abschnitten (Bausteinen) in fester Form. Aussehen, Farben und Schriften kommen aus dem Profil des Kunden.
- Der Kopfbereich (`hero`) steht genau einmal und ganz oben. Anmeldung und Häufige Fragen gibt es höchstens einmal.
- Links nur in Knöpfen und Quellen, nur `https://` auf die freigegebenen Websites des Kunden oder `#anker` auf der Seite.
- Texte ohne HTML. Leerzeile = neuer Absatz, `**fett**` ist die einzige Auszeichnung. Echte Umlaute (ä, ö, ü, ß).
- Adressen im Text (`www.…`, `https://…`, E-Mail) werden zum Link, wenn der Host in `link_hosts` steht — dafür kein Markdown-Link und kein HTML.
- Keine Heil- oder Wirkversprechen; die Sperrliste des Kunden wird geprüft, der Kunde gibt die Seite frei.
- Bilder: `bestand/<datei>` aus dem Bildbestand des Kunden oder `img/<datei>` im Seitenordner (dann mit `alt`, leer nur mit `"dekorativ": true`).
- Beim Teilen des Seitenlinks (WhatsApp, Facebook, LinkedIn …) erscheint das Bild des Kopfbereichs, wenn `meta.vorschaubild` fehlt. `meta.adresse` setzt der Betreiber — nie ändern oder entfernen.
- Die Kamera-Vorführung (`bewegung`, Variante `live`) gibt es höchstens einmal; ihr Knopftext soll das Wort „Kamera“ enthalten, damit der Besucher weiß, was der Klick auslöst.
- Nach jeder Änderung bauen und prüfen; Befunde nennen den Abschnitt mit Nummer und Namen.

## Abschnitte

### Kopfbereich (`hero`)

Varianten: `bild-rechts`, `bild-hinten`, `schlicht` · genau einmal, als erster Abschnitt

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `dachzeile` | zeile | – | eine Zeile, ≤ 100 Zeichen |
| `titel` | zeile | ja | eine Zeile, ≤ 80 Zeichen |
| `text` | absatz | – | ≤ 400 Zeichen, Leerzeile = Absatz, `**fett**` |
| `bild` | bild | bei bild-rechts, bild-hinten | `{"datei": "bestand/…" | "img/…", "alt": …, "dekorativ": true}` |
| `video_kennung` | kennung | – | `^[A-Za-z0-9_-]{11}\z`; nur bei bild-rechts; seit 3.61.0; fehlt: das Bild bleibt ein gewöhnliches Bild; mit Kennung wird es zum Startbild mit Play-Knopf, erst der Klick lädt das Video von YouTube (youtube-nocookie) |
| `video_datei` | videodatei | – | `vid/eigen-<16 Hexzeichen>.mp4`: ein MP4 im Seitenordner, ≤ 40 MB; nur bei bild-rechts; nicht zusammen mit `video_kennung`; seit 3.62.0; fehlt: das Bild bleibt ein gewöhnliches Bild; mit Datei wird es zum Startbild eines eigenen Videos mit Bedienleiste (kein Drittanbieter, nichts lädt oder startet von selbst) |
| `knopf` | knopf | – | `{"text": ≤ 40 Zeichen, "ziel": "#anker" | "https://…"}` |

```json
{
    "typ": "hero",
    "variante": "bild-rechts",
    "dachzeile": "Kostenloser Online-Abend",
    "titel": "Gesund durch den Winter",
    "text": "Was du selbst für dein Immunsystem tun kannst – verständlich erklärt.",
    "bild": {
        "datei": "bestand/beispiel-vortrag.webp"
    },
    "knopf": {
        "text": "Kostenlos anmelden",
        "ziel": "#anmeldung"
    }
}
```

### Termin (`termin`)

Varianten: `karte`, `band` · höchstens 2-mal

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `art` | auswahl | – | eines von `online`, `vor_ort` |
| `termine` | liste | ja | 1–6 Einträge |
| `termine[].beginn` | zeitpunkt | ja | `JJJJ-MM-TTTHH:MM`, Wiener Zeit |
| `termine[].dauer_min` | zahl | – | 15–600 |
| `ort` | zeile | ja | eine Zeile, ≤ 120 Zeichen |
| `wenn_leer` | zeile | ja | eine Zeile, ≤ 120 Zeichen |
| `preis` | zahl | – | 0–9999; fehlt: Die Seite zeigt keine Preiszeile und meldet Suchmaschinen weder „kostenlos“ noch einen Preis; „Teilnahme kostenlos“ und `isAccessibleForFree` gibt es nur bei ausdrücklich `preis` 0 |
| `knopf` | knopf | – | `{"text": ≤ 40 Zeichen, "ziel": "#anker" | "https://…"}` |

```json
{
    "typ": "termin",
    "variante": "karte",
    "titel": "Nächster Termin",
    "art": "online",
    "termine": [
        {
            "beginn": "2099-10-06T19:00",
            "dauer_min": 60
        }
    ],
    "ort": "Online über Zoom – den Link bekommst du per E-Mail",
    "wenn_leer": "Der nächste Termin steht bald fest."
}
```

### Themen (`themen`)

Varianten: `haken`, `zwei-spalten`

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `text` | absatz | – | ≤ 300 Zeichen, Leerzeile = Absatz, `**fett**` |
| `punkte` | liste | ja | 2–12 Einträge (eine Zeile, ≤ 120 Zeichen) |

```json
{
    "typ": "themen",
    "variante": "haken",
    "titel": "Darum geht es",
    "punkte": [
        "Wie das Immunsystem arbeitet",
        "Was Schlaf damit zu tun hat",
        "Drei einfache Gewohnheiten"
    ]
}
```

### Video (`video`)

Varianten: `breit`, `mit-text`

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 80 Zeichen |
| `text` | absatz | bei mit-text | ≤ 400 Zeichen, Leerzeile = Absatz, `**fett**` |
| `anbieter` | auswahl | – | eines von `youtube`, `datei`; Standard `youtube`; fehlt: youtube; „datei“ (eigenes Video aus dem Seitenordner, kein Drittanbieter) gibt es seit 3.62.0 |
| `kennung` | kennung | wenn `anbieter` = `youtube` | `^[A-Za-z0-9_-]{11}$`; nur wenn `anbieter` = `youtube` |
| `datei` | videodatei | wenn `anbieter` = `datei` | `vid/eigen-<16 Hexzeichen>.mp4`: ein MP4 im Seitenordner, ≤ 40 MB; nur wenn `anbieter` = `datei`; seit 3.62.0 |
| `vorschau` | bild | ja | `{"datei": "bestand/…" | "img/…", "alt": …, "dekorativ": true}` |

```json
{
    "typ": "video",
    "variante": "breit",
    "titel": "Kurz erklärt: Worum geht es?",
    "anbieter": "youtube",
    "kennung": "AbCdEfGhIjk",
    "vorschau": {
        "datei": "bestand/beispiel-video.webp"
    }
}
```

### Erfahrungen (`erfahrungen`)

Varianten: `karten`, `einzeln`

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `eintraege` | liste | ja | 1–6 Einträge |
| `eintraege[].text` | absatz | ja | ≤ 300 Zeichen, Leerzeile = Absatz, `**fett**` |
| `eintraege[].name` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `eintraege[].ort` | zeile | – | eine Zeile, ≤ 60 Zeichen |
| `eintraege[].quelle` | url | ja | `https://` auf erlaubte Hosts des Profils |

```json
{
    "typ": "erfahrungen",
    "variante": "karten",
    "titel": "Das sagen Teilnehmer",
    "eintraege": [
        {
            "text": "Sehr verständlich erklärt, ich habe viel mitgenommen.",
            "name": "Anna B.",
            "ort": "Graz",
            "quelle": "https://www.example.com/erfahrungen/1"
        }
    ]
}
```

### Häufige Fragen (`faq`)

Varianten: `standard` · höchstens 1-mal

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `fragen` | liste | ja | 1–15 Einträge |
| `fragen[].frage` | zeile | ja | eine Zeile, ≤ 150 Zeichen |
| `fragen[].antwort` | absatz | ja | ≤ 800 Zeichen, Leerzeile = Absatz, `**fett**` |

```json
{
    "typ": "faq",
    "titel": "Häufige Fragen",
    "fragen": [
        {
            "frage": "Kostet die Teilnahme etwas?",
            "antwort": "Nein, die Teilnahme ist kostenlos."
        }
    ]
}
```

### Anmeldung (`anmeldung`)

Varianten: `karte`, `breit` · höchstens 1-mal

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `text` | absatz | – | ≤ 400 Zeichen, Leerzeile = Absatz, `**fett**` |
| `danach` | absatz | – | ≤ 300 Zeichen, Leerzeile = Absatz, `**fett**` |
| `felder` | auswahl | ja | Liste aus `vorname`, `nachname`, `email`, `telefon`, muss `email` enthalten |
| `knopf_text` | zeile | ja | eine Zeile, ≤ 40 Zeichen |
| `liste` | kennung | ja | `^[a-z0-9][a-z0-9-]{1,59}$` |
| `danke` | zeile | ja | eine Zeile, ≤ 200 Zeichen |
| `plaetze` | zahl | – | 1–10000; fehlt: unbegrenzt |
| `newsletter` | janein | – | `true` / `false`; fehlt: kein Newsletter-Häkchen |

```json
{
    "typ": "anmeldung",
    "variante": "karte",
    "anker": "anmeldung",
    "titel": "Jetzt kostenlos anmelden",
    "felder": [
        "vorname",
        "email"
    ],
    "knopf_text": "Ich bin dabei",
    "liste": "beispiel-abend",
    "danke": "Danke! Bitte bestätige deine Anmeldung in der E-Mail."
}
```

### Vortragende Person (`person`)

Varianten: `schlicht`, `bild-links` · höchstens 1-mal

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `name` | zeile | – | eine Zeile, ≤ 80 Zeichen |
| `rolle` | zeile | – | eine Zeile, ≤ 100 Zeichen |
| `text` | absatz | – | ≤ 400 Zeichen, Leerzeile = Absatz, `**fett**` |
| `bild` | bild | bei bild-links | `{"datei": "bestand/…" | "img/…", "alt": …, "dekorativ": true}` |

```json
{
    "typ": "person",
    "variante": "schlicht",
    "titel": "Wer spricht?",
    "name": "Anna Beispiel",
    "rolle": "Ernährungsberaterin",
    "text": "Anna Beispiel berät zu Ernährung im Alltag. Sie erklärt verständlich und ohne Fachchinesisch."
}
```

### Abschluss (`abschluss`)

Varianten: `akzent`, `schlicht`

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 80 Zeichen |
| `text` | absatz | – | ≤ 300 Zeichen, Leerzeile = Absatz, `**fett**` |
| `knopf` | knopf | ja | `{"text": ≤ 40 Zeichen, "ziel": "#anker"}` (nur Sprungmarken der Seite) |

```json
{
    "typ": "abschluss",
    "variante": "akzent",
    "titel": "Sei dabei",
    "knopf": {
        "text": "Platz sichern",
        "ziel": "#anmeldung"
    }
}
```

### Bewegung (`bewegung`)

Varianten: `wiedergabe`, `live` · Variante `live` höchstens 1-mal

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 80 Zeichen |
| `text` | absatz | – | ≤ 400 Zeichen, Leerzeile = Absatz, `**fett**` |
| `bewegung` | bewegungsdatei | ja | `bew/<name>.bewegung`: eine Bewegungsdatei (Format „bewegung v1“) im Seitenordner, ≤ 300 KB |
| `alt` | zeile | ja | eine Zeile, ≤ 200 Zeichen |
| `stil` | auswahl | – | eines von `silhouette`, `piktogramm`, `koerper`, `strich`, `punkte`, `figur`; Standard `silhouette`; fehlt: silhouette (Vorgabe: kräftige Figur mit Kontur, auch klein gut lesbar); piktogramm ist ruhig, wie ein Notausgang-Schild; der dritte Wert ist die Figur mit Tiefe („Körper mit Volumen“) und braucht etwas mehr Rechenzeit; strich (Strichfigur) und punkte (nur die Körperpunkte) sind die älteren, schlichten Darstellungen; figur (seit 3.67.0) ist eine gezeichnete Figur mit dunklem Kopf, Gesicht aus Augen mit Pupille, Brauen und Mund, Kleidung in der Markenfarbe und heller Randlinie — dazu passt das Feld ausdruck |
| `ausdruck` | auswahl | – | eines von `neutral`, `muede`, `ueberrascht`, `entschlossen`; nur wenn `stil` = `figur`; Standard `neutral`; seit 3.67.0; fehlt: neutral (offener, ruhiger Blick, die Figur blinzelt gelegentlich); müde hat halb geschlossene Lider, Augenringe und hängende Brauen; überrascht weit aufgerissene Augen, hohe Brauen und einen kleinen runden Mund; entschlossen schmale Augen und steil nach innen gezogene Brauen. Der Ausdruck kommt von der Seite, nicht von der Kamera; bei „weniger Bewegung“ blinzelt die Figur nie |
| `requisit` | auswahl | – | eines von `stab`; seit 3.68.0; fehlt: kein Requisit. stab zeichnet einen Stab in der Hand der Figur, in jeder Darstellung und in deren Farbe. Die Aufnahme kennt keinen Stab: der Player errechnet ihn aus Handgelenk, Handpunkten und Unterarm, mit fester Länge und geglättet; fehlt in einem Bild die Hand (nicht sicher erkannt), fehlt dort auch der Stab. Passend dazu die Felder hand und laenge |
| `hand` | auswahl | – | eines von `rechts`, `links`, `beide`; nur wenn `requisit` = `stab`; Standard `rechts`; seit 3.68.0; fehlt: rechts (die rechte Hand der aufgenommenen Person); links ebenso; beide: ein Stab in beiden Händen, mittig zwischen den Händen, er ragt auf beiden Seiten heraus (nur wenn beide Hände erkannt sind) |
| `laenge` | auswahl | – | eines von `kurz`, `mittel`, `lang`; nur wenn `requisit` = `stab`; Standard `mittel`; seit 3.68.0; fehlt: mittel (0,7 Körpergrößen; kurz 0,4 wie ein Schlagstock, lang 1 wie ein Kampfstab so groß wie die Person). Die Länge richtet sich nach dem Rumpf der Figur und bleibt in der ganzen Aufnahme gleich; ein Stab, der nach unten über den Boden hinausragte, endet auf dem Boden |
| `knopf_text` | zeile | – | eine Zeile, ≤ 40 Zeichen, nicht leer; nur bei live; fehlt: Kamera starten |

```json
{
    "typ": "bewegung",
    "variante": "wiedergabe",
    "titel": "So sieht eine aufgezeichnete Bewegung aus",
    "bewegung": "bew/beispiel-winken.bewegung",
    "alt": "Eine Figur winkt dreimal mit dem rechten Arm und senkt ihn wieder."
}
```
