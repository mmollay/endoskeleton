# Web-Generator Foundation — Design

**Datum:** 2026-04-11
**Status:** Approved (Block 1+2), Block 3 out of scope
**Autor:** Claude + Martin
**Sub-Projekt:** A (Foundation & Safety Net)

## Problem

Der SSI Web-Generator (Scanner + Endoskeleton + Konfigurator) läuft produktiv auf S7, aber die Quellen sind nicht sauber versioniert:

- `~/pawbot/core/templates/endoskeleton/` ist kein Git-Repo — lokale Arbeitskopie mit v3.31.2, nicht committed
- `github.com/mmollay/endoskeleton` ist 5 Tage hinter dem lokalen Stand (letzter Push 2026-04-06)
- Scanner-Code lebt **nur** auf S7 unter `/home/pawbot/projects/scanner/` — kein Git, kein Backup, keine Historie
- Endoskeleton-Arbeitskopie liegt **innerhalb** des PawBot-Verzeichnisbaums → logisch getrennte Systeme sind physisch verzahnt
- Deploy ist manueller `scp` ohne Verification
- Keine Smoke-Tests → jede Änderung kann still brechen
- ⚠️ GitHub Personal Access Token im Klartext in alter Remote-Config

Solange das so ist, ist jede weitere Arbeit am Produkt ein Blindflug.

## Ziele

- **Git-Sicherheit:** Scanner v1.3.0 und Endoskeleton v3.31.2 sind gesichert, getaggt, gepusht
- **Repo-Trennung:** Drei unabhängige Produkte in drei eigenen Repos, keine Verzahnung
- **Kontaminations-Firewall:** PawBot arbeitet parallel weiter (v5.13.x auf S8), ohne dass Web-Generator-Arbeit PawBot bricht oder umgekehrt
- **Deploy-Automation:** Pro Repo ein `deploy.sh` mit Pre-Flight-Checks und Post-Deploy-Verification
- **Smoke-Tests:** Pro Repo ein Minimal-Test (~30s), der vor jedem Deploy läuft
- **Sicherheit:** Exponierter GitHub-Token rotiert

## Nicht-Ziele (explizit aufgeschoben)

- Scanner-Qualitätsverbesserungen (Hero-Image-Extraktion, JS-Rendering, Multi-Image-Mapping) → Sub-Projekt B
- /endo Parallelisierung und Telegram-Inline-Keyboard-Fixes → Sub-Projekt C
- End-to-End-Regressionstests mit Playwright über mehrere Domains → Sub-Projekt D
- GitHub-Actions CI-Pipeline → nachgelagert
- PawBot-Plugin-Abnabelung (Löschen von `~/pawbot/core/templates/endoskeleton/`) → nachgelagert, nur wenn PawBot-Plugin ausschließlich HTTP spricht
- Panel-Integration, Multi-Tenant-Scoping, Reseller-Konzept → Sub-Projekt E (weit in der Zukunft)

## Ziel-Architektur

Drei unabhängige Produkte, drei Repos, drei Deploy-Wege:

```
~/code/
├── ssi-scanner/                       NEU
│   ├── api/                           PHP-Quellen (von S7 gezogen)
│   ├── scripts/deploy.sh              rsync + Cache-Purge + Smoke-Test
│   ├── tests/smoke.sh                 scannt obststadt.at, prüft Schema
│   ├── .gitignore                     scans/, logs/, node_modules/, .env
│   ├── VERSION                        1.3.0
│   ├── CHANGELOG.md                   rückwirkend aus Memory-Notizen
│   └── README.md                      API-Doku aus Schema generiert
│   GitHub:  mmollay/ssi-scanner (private, neu)
│   Deploy:  S7 /home/pawbot/projects/scanner/
│
├── ssi-endoskeleton/                  KLON + Merge
│   ├── api/                           PHP-API
│   ├── js/content-injector.js         v3.31.2
│   ├── konfigurator.html              v3.31.2
│   ├── presets/, buttons/, colors/    Design-System
│   ├── scripts/deploy.sh              rsync + Cache-Purge + Smoke-Test
│   ├── tests/smoke.sh                 prüft Konfigurator-HTML + recommend-API
│   ├── VERSION                        3.31.2
│   └── CHANGELOG.md                   bereits vorhanden, wird weitergeführt
│   GitHub:  mmollay/endoskeleton (existiert, wird aktualisiert)
│   Deploy:  S7 /home/pawbot/projects/endoskeleton/
│
└── pawbot/                            UNBERÜHRT
    GitHub:  mmollay/pawbot (Martin arbeitet aktiv dran, v5.13.18)
    Hinweis: ~/pawbot/core/templates/endoskeleton/ wird zu totem Code,
             wird aber in diesem Sub-Projekt NICHT angefasst.
```

