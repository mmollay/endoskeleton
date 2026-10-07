---
name: endoskeleton-landingpage
description: Landingpages aus festen Bausteinen bauen, ändern und prüfen (Endoskeleton lp/). Verwenden, wenn eine Landingpage angelegt, ihr Bauplan seite.json befüllt oder geändert, ein Kundenprofil übernommen oder eine Seite vor der Freigabe geprüft werden soll.
---

# Landingpage-Baukasten (Endoskeleton `lp/`)

Eine Landingpage ist ein Ordner mit dem Bauplan `seite.json`, der Versionsmarke `ENDOSKELETON` und der Kopie des
Kundenprofils `profil/`. `lp/bauen.php` baut daraus ohne Netz, KI und Uhr immer dieselben Dateien: `index.html`,
`impressum.html`, `datenschutz.html`, `robots.txt` und `es/` (CSS, JS, Logo, Bilder). Aussehen, Farben, Schriften,
Firma, Rechtstexte und Einwilligung kommen **nur** aus dem Profil — im Bauplan stehen Abschnitte und Texte.

## Ablauf

`php` steht für PHP ab 8.2 mit mbstring: auf server7 `php` (8.3), auf server6 **`php8.2`** (dort ist `php` 8.5 ohne
mbstring).

```bash
php lp/neu.php <sammelordner> <slug> zoom-vortrag          # Ordner, Bauplan aus der Vorlage, Marke, git init
php lp/profil-uebernehmen.php <seite> <sammelordner>/_profil  # Profil in die Seite kopieren (nur so ändert es sich)
# seite.json füllen: jede Lücke {{…}} ersetzen
php lp/bauen.php <seite>                                   # Exit 0 gebaut, 1 Befunde, 2 technischer Fehler
php lp/pruefen.php <seite>                                 # vor jeder Freigabe; --heute JJJJ-MM-TTTHH:MM für Tests
php lp/sammelordner.php <sammelordner>                     # .htaccess des Sammelordners (404-Sperren, kein Cache)
```

Befunde kommen als JSON-Lines `{art, pfad, technik, kunde}`: `sperrend` verhindert Bau bzw. Freigabe, `hinweis` ist
zum Gegenlesen. `kunde` nennt den Abschnitt mit Nummer und Namen und ist für den Kunden geschrieben.

Im Profil optional: `--lp-logo-hoehe` (Höhe des Logos im Kopf, Vorgabe `3rem`).

Bestehende Seiten baut immer der Stand ihrer Marke:
`/home/pawbot/projects/endoskeleton-versionen/<Marke aus ENDOSKELETON>/lp/bauen.php`.

## Regeln für den Bauplan

- Nur Bausteine aus dem Katalog unten, nur ihre Felder. Unbekanntes wird abgelehnt.
- Der Kopfbereich (`hero`) steht genau einmal und zuerst; er trägt die einzige H1.
- Texte sind reiner Text: Leerzeile = neuer Absatz, `**fett**` ist die einzige Auszeichnung, echte Umlaute.
- Links nur in Knöpfen (`knopf.ziel`) und Quellen (`url`): `#anker` auf der Seite oder `https://` auf einen Host aus
  `link_hosts` des Profils.
- Bilder: `bestand/<datei>` aus `profil/bilder/` (Alt-Text aus `bilder.json`) oder `img/<datei>` im Seitenordner
  (dann `alt` Pflicht, leer nur mit `"dekorativ": true`). WebP, höchstens 400 KB. Bestandsbilder brauchen in
  `bilder.json` `"freigegeben": true`; im Seitenordner liegen nur Bilder, die der Bauplan verwendet.
- Sprungmarken (`anker`): `inhalt` und alles mit `lp-` sind vergeben. Der Abschluss-Knopf führt nur auf eine
  Sprungmarke der Seite.
- `meta.vorschaubild` wird ab Teil 4 (feste Adresse) als `og:image` ausgegeben; bis dahin nur geprüft.
- Termine als `JJJJ-MM-TTTHH:MM` (Wiener Zeit). Gebaut werden alle; vergangene blendet `lp.js` aus. Sind alle vorbei,
  sperrt die Prüfung.
