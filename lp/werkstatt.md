# Landingpage bearbeiten (Endoskeleton 3.52.0)

_Erzeugt aus `lp/bausteine.json` mit `php ci/skill-bauen.php` — nicht von Hand ändern._

- Du änderst ausschließlich `seite.json`, den Bauplan der Seite. HTML, CSS und das Profil fasst du nie an.
- Die Seite besteht aus Abschnitten (Bausteinen) in fester Form. Aussehen, Farben und Schriften kommen aus dem Profil des Kunden.
- Der Kopfbereich (`hero`) steht genau einmal und ganz oben. Anmeldung und Häufige Fragen gibt es höchstens einmal.
- Links nur in Knöpfen und Quellen, nur `https://` auf die freigegebenen Websites des Kunden oder `#anker` auf der Seite.
- Texte ohne HTML. Leerzeile = neuer Absatz, `**fett**` ist die einzige Auszeichnung. Echte Umlaute (ä, ö, ü, ß).
- Keine Heil- oder Wirkversprechen; die Sperrliste des Kunden wird geprüft, der Kunde gibt die Seite frei.
- Bilder: `bestand/<datei>` aus dem Bildbestand des Kunden oder `img/<datei>` im Seitenordner (dann mit `alt`, leer nur mit `"dekorativ": true`).
- Nach jeder Änderung bauen und prüfen; Befunde nennen den Abschnitt mit Nummer und Namen.

## Abschnitte

### Kopfbereich (`hero`)

Varianten: `bild-rechts`, `bild-hinten`, `schlicht` · genau einmal, als erster Abschnitt

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `dachzeile` | zeile | – | eine Zeile, ≤ 60 Zeichen |
| `titel` | zeile | ja | eine Zeile, ≤ 80 Zeichen |
| `text` | absatz | – | ≤ 400 Zeichen, Leerzeile = Absatz, `**fett**` |
| `bild` | bild | bei bild-rechts, bild-hinten | `{"datei": "bestand/…" | "img/…", "alt": …, "dekorativ": true}` |
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
| `preis` | zahl | – | 1–9999 |
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
| `anbieter` | auswahl | ja | eines von `youtube` |
| `kennung` | kennung | ja | `^[A-Za-z0-9_-]{11}$` |
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
| `plaetze` | zahl | – | 1–10000 |

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
