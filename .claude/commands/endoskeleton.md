# /endoskeleton — Endoskeleton ändern (wie GRIDKit)

Das Endoskeleton (server7 `/home/pawbot/projects/endoskeleton`, Repo `mmollay/endoskeleton`, **öffentlich**) ist die
Grundlage der SSI-Websites und des Landingpage-Baukastens `lp/`. Der Hauptordner ist DocumentRoot von skeleton.ssi.at
und Vorlage des Panel-Abgleichs — dort wird **nie** direkt gearbeitet.

## Arbeitsweg

1. **Arbeitskopie** auf server7 als pawbot (nie root), ein Zweig je Vorhaben:
   `sudo -iu pawbot git -C /home/pawbot/projects/endoskeleton worktree add ../endoskeleton-arbeit -b <zweig>`
   Bearbeiten und Browsertests gehen bequemer im Klon auf server6 (`/home/pawbot/projects/endoskeleton-klon` mit
   Arbeitskopie `../endoskeleton-arbeit`); `scripts/arbeit-nach-server7.sh` überträgt den Zweig und testet dort.
2. **Test zuerst**: `php tests/run.php` (ohne Abhängigkeiten) — der neue Test muss gegen den alten Stand rot sein.
   Danach Gegenprobe: `php8.2 /home/pawbot/projects/panel/scripts/gegenprobe.php --in <arbeitskopie> tests/run.php <datei> <suchen> <ersetzen>`
   (nur BESTANDEN zählt).
3. **Browsertests**: `ES_PLAYWRIGHT=/home/pawbot/projects/panel/node_modules/playwright node ci/browser.js`.
   **Nie `npm install`** im Endoskeleton (Schadcode-Commit `eb8a34d`).
4. **Erzeugtes neu bauen**: `php ci/musterbuch-bauen.php` (Musterbuch = Golddateien), `php ci/skill-bauen.php`
   (`lp/SKILL.md`, `lp/werkstatt.md`). Die Tests melden, wenn etwas veraltet ist.
5. **CHANGELOG** nennt jede neue `lp-`-Klasse (Test erzwingt es), **VERSION zuletzt** (auch Konfigurator
   `ENDO_VERSION`/`?v=` und `package.json` — Test `versionen`).
6. **Abnahme** durch höchstens zwei lesende Prüfer über `git diff main...<zweig>`.
7. **⛔ Nur mit Martins Freigabe:** `merge --ff-only` in den Hauptordner, Tag `v<X.Y.Z>`,
   `scripts/version-ablegen.sh <X.Y.Z>` (legt den Stand schreibgeschützt nach `endoskeleton-versionen/<X.Y.Z>/`),
   Push (pawbot hat nur auf server6 eine GitHub-Anmeldung).

## Landingpages

Siehe `lp/SKILL.md` (Ablauf, Regeln, Katalog). Bestehende Seiten baut immer der Stand ihrer Marke:
`/home/pawbot/projects/endoskeleton-versionen/<Inhalt von ENDOSKELETON>/lp/bauen.php <seite>`.

## Grenzen

- Nichts Kundenspezifisches ins Repo (Test `neutral`); Profile liegen in `websites/lp.<kundendomain>/_profil/` (privat).
- `base.css`, `shared.js`, Presets, `pages/`, `api/` nur mit eigenem Anlass ändern — bestehende Websites nutzen sie
  (Test `bestand` hält den Stand von 3.50.1 fest).
- Gesperrtes auf den Webservern immer als 404 (`RedirectMatch`), nie `Require all denied` — fail2ban auf server7
  sperrt sonst die aufrufende Adresse für eine Stunde.
