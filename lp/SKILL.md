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
- Eigenes Video (seit 3.62.0): `video_datei` im Kopfbereich (`bild-rechts`) oder `anbieter` `datei` mit `datei` im Baustein `video`.
  Der Pfad ist immer `vid/eigen-<16 Hexzeichen>.mp4` (nur MP4, höchstens 40 MB, die Datei liegt im Seitenordner und wird vom
  Betreiber angelegt — nie einen Pfad erfinden). Das Bild des Abschnitts ist das Startbild; das Video lädt und startet nie von selbst,
  es gibt keinen Drittanbieter. `video_datei` und `video_kennung` (YouTube) schließen sich aus; im Seitenordner liegen nur Videos,
  auf die der Bauplan verweist.
- Sprungmarken (`anker`): `inhalt` und alles mit `lp-` sind vergeben. Der Abschluss-Knopf führt nur auf eine
  Sprungmarke der Seite.
- Teilen des Seitenlinks (WhatsApp, Telegram, Facebook, LinkedIn, Slack, X): Mit `meta.adresse` (die absolute Adresse
  `https://<host>/` der Live-Seite, vom Betreiber gesetzt, Host aus `link_hosts` oder eine Unteradresse davon) gibt der Bau
  `og:url`, `og:image` (+ Typ, Maße, Alt-Text) und `twitter:card` aus. Das Bild ist `meta.vorschaubild`, sonst das Bild des
  Kopfbereichs; der Bau macht daraus `es/vorschau.jpg` (JPEG, 1200 × 630, aus der Mitte zugeschnitten, höchstens 300 KB —
  WebP liefern nicht alle Server mit Content-Type und nicht alle Netzwerke lesen es). Ohne Bild oder Adresse gibt es keine
  Bildkarte, nie eine kaputte. Eine Seite, die nicht in Suchmaschinen erscheinen soll, bekommt mit Karte eine robots.txt,
  die nur den Link-Vorschau-Crawlern erlaubt, sie zu lesen (sonst zeigen Facebook, LinkedIn, X und Slack nichts).
  Ohne `meta.adresse` bleibt `meta.vorschaubild` ohne Wirkung (die Prüfung weist darauf hin).
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
- Zwei Häkchen (3.55.0): Die Pflicht-Einwilligung (`einwilligung` des Profils) deckt nur Zugangslink und Erinnerung.
  Trägt die Anmeldung `"newsletter": true`, steht darunter ein zweites, freiwilliges Häkchen `newsletter` (nie Pflicht, nie
  vorangehakt, „(freiwillig)“ im Label) mit dem Text `einwilligung_newsletter` des Profils, sonst „Ja, ich möchte zusätzlich
  Neuigkeiten von <Name> per E-Mail bekommen. Jederzeit abbestellbar.“; seine Kurzsumme geht als `newsletter_kurz` mit.
  `newsletter` setzt der Betreiber (das Panel, wenn eine Newsletter-Gruppe gewählt ist) — nie selbst setzen oder entfernen.
- Statistik (3.56.0): `lp.js` zählt anonym Aufruf, Formularbeginn und Absenden beim Panel (Formularziel `…/lp/anmelden` →
  `…/lp/treffer`) — ohne Cookies, ohne Web-Speicher, ohne Kennung des Besuchers; gesendet werden nur Verweiser-Host, UTM
  und Fensterbreite; beim Verlassen der Seite (3.57.0) einmal auch die sichtbare Zeit (ganze Sekunden, höchstens 3600) und
  die Scrolltiefe (0 | 25 | 50 | 75 | 100 %). Es gibt dafür keine Einstellung. Abmeldung einer Seite: `data-lp-statistik="aus"` am `<body>` (Do Not
  Track und Global Privacy Control beachtet `lp.js` immer). Die Datenschutzerklärung der Seite (`rechtliches/datenschutz.txt`
  im Profil) sollte es nennen, z. B.: „Wir zählen Aufrufe dieser Seite anonym — ohne Cookies und ohne Speicherung Ihrer
  IP-Adresse.“ Den Wortlaut vorher mit der Gegenstelle abgleichen (auch ein Webserver-Protokoll führt Adressen).
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
| `vorschaubild` | bilddatei | – | `bestand/…` oder `img/…`; fehlt: beim Teilen des Links erscheint das Bild des Kopfbereichs (ohne Kopfbild: keine Bildkarte) |
| `adresse` | kennung | – | `^https://[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+/\z`; fehlt: die Seite gibt beim Teilen keine Karte aus (og:url, og:image, twitter:card); setzt der Betreiber, nie der Kunde |
| `indexieren` | janein | – | `true` / `false` |

## Bausteine (Katalog 3.64.2)

_Erzeugt aus `lp/bausteine.json` mit `php ci/skill-bauen.php` — nicht von Hand ändern._

Jeder Baustein kennt zusätzlich `typ` (Pflicht), `variante` (ohne Angabe die erste) und `anker` (Sprungmarke `[a-z][a-z0-9-]*`, Ziel für Knöpfe `#anker`).

### `hero` — Kopfbereich

Varianten: `bild-rechts`, `bild-hinten`, `schlicht` · seit 3.51.0, geändert 3.62.0 · genau einmal, als erster Abschnitt

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

Varianten: `breit`, `mit-text` · seit 3.51.0, geändert 3.62.0

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

Varianten: `karte`, `breit` · seit 3.51.0, geändert 3.55.0 · höchstens 1-mal

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

### `person` — Vortragende Person

Varianten: `schlicht`, `bild-links` · seit 3.52.0, geändert 3.52.0 · höchstens 1-mal

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

### `bewegung` — Bewegung

Varianten: `wiedergabe`, `live` · seit 3.64.0, geändert 3.64.2 · Variante `live` höchstens 1-mal

| Feld | Typ | Pflicht | Grenzen |
|---|---|---|---|
| `titel` | zeile | ja | eine Zeile, ≤ 80 Zeichen |
| `text` | absatz | – | ≤ 400 Zeichen, Leerzeile = Absatz, `**fett**` |
| `bewegung` | bewegungsdatei | ja | `bew/<name>.bewegung`: eine Bewegungsdatei (Format „bewegung v1“) im Seitenordner, ≤ 300 KB |
| `alt` | zeile | ja | eine Zeile, ≤ 200 Zeichen |
| `stil` | auswahl | – | eines von `silhouette`, `piktogramm`, `koerper`, `strich`, `punkte`; Standard `silhouette`; fehlt: silhouette (Vorgabe: kräftige Figur mit Kontur, auch klein gut lesbar); piktogramm ist ruhig, wie ein Notausgang-Schild; der dritte Wert ist die Figur mit Tiefe („Körper mit Volumen“) und braucht etwas mehr Rechenzeit; strich (Strichfigur) und punkte (nur die Körperpunkte) sind die älteren, schlichten Darstellungen |
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
