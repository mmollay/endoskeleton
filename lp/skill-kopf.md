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
- Nichts Kundenspezifisches ins Endoskeleton-Repo (es ist öffentlich) — Profile liegen in `lp.<kundendomain>/_profil/`.

## Einen Baustein hinzufügen (wie GRIDKit)

Nur aus Anlass einer echten Seite: Katalogeintrag (nächste Minor-Version) → Vorlage `lp/bausteine/<typ>.php` →
CSS in `lp/lp.css` (nur Tokens) → Fixture je Variante in `lp/musterbuch/` → Test zuerst mit Gegenprobe →
`php ci/musterbuch-bauen.php` und `php ci/skill-bauen.php` → CHANGELOG nennt jede neue Klasse → VERSION zuletzt →
Tag → `scripts/version-ablegen.sh`. Namen von Bausteinen, Feldern und Varianten werden nie umbenannt oder entfernt.
