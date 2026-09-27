# Landingpage bearbeiten (Endoskeleton 3.51.0)

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

- **Kopfbereich** (`hero`, Varianten bild-rechts, bild-hinten, schlicht): `dachzeile` (Dachzeile, ≤ 60 Zeichen), `titel` (Titel, ≤ 80 Zeichen), `text` (Text, ≤ 400 Zeichen), `bild` (Bild), `knopf` (Knopf)

- **Termin** (`termin`, Varianten karte, band): `titel` (Titel, ≤ 60 Zeichen), `art` (Art), `termine` (Termine), `ort` (Ort, ≤ 120 Zeichen), `wenn_leer` (Text ohne Termin, ≤ 120 Zeichen), `knopf` (Knopf)

- **Themen** (`themen`, Varianten haken, zwei-spalten): `titel` (Titel, ≤ 60 Zeichen), `text` (Text, ≤ 300 Zeichen), `punkte` (Punkte)

- **Video** (`video`, Varianten breit, mit-text): `titel` (Titel, ≤ 80 Zeichen), `text` (Text, ≤ 400 Zeichen), `anbieter` (Anbieter), `kennung` (Video-Kennung), `vorschau` (Vorschaubild)

- **Erfahrungen** (`erfahrungen`, Varianten karten, einzeln): `titel` (Titel, ≤ 60 Zeichen), `eintraege` (Einträge)

- **Häufige Fragen** (`faq`, Varianten standard): `titel` (Titel, ≤ 60 Zeichen), `fragen` (Fragen)

- **Anmeldung** (`anmeldung`, Varianten karte, breit): `titel` (Titel, ≤ 60 Zeichen), `text` (Text, ≤ 400 Zeichen), `felder` (Formularfelder), `knopf_text` (Text auf dem Knopf, ≤ 40 Zeichen), `liste` (Verteilerliste), `danke` (Dankestext, ≤ 200 Zeichen)

- **Abschluss** (`abschluss`, Varianten akzent, schlicht): `titel` (Titel, ≤ 80 Zeichen), `text` (Text, ≤ 300 Zeichen), `knopf` (Knopf)