**Kontaminations-Firewall:** Ab sofort gehen alle Endoskeleton-/Scanner-Änderungen in die neuen Repos unter `~/code/`. Die pawbot-interne Kopie ist eingefroren.

## Ablauf heute (Execution)

### Schritt 1 — Scanner ins Git holen (~15 min)

1. SSH zu S7, `tar czf /tmp/scanner.tar.gz --exclude='scans' --exclude='node_modules' --exclude='.env' /home/pawbot/projects/scanner`
2. `scp` nach lokal, entpacken nach `~/code/ssi-scanner/`
3. `.gitignore` schreiben: `scans/`, `logs/`, `node_modules/`, `.env`, `*.log`, `api-keys/`
4. `VERSION` auf `1.3.0` setzen (passend zum API-Schema)
5. `CHANGELOG.md` mit Initial-Eintrag "v1.3.0 — Initial import from S7 (2026-04-11)"
6. `README.md` generieren aus `/api/v1/schema` Response (Endpoint-Liste, Beispiele)
7. `git init`, initial commit "feat: initial import v1.3.0 from S7"
8. `gh repo create mmollay/ssi-scanner --private --source=. --push`
9. Tag `v1.3.0` + Push

### Schritt 2 — Endoskeleton-Repo aufräumen (~20 min)

1. `git clone https://github.com/mmollay/endoskeleton ~/code/ssi-endoskeleton`
2. `diff -r ~/code/ssi-endoskeleton ~/pawbot/core/templates/endoskeleton` → Liste geänderter Files
3. Geänderte Files übernehmen, aber in **drei sauberen Commits** entlang der Version-Historie:
   - Commit 1: `v3.31.0 — content-injector _setTextAndLock, Home-Dedup, Hero multi-variant`
   - Commit 2: `v3.31.1 — title shortening für Nav/About/Services`
   - Commit 3: `v3.31.2 — whitespace-required split regex (Smart-Kit fix)`
4. CHANGELOG-Einträge (schon vorhanden in lokaler Kopie) mitnehmen
5. Jeweils `VERSION`-Datei im passenden Commit mitbumpen
6. Tag `v3.31.2` setzen, push

### Schritt 3 — Deploy-Skripte pro Repo (~15 min)

Pro Repo `scripts/deploy.sh` mit diesem Ablauf:
1. Pre-Flight: `git status` sauber, `VERSION` neuer als letzter Tag, auf `main`
2. `rsync -av --exclude-from=.gitignore` nach S7-Ziel
3. Cloudflare Cache-Purge (Endoskeleton hat bereits das Snippet im Memory)
4. Post-Deploy: `tests/smoke.sh` ausführen, bei Fehler Exit 1

### Schritt 4 — Smoke-Tests pro Repo (~20 min)

**`ssi-scanner/tests/smoke.sh`:**
- `POST /api/v1/scan` mit `{"domain":"obststadt.at","options":{"analyze":true,"ai_provider":"gemini"}}`
- `jq` prüft: `.total_pages > 0`, `.total_words > 100`, `.colors | length >= 3`, `.ai_analysis.branch != null`
- Exit 0 wenn alle passen, sonst Exit 1 mit lesbarer Fehlermeldung