- Ohne `formularziel` im Profil ist das Anmeldeformular sichtbar gesperrt und die Freigabe gesperrt.
- Das Formularziel antwortet immer mit 303 zurück auf die Seite: `?anmeldung=ok|fehler#anmeldung`. `lp.js` zeigt
  dann den Dankestext (`danke`, ein ganzer Satz) mit dem festen Zusatzsatz „Falls du schon angemeldet warst, bekommst
  du keine neue E-Mail.“ oder „Fehler“ (Texte fest, du/Sie aus dem Profil) und fokussiert ihn — das bringt ihn ins
  Bild, die Sprungmarke nimmt `lp.js` aus der Adresse. Ohne JavaScript gibt es auf der Seite keine Rückmeldung: Der
  Browser springt nur zum Formular, ein `<noscript>`-Hinweis verweist auf die E-Mail.
- Datenschutz: Die Seite verrät nie, ob eine Adresse schon auf der Liste steht — keine Meldung „bereits angemeldet“.
  Ein `?anmeldung=bereits` (älterer Stand der Weiterleitungsstelle) zeigt `lp.js` genau wie `ok`.
- Die Anmeldung trägt immer `"anker": "anmeldung"` (Ziel des Rücksprungs); sonst sperrt die Prüfung.
- Nichts Kundenspezifisches ins Endoskeleton-Repo (es ist öffentlich) — Profile liegen in `lp.<kundendomain>/_profil/`.

## Einen Baustein hinzufügen (wie GRIDKit)

Nur aus Anlass einer echten Seite: Katalogeintrag (nächste Minor-Version) → Vorlage `lp/bausteine/<typ>.php` →
CSS in `lp/lp.css` (nur Tokens) → Fixture je Variante in `lp/musterbuch/` → Test zuerst mit Gegenprobe →
`php ci/musterbuch-bauen.php` und `php ci/skill-bauen.php` → CHANGELOG nennt jede neue Klasse → VERSION zuletzt →
Tag → `scripts/version-ablegen.sh`. Namen von Bausteinen, Feldern und Varianten werden nie umbenannt oder entfernt.

## Seiteneinstellungen (`meta`)

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 60 Zeichen |
| `beschreibung` | zeile | ja | eine Zeile, ≤ 160 Zeichen |
| `vorschaubild` | bilddatei | – | `bestand/…` oder `img/…` |
| `indexieren` | janein | – | `true` / `false` |

## Bausteine (Katalog 3.52.0)

_Erzeugt aus `lp/bausteine.json` mit `php ci/skill-bauen.php` — nicht von Hand ändern._

Jeder Baustein kennt zusätzlich `typ` (Pflicht), `variante` (ohne Angabe die erste) und `anker` (Sprungmarke `[a-z][a-z0-9-]*`, Ziel für Knöpfe `#anker`).

### `hero` — Kopfbereich

Varianten: `bild-rechts`, `bild-hinten`, `schlicht` · seit 3.51.0, geändert 3.51.0 · genau einmal, als erster Abschnitt

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

### `termin` — Termin

Varianten: `karte`, `band` · seit 3.51.0, geändert 3.52.0 · höchstens 2-mal

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

### `themen` — Themen

Varianten: `haken`, `zwei-spalten` · seit 3.51.0, geändert 3.51.0

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

### `video` — Video

Varianten: `breit`, `mit-text` · seit 3.51.0, geändert 3.51.0

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

### `erfahrungen` — Erfahrungen

Varianten: `karten`, `einzeln` · seit 3.51.0, geändert 3.51.0

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

### `faq` — Häufige Fragen

Varianten: `standard` · seit 3.51.0, geändert 3.51.0 · höchstens 1-mal

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

### `anmeldung` — Anmeldung

Varianten: `karte`, `breit` · seit 3.51.0, geändert 3.52.0 · höchstens 1-mal

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

### `abschluss` — Abschluss

Varianten: `akzent`, `schlicht` · seit 3.51.0, geändert 3.51.0

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