**`ssi-endoskeleton/tests/smoke.sh`:**
- `curl https://skeleton.ssi.at/konfigurator.html` → prüft dass `content-injector.js?v=3.31.2` im HTML steht
- `POST /api/v1/recommend` mit Beispiel-Scan-Daten → erwartet `recommended.preset` Feld
- `GET /api/v1/presets` → erwartet ≥22 Presets
- Exit 0/1 mit lesbarer Ausgabe

Beide Tests laufen <30s, damit sie im Deploy-Gate nicht nerven.

### Schritt 5 — Token rotieren (~5 min)

1. GitHub-Settings → alten PAT `ghp_BDs7...` widerrufen
2. Neuer Token über `gh auth login` (schreibt nach `~/.config/gh/hosts.yml`, nicht in Remote-URLs)
3. In allen Repos Remote-URLs auf `https://github.com/...` ohne Token umstellen
4. `gh auth status` prüfen

## Erfolgs-Kriterien

Am Ende von heute gilt:

- [ ] `~/code/ssi-scanner/` existiert, ist ein Git-Repo, auf GitHub gepusht, Tag `v1.3.0` gesetzt
- [ ] `~/code/ssi-endoskeleton/` existiert, ist auf v3.31.2 aktuell, auf GitHub gepusht, Tag `v3.31.2` gesetzt
- [ ] `scripts/deploy.sh` läuft in beiden Repos fehlerfrei durch (dry-run gegen S7)
- [ ] `tests/smoke.sh` läuft in beiden Repos grün
- [ ] Alter GitHub-PAT widerrufen, neuer Token aktiv
- [ ] `~/pawbot/` unverändert, PawBot v5.13.18 auf S8 unbeeinträchtigt
- [ ] Dieser Spec ist in `~/code/ssi-endoskeleton/docs/superpowers/specs/` committed (umziehen nach Step 2)

## Risiken & Mitigation

| Risiko | Mitigation |
|---|---|
| S7 Scanner-Code hat lokale Änderungen die nicht in Memory dokumentiert sind | `tar` kompletten Stand, `git diff` später begutachten vor Push |
| Endoskeleton-GitHub-Historie hat Commits die lokal fehlen | `git pull --rebase` vor dem Aufsetzen der v3.31.x Commits |
| S7 Scanner hat laufende Scans während `tar` | `tar` ignoriert `scans/` Verzeichnis → keine Interferenz |
| Cloudflare Cache-Purge API rate-limited | Post-Deploy-Smoke-Test mit 10s Retry |
| PawBot-Deploy bricht weil `~/pawbot/core/templates/endoskeleton/` plötzlich "veraltet" | Wird NICHT angefasst — pawbot-interne Kopie bleibt, bis PawBot-Plugin sauber abgenabelt ist |

## Out of Scope (für spätere Sub-Projekte)

- **Sub-Projekt B — Scanner-Qualität:** Hero-Image-Extraktion, og:image fallback, JS-Rendering waitUntil networkidle, Services-Cards multi-image mapping, Gemini-Prompt für branch-Erkennung verbessern
- **Sub-Projekt C — /endo Performance:** Parallelisierung der Claude/Gemini-Verfeinerung (3-8min → <1min), Telegram-Inline-Keyboard-Override-Fix, _fix_css_links reparieren oder ersetzen
- **Sub-Projekt D — E2E-Testing:** Playwright Full-Page Screenshots als Regression-Baseline über 3+ Domains, 22 Presets × N Domains Matrix
- **Sub-Projekt E — Panel-Integration:** Multi-Tenant, Reseller, Versionsverwaltung im panel.ssi.at
- **PawBot-Plugin-Abnabelung:** Sobald `/endo` ausschließlich über HTTP die Web-Generator-APIs spricht, `~/pawbot/core/templates/endoskeleton/` löschen
