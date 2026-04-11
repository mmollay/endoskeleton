# Web-Generator Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scanner v1.3.0 und Endoskeleton v3.31.2 in zwei saubere Git-Repos bringen, mit Deploy-Scripts und Smoke-Tests, ohne PawBot anzufassen.

**Architecture:** Drei unabhängige Produkte in `~/code/` — ssi-scanner (neu von S7 gezogen), ssi-endoskeleton (GitHub-Klon + lokale v3.31.2 gemerged), pawbot (unverändert). Deploy ist rsync nach S7 mit Pre-Flight-Checks und Post-Deploy-Smoke-Test.

**Tech Stack:** Bash, Git, GitHub CLI (gh), SSH (server7 = root), rsync, curl, jq, Cloudflare Purge API

**Spec:** `~/code/docs/superpowers/specs/2026-04-11-web-generator-foundation-design.md`

---

## File Structure

```
~/code/
├── ssi-scanner/                        Task 2-7
│   ├── api/                           (aus S7 tar)
│   ├── scripts/deploy.sh              Task 6
│   ├── tests/smoke.sh                 Task 5
│   ├── .gitignore                     Task 3
│   ├── VERSION                        Task 3
│   ├── CHANGELOG.md                   Task 3
│   └── README.md                      Task 3
├── ssi-endoskeleton/                   Task 8-12
│   ├── [bestehender Endoskeleton-Code] Task 8 (git clone)
│   ├── js/content-injector.js         Task 9 (v3.31.2)
│   ├── konfigurator.html              Task 9
│   ├── VERSION                        Task 9
│   ├── CHANGELOG.md                   Task 9
│   ├── scripts/deploy.sh              Task 11
│   ├── tests/smoke.sh                 Task 10
│   └── docs/superpowers/specs/        Task 12
│       └── 2026-04-11-web-generator-foundation-design.md
└── docs/                               (temporär, Inhalt zieht nach Task 12)
```

---

## Task 1: Preflight & Workspace Setup

**Files:**
- Create: `~/code/` (already exists)

- [ ] **Step 1: Verify SSH to server7 works**

Run: `ssh -o BatchMode=yes -o ConnectTimeout=5 server7 'whoami; ls /home/pawbot/projects/scanner/ | head -5'`
Expected: Prints `root` and lists at least `api`, `CHANGELOG.md` or similar scanner files. If fails: abort and fix SSH config before continuing.

- [ ] **Step 2: Verify gh CLI is authenticated**

Run: `gh auth status`
Expected: `Logged in to github.com account mmollay` with keyring token. If not: run `gh auth login` first.

- [ ] **Step 3: Verify ~/code workspace exists**

Run: `ls -la ~/code/`
Expected: Directory exists with at least `docs/` subdir (from spec creation).

- [ ] **Step 4: Verify local Endoskeleton working copy exists**

Run: `ls ~/pawbot/core/templates/endoskeleton/VERSION && cat ~/pawbot/core/templates/endoskeleton/VERSION`
Expected: Prints `3.31.2`. This is the source of truth for the Endoskeleton merge in Task 9.

---

## Task 2: Pull Scanner from S7

**Files:**
- Create: `~/code/ssi-scanner/` (extracted from S7 tar)

- [ ] **Step 1: Inspect S7 scanner directory**

Run: `ssh server7 'ls -la /home/pawbot/projects/scanner/; du -sh /home/pawbot/projects/scanner/scans /home/pawbot/projects/scanner/node_modules 2>/dev/null'`
Expected: Directory listing. Note total size of `scans/` and `node_modules/` — these get excluded.

- [ ] **Step 2: Create tar on S7 excluding runtime data**

Run:
```bash
ssh server7 'cd /home/pawbot/projects && tar czf /tmp/ssi-scanner-v1.3.0.tar.gz \
  --exclude="scanner/scans" \
  --exclude="scanner/node_modules" \
  --exclude="scanner/.env" \
  --exclude="scanner/logs" \
  --exclude="scanner/*.log" \
  scanner && ls -lh /tmp/ssi-scanner-v1.3.0.tar.gz'
```
Expected: File created, size typically <5MB (code only, no scans).

- [ ] **Step 3: Download tar to local**

Run: `scp server7:/tmp/ssi-scanner-v1.3.0.tar.gz /tmp/ && ls -lh /tmp/ssi-scanner-v1.3.0.tar.gz`
Expected: File downloaded.

- [ ] **Step 4: Extract into workspace**

Run:
```bash
mkdir -p ~/code && cd ~/code && tar xzf /tmp/ssi-scanner-v1.3.0.tar.gz && \
mv scanner ssi-scanner && ls ~/code/ssi-scanner/
```
Expected: `ssi-scanner/` directory with `api/`, likely `CHANGELOG.md`, `VERSION`, etc.

- [ ] **Step 5: Clean up S7 tar**

Run: `ssh server7 'rm /tmp/ssi-scanner-v1.3.0.tar.gz'`
Expected: No output.

- [ ] **Step 6: Verify no secrets leaked into extract**

Run: `grep -rE "(api[_-]?key|secret|token|password)" ~/code/ssi-scanner/ --include="*.php" --include="*.json" --include="*.env*" -l 2>/dev/null | head -20`
Expected: Should show only example config files, NOT real `.env` or actual keys. If any real secrets found → stop and clean them before git init.

---

## Task 3: Scaffold Scanner repo files

**Files:**
- Create: `~/code/ssi-scanner/.gitignore`
- Create: `~/code/ssi-scanner/VERSION`
- Verify/Create: `~/code/ssi-scanner/CHANGELOG.md`
- Create: `~/code/ssi-scanner/README.md`

- [ ] **Step 1: Write .gitignore**

Create `~/code/ssi-scanner/.gitignore` with content:
```gitignore
# Runtime data
scans/
logs/
*.log

# Dependencies
node_modules/
vendor/

# Environment & secrets
.env
.env.*
!.env.example
api-keys/
*.pem
*.key

# OS & editors
.DS_Store
Thumbs.db
.idea/
.vscode/
*.swp

# Temp files
/tmp/
*.tmp
```

- [ ] **Step 2: Write VERSION file**

Run: `echo "1.3.0" > ~/code/ssi-scanner/VERSION`
Expected: File created with single line `1.3.0`.

- [ ] **Step 3: Check if CHANGELOG.md exists in extract**

Run: `ls -la ~/code/ssi-scanner/CHANGELOG.md 2>&1`
Expected: Either file exists (keep it) or not found (create in next step).

- [ ] **Step 4: Create CHANGELOG.md if missing**

If file from Step 3 does not exist, create `~/code/ssi-scanner/CHANGELOG.md`:
```markdown
# Changelog — SSI Scanner

Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)

---

## [1.3.0] — 2026-04-11

### Added
- Initial Git-Import aus S7 (`/home/pawbot/projects/scanner/`)
- REST API v1 (POST /scan, GET /scans/{domain}, screenshots, images)
- Multi-Provider KI-Analyse (Gemini, OpenAI, Anthropic)
- Playwright-basierter Crawler (bis 30 Seiten)
- Bild-Download + Desktop/Mobile-Screenshots
```

- [ ] **Step 5: Generate README.md from live API schema**

Run:
```bash
curl -sS https://scanner.ssi.at/api/v1/schema > /tmp/scanner-schema.json
cat > ~/code/ssi-scanner/README.md <<'EOF'
# SSI Scanner

Website-Scanner mit Playwright-Crawler, Bild-Download, Screenshots und Multi-Provider KI-Analyse.

**Live:** https://scanner.ssi.at/api/v1/
**Version:** 1.3.0
**Server:** S7 (`/home/pawbot/projects/scanner/`)

## API Endpoints

| Method | Path | Zweck |
|--------|------|-------|
| POST | `/api/v1/scan` | Website scannen (domain, options: max_pages, analyze, ai_provider) |
| GET | `/api/v1/scans/{domain}` | Scan-Ergebnis (JSON) |
| GET | `/api/v1/scans/{domain}/status` | Scan-Status + Progress |
| GET | `/api/v1/scans/{domain}/images/{file}` | Heruntergeladenes Bild |
| GET | `/api/v1/scans/{domain}/screenshots/{type}` | PNG Screenshot (desktop/mobile) |
| GET | `/api/v1/schema` | OpenAPI 3.0 Schema |

## Beispiel: Scan starten

```bash
curl -X POST https://scanner.ssi.at/api/v1/scan \
  -H "Content-Type: application/json" \
  -d '{
    "domain": "obststadt.at",
    "options": {
      "max_pages": 30,
      "analyze": true,
      "ai_provider": "gemini"
    }
  }'
```

## Antwort-Felder (wichtig)

- `colors[]` — Top-Farben als Hex (Top-Level, NICHT in `ai_analysis`)
- `logo: {src, alt, width, height}`
- `pages[]` — Crawl-Ergebnisse
- `images[]`, `screenshots: {desktop, mobile}`
- `seo: {title, description}`
- `ai_analysis: {branch, mood, ...}` — nur bei `analyze: true`

## Deploy

```bash
./scripts/deploy.sh
```

Siehe `scripts/deploy.sh` für Pre-Flight-Checks.

## Tests

```bash
./tests/smoke.sh
```

Testet End-to-End gegen Live-API (scanned obststadt.at).

## Verwandte Repos

- **ssi-endoskeleton** — Template-System, konsumiert Scanner-Daten
- **pawbot** — Telegram-Bot mit /endo Plugin, nutzt diese API
EOF
```
Expected: README.md geschrieben, Temp-Schema-Datei ignoriert.

- [ ] **Step 6: Verify all scaffolding files**

Run: `ls -la ~/code/ssi-scanner/{.gitignore,VERSION,CHANGELOG.md,README.md}`
Expected: All four files exist.

---

## Task 4: Git init Scanner + GitHub push

**Files:**
- Create: `~/code/ssi-scanner/.git/`
- Create: GitHub repo `mmollay/ssi-scanner`

- [ ] **Step 1: Git init + initial add**

Run:
```bash
cd ~/code/ssi-scanner && git init -b main && git add -A && git status -s | head -20
```
Expected: Files staged. Verify no `scans/`, `.env`, or `node_modules/` in list.

- [ ] **Step 2: Initial commit**

Run:
```bash
cd ~/code/ssi-scanner && git commit -m "feat: initial import v1.3.0 from S7

Initial import of SSI Scanner (scanner.ssi.at) from
/home/pawbot/projects/scanner/ on S7.

Excluded at import:
- scans/ (runtime data, ~GB)
- node_modules/ (dependencies)
- .env (secrets)
- logs/ (runtime)

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```
Expected: Commit created with short hash.

- [ ] **Step 3: Create GitHub repo (destructive — requires confirmation in interactive runs)**

Run: `cd ~/code/ssi-scanner && gh repo create mmollay/ssi-scanner --private --source=. --description "SSI Scanner — Website-Crawler mit Playwright, Bildern, Screenshots und KI-Analyse" --push`
Expected: Repo created on GitHub, main branch pushed.

- [ ] **Step 4: Tag v1.3.0 + push tag**

Run: `cd ~/code/ssi-scanner && git tag -a v1.3.0 -m "v1.3.0 — initial import from S7" && git push origin v1.3.0`
Expected: Tag pushed.

- [ ] **Step 5: Verify on GitHub**

Run: `gh repo view mmollay/ssi-scanner --json name,pushedAt,defaultBranchRef,visibility`
Expected: JSON shows `"visibility":"PRIVATE"`, recent `pushedAt`, `"name":"main"`.

---

## Task 5: Scanner smoke test

**Files:**
- Create: `~/code/ssi-scanner/tests/smoke.sh`

- [ ] **Step 1: Write smoke test script**

Create `~/code/ssi-scanner/tests/smoke.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail

# Smoke test for SSI Scanner — hits live API at scanner.ssi.at
# Exit 0 = all assertions pass, Exit 1 = any assertion fails

API="https://scanner.ssi.at/api/v1"
TEST_DOMAIN="obststadt.at"

echo "→ Smoke test: SSI Scanner API"
echo "  API: $API"
echo "  Test domain: $TEST_DOMAIN"
echo

# 1) Schema endpoint reachable
echo -n "1) GET /schema ... "
SCHEMA_VERSION=$(curl -sS -m 10 "$API/schema" | jq -r '.info.version // "missing"')
if [ "$SCHEMA_VERSION" = "missing" ]; then
  echo "FAIL (no version in schema)"
  exit 1
fi
echo "OK (v$SCHEMA_VERSION)"

# 2) Cached scan for obststadt.at has required fields
echo -n "2) GET /scans/$TEST_DOMAIN ... "
SCAN=$(curl -sS -m 15 "$API/scans/$TEST_DOMAIN")
PAGES=$(echo "$SCAN" | jq -r '.total_pages // 0')
WORDS=$(echo "$SCAN" | jq -r '.total_words // 0')
COLORS=$(echo "$SCAN" | jq -r '.colors | length')
BRANCH=$(echo "$SCAN" | jq -r '.ai_analysis.branch // .branch // "null"')

FAIL=0
[ "$PAGES" -lt 1 ] && { echo; echo "   FAIL: total_pages=$PAGES (expected >0)"; FAIL=1; }
[ "$WORDS" -lt 100 ] && { echo; echo "   FAIL: total_words=$WORDS (expected >=100)"; FAIL=1; }
[ "$COLORS" -lt 3 ] && { echo; echo "   FAIL: colors count=$COLORS (expected >=3)"; FAIL=1; }
[ "$BRANCH" = "null" ] && { echo; echo "   FAIL: branch is null (expected non-null, re-run scan with analyze:true)"; FAIL=1; }
[ $FAIL -eq 1 ] && exit 1
echo "OK (pages=$PAGES, words=$WORDS, colors=$COLORS, branch=$BRANCH)"

# 3) Screenshots endpoint serves PNGs
echo -n "3) GET /scans/$TEST_DOMAIN/screenshots/desktop ... "
CT=$(curl -sS -m 10 -I "$API/scans/$TEST_DOMAIN/screenshots/desktop" | grep -i "^content-type:" | tr -d '\r' | awk '{print $2}')
if [[ "$CT" != image/png* ]]; then
  echo "FAIL (content-type=$CT, expected image/png)"
  exit 1
fi
echo "OK"

echo
echo "✓ All smoke tests passed"
```

- [ ] **Step 2: Make script executable**

Run: `chmod +x ~/code/ssi-scanner/tests/smoke.sh`

- [ ] **Step 3: Run smoke test**

Run: `cd ~/code/ssi-scanner && ./tests/smoke.sh`
Expected: All 3 checks print OK, final line `✓ All smoke tests passed`.

**If test 2 fails with "branch is null":** The cached scan for obststadt.at doesn't have `analyze:true`. Fix by triggering a fresh scan:
```bash
curl -X POST "https://scanner.ssi.at/api/v1/scan" \
  -H "Content-Type: application/json" \
  -d '{"domain":"obststadt.at","options":{"analyze":true,"ai_provider":"gemini"}}'
```
Wait 60s, then re-run smoke test.

- [ ] **Step 4: Commit smoke test**

Run:
```bash
cd ~/code/ssi-scanner && git add tests/smoke.sh && git commit -m "test: smoke test against live API

Checks:
- /schema version field present
- /scans/obststadt.at returns pages>0, words>=100, colors>=3, branch not null
- /screenshots/desktop serves image/png"
git push
```

---

## Task 6: Scanner deploy script

**Files:**
- Create: `~/code/ssi-scanner/scripts/deploy.sh`

- [ ] **Step 1: Write deploy script**

Create `~/code/ssi-scanner/scripts/deploy.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail

# Deploy SSI Scanner to S7
# Pre-flight: git clean, VERSION bumped, on main
# Deploy:     rsync + Cloudflare purge
# Post:       smoke test

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="server7:/home/pawbot/projects/scanner/"
CF_ZONE="cdce009435d7e281ded0754b882ad5f1"

cd "$REPO_DIR"

echo "→ Pre-flight checks"

# Git clean?
if [ -n "$(git status --porcelain)" ]; then
  echo "  ✗ Git working tree not clean. Commit or stash first."
  git status -s
  exit 1
fi
echo "  ✓ Git clean"

# On main?
BRANCH=$(git branch --show-current)
if [ "$BRANCH" != "main" ]; then
  echo "  ✗ Not on main (on $BRANCH). Deploy only from main."
  exit 1
fi
echo "  ✓ On main"

# VERSION bumped compared to last tag?
VERSION=$(cat VERSION)
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "none")
echo "  VERSION=$VERSION, last tag=$LAST_TAG"

echo
echo "→ Rsync to $REMOTE"
rsync -av --delete \
  --exclude='.git/' \
  --exclude='tests/' \
  --exclude='scripts/' \
  --exclude='docs/' \
  --exclude='node_modules/' \
  --exclude='scans/' \
  --exclude='logs/' \
  --exclude='.env' \
  --exclude='*.log' \
  ./ "$REMOTE"

echo
echo "→ Cloudflare cache purge"
if [ -z "${CLOUDFLARE_GLOBAL_API_KEY:-}" ]; then
  ssh server7 "bash -c 'source ~/.env 2>/dev/null || true; curl -sS -X POST \"https://api.cloudflare.com/client/v4/zones/$CF_ZONE/purge_cache\" -H \"X-Auth-Email: office@ssi.at\" -H \"X-Auth-Key: \$CLOUDFLARE_GLOBAL_API_KEY\" -H \"Content-Type: application/json\" --data \"{\\\"purge_everything\\\":true}\"'" | jq -r '.success // "unknown"' | head -1
else
  curl -sS -X POST "https://api.cloudflare.com/client/v4/zones/$CF_ZONE/purge_cache" \
    -H "X-Auth-Email: office@ssi.at" \
    -H "X-Auth-Key: $CLOUDFLARE_GLOBAL_API_KEY" \
    -H "Content-Type: application/json" \
    --data '{"purge_everything":true}' | jq -r '.success'
fi

echo
echo "→ Wait 5s for cache propagation"
sleep 5

echo
echo "→ Post-deploy smoke test"
./tests/smoke.sh

echo
echo "✓ Deploy successful: v$VERSION"
```

- [ ] **Step 2: Make executable**

Run: `chmod +x ~/code/ssi-scanner/scripts/deploy.sh`

- [ ] **Step 3: DRY-RUN the rsync (no actual transfer)**

Run:
```bash
cd ~/code/ssi-scanner && rsync -avn --delete \
  --exclude='.git/' --exclude='tests/' --exclude='scripts/' \
  --exclude='docs/' --exclude='node_modules/' --exclude='scans/' \
  --exclude='logs/' --exclude='.env' --exclude='*.log' \
  ./ server7:/home/pawbot/projects/scanner/ | head -30
```
Expected: Shows files that would be transferred. **NO actual transfer** (the `-n` flag). Verify no `.env`, no `scans/`, no `tests/` in list.

- [ ] **Step 4: Commit deploy script**

Run:
```bash
cd ~/code/ssi-scanner && git add scripts/deploy.sh && git commit -m "ci: deploy script with pre-flight + post-deploy smoke test

Pre-flight: git clean, on main, VERSION known
Deploy:     rsync --delete to S7 (excludes tests/scripts/docs/.env/scans)
Post:       Cloudflare purge + tests/smoke.sh"
git push
```

---

## Task 7: Verify Scanner repo is complete

- [ ] **Step 1: Check final structure**

Run: `cd ~/code/ssi-scanner && git log --oneline && ls -la`
Expected: At least 3 commits (import, smoke test, deploy script), all scaffolding files present.

- [ ] **Step 2: Check remote is in sync**

Run: `cd ~/code/ssi-scanner && git fetch && git status`
Expected: `Your branch is up to date with 'origin/main'`.

- [ ] **Step 3: Run smoke test one more time for confidence**

Run: `cd ~/code/ssi-scanner && ./tests/smoke.sh`
Expected: All checks pass.

---

## Task 8: Clone Endoskeleton repo

**Files:**
- Create: `~/code/ssi-endoskeleton/` (from git clone)

- [ ] **Step 1: Clone from GitHub**

Run: `cd ~/code && git clone https://github.com/mmollay/endoskeleton ssi-endoskeleton && cd ssi-endoskeleton && git log --oneline -5`
Expected: Clone succeeds, shows recent commit history. Last commit should be from ~2026-04-06 (before local v3.31.x changes).

- [ ] **Step 2: Check current VERSION in clone**

Run: `cat ~/code/ssi-endoskeleton/VERSION`
Expected: Version BEFORE 3.31.0 (likely 3.30.x or 3.19.x). Note the value.

- [ ] **Step 3: Diff against local PawBot working copy**

Run:
```bash
diff -rq ~/code/ssi-endoskeleton ~/pawbot/core/templates/endoskeleton 2>&1 \
  | grep -v "^Only in.*\\.git" \
  | grep -v "Only in.*\\.playwright-mcp" \
  | grep -v "Only in.*demo-current.html" \
  > /tmp/endo-diff.txt
wc -l /tmp/endo-diff.txt
head -40 /tmp/endo-diff.txt
```
Expected: List of differing files. Likely candidates: `js/content-injector.js`, `konfigurator.html`, `VERSION`, `CHANGELOG.md`, possibly a few CSS files.

- [ ] **Step 4: Review diff with human eyes**

Read `/tmp/endo-diff.txt` in full. For each "differ" entry, decide: take the pawbot version (local is newer)? For each "Only in pawbot" entry, decide: add to ssi-endoskeleton?

**Default rule:** Take pawbot version for all files that appear in both (local is v3.31.2 newer). For "Only in pawbot" files, include unless they look like dev artifacts (screenshots, scratch files, `.playwright-mcp/`).

---

## Task 9: Apply v3.31.2 changes + commit + tag + push

**Files:**
- Modify: `~/code/ssi-endoskeleton/js/content-injector.js`
- Modify: `~/code/ssi-endoskeleton/VERSION`
- Modify: `~/code/ssi-endoskeleton/CHANGELOG.md`
- Modify: (all other files from Task 8 Step 3 diff)

- [ ] **Step 1: Copy changed files from pawbot working copy**

For each file listed in `/tmp/endo-diff.txt` as "differ" or "Only in pawbot" (excluding `.git`, `.playwright-mcp/`, `demo-current.html`, `node_modules/`):

Run (adjust file list based on actual diff output):
```bash
# Example — actual list comes from Task 8 Step 4 review
cp ~/pawbot/core/templates/endoskeleton/js/content-injector.js ~/code/ssi-endoskeleton/js/
cp ~/pawbot/core/templates/endoskeleton/konfigurator.html ~/code/ssi-endoskeleton/
cp ~/pawbot/core/templates/endoskeleton/VERSION ~/code/ssi-endoskeleton/
cp ~/pawbot/core/templates/endoskeleton/CHANGELOG.md ~/code/ssi-endoskeleton/
# ... etc
```

- [ ] **Step 2: Verify VERSION is now 3.31.2**

Run: `cat ~/code/ssi-endoskeleton/VERSION`
Expected: `3.31.2`

- [ ] **Step 3: Verify CHANGELOG has v3.31.0/3.31.1/3.31.2 entries**

Run: `grep -E "^## \[3\.31\." ~/code/ssi-endoskeleton/CHANGELOG.md`
Expected: Three lines: `## [3.31.0]`, `## [3.31.1]`, `## [3.31.2]`.

- [ ] **Step 4: Git status to see what changed**

Run: `cd ~/code/ssi-endoskeleton && git status -s`
Expected: Modified + new files from Task 8 diff.

- [ ] **Step 5: Stage and commit as single v3.31.2 release**

Run:
```bash
cd ~/code/ssi-endoskeleton && git add -A && git commit -m "release: v3.31.2 — konfigurator content-injection rewrite

Rolled up three point releases from local pawbot working copy:

v3.31.0:
- content-injector: _setTextAndLock replaces _setAllText so
  applyLang() no longer overwrites injected scan texts
- Home-dedup in navigation (/ vs /index.html with same title)
- Hero image in all variants: split uses img[src], fullscreen/
  banner/veil use background-image; srcset removed so browser
  does not fall back to original src
- Fallback titles 'Über uns'/'Unsere Leistungen' when scan has
  no dedicated about/services page

v3.31.1:
- Nav-labels, about/services titles trimmed at first separator
  (– | -) to shorten long page titles like 'Leistungen – SSI |
  Webhosting, …' down to 'Leistungen'
- Nav labels hard-capped at 25 chars

v3.31.2:
- Title shortening requires whitespace around separator so
  'Smart-Kit' stays intact; regex: \\s+[–|]\\s+|\\s+-\\s+

Verified on obststadt.at, ssi.at, survivaltraining.at — preset
switch preserves injected scan data across all 22 presets.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 6: Tag v3.31.2**

Run: `cd ~/code/ssi-endoskeleton && git tag -a v3.31.2 -m "v3.31.2 — konfigurator content-injection rewrite"`

- [ ] **Step 7: Push main + tag**

Run: `cd ~/code/ssi-endoskeleton && git push origin main && git push origin v3.31.2`
Expected: Both pushes succeed.

- [ ] **Step 8: Verify GitHub is in sync**

Run: `gh repo view mmollay/endoskeleton --json pushedAt,defaultBranchRef`
Expected: `pushedAt` is current time (within last minute).

---

## Task 10: Endoskeleton smoke test

**Files:**
- Create: `~/code/ssi-endoskeleton/tests/smoke.sh`

- [ ] **Step 1: Write smoke test script**

Create `~/code/ssi-endoskeleton/tests/smoke.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail

# Smoke test for SSI Endoskeleton — hits live API + konfigurator HTML
# Exit 0 = all assertions pass, Exit 1 = any assertion fails

API="https://skeleton.ssi.at/api/v1"
KONF_URL="https://skeleton.ssi.at/konfigurator.html"
EXPECTED_VERSION=$(cat "$(dirname "$0")/../VERSION")

echo "→ Smoke test: SSI Endoskeleton"
echo "  API: $API"
echo "  Expected version: $EXPECTED_VERSION"
echo

# 1) Schema endpoint returns expected version
echo -n "1) GET /schema version matches VERSION file ... "
API_VERSION=$(curl -sS -m 10 "$API/schema" | jq -r '.info.version // "missing"')
if [ "$API_VERSION" != "$EXPECTED_VERSION" ]; then
  echo "FAIL (API=$API_VERSION, file=$EXPECTED_VERSION)"
  exit 1
fi
echo "OK (v$API_VERSION)"

# 2) Konfigurator HTML references content-injector with current version
echo -n "2) konfigurator.html contains content-injector.js?v=$EXPECTED_VERSION ... "
if ! curl -sS -m 10 "$KONF_URL" | grep -q "content-injector.js?v=$EXPECTED_VERSION"; then
  echo "FAIL (expected version string not found in HTML)"
  exit 1
fi
echo "OK"

# 3) Preset list has at least 22 entries
echo -n "3) GET /presets has >=22 entries ... "
PRESET_COUNT=$(curl -sS -m 10 "$API/presets" | jq '. | length // (.presets | length)')
if [ "$PRESET_COUNT" -lt 22 ]; then
  echo "FAIL (count=$PRESET_COUNT, expected >=22)"
  exit 1
fi
echo "OK ($PRESET_COUNT presets)"

# 4) Recommend API responds with correct shape
echo -n "4) POST /recommend returns recommended.* ... "
RECO=$(curl -sS -m 10 -X POST "$API/recommend" \
  -H "Content-Type: application/json" \
  -d '{"branch":"tech","mood":"modern","colors":["#147070"]}')
PRESET=$(echo "$RECO" | jq -r '.recommended.preset // .recommended.name // "missing"')
if [ "$PRESET" = "missing" ]; then
  echo "FAIL (no recommended.preset in response)"
  echo "$RECO" | head -5
  exit 1
fi
echo "OK (recommended: $PRESET)"

echo
echo "✓ All smoke tests passed"
```

- [ ] **Step 2: Make executable and run**

Run: `chmod +x ~/code/ssi-endoskeleton/tests/smoke.sh && cd ~/code/ssi-endoskeleton && ./tests/smoke.sh`
Expected: All 4 checks pass.

**If test 2 fails:** Live skeleton.ssi.at still has an older version. Either (a) wait and run after Task 11's deploy, or (b) check Cloudflare cache and purge manually.

- [ ] **Step 3: Commit smoke test**

Run:
```bash
cd ~/code/ssi-endoskeleton && git add tests/smoke.sh && git commit -m "test: smoke test against live API + konfigurator

Checks:
- /schema version matches local VERSION file
- konfigurator.html references content-injector.js?v=<version>
- /presets returns >=22 entries
- /recommend returns recommended.preset shape"
git push
```

---

## Task 11: Endoskeleton deploy script

**Files:**
- Create: `~/code/ssi-endoskeleton/scripts/deploy.sh`

- [ ] **Step 1: Write deploy script (same pattern as Task 6)**

Create `~/code/ssi-endoskeleton/scripts/deploy.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail

# Deploy SSI Endoskeleton to S7
REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="server7:/home/pawbot/projects/endoskeleton/"
CF_ZONE="cdce009435d7e281ded0754b882ad5f1"

cd "$REPO_DIR"

echo "→ Pre-flight checks"

if [ -n "$(git status --porcelain)" ]; then
  echo "  ✗ Git working tree not clean."
  git status -s
  exit 1
fi
echo "  ✓ Git clean"

BRANCH=$(git branch --show-current)
if [ "$BRANCH" != "main" ]; then
  echo "  ✗ Not on main (on $BRANCH)."
  exit 1
fi
echo "  ✓ On main"

VERSION=$(cat VERSION)
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "none")
echo "  VERSION=$VERSION, last tag=$LAST_TAG"

echo
echo "→ Rsync to $REMOTE"
rsync -av --delete \
  --exclude='.git/' \
  --exclude='tests/' \
  --exclude='scripts/' \
  --exclude='docs/' \
  --exclude='node_modules/' \
  --exclude='.playwright-mcp/' \
  --exclude='demo-current.html' \
  --exclude='.env' \
  --exclude='*.log' \
  ./ "$REMOTE"

echo
echo "→ Cloudflare cache purge"
ssh server7 "bash -c 'source ~/.env 2>/dev/null || true; curl -sS -X POST \"https://api.cloudflare.com/client/v4/zones/$CF_ZONE/purge_cache\" -H \"X-Auth-Email: office@ssi.at\" -H \"X-Auth-Key: \$CLOUDFLARE_GLOBAL_API_KEY\" -H \"Content-Type: application/json\" --data \"{\\\"purge_everything\\\":true}\"'" | jq -r '.success // "unknown"' | head -1

echo
echo "→ Wait 5s for cache propagation"
sleep 5

echo
echo "→ Post-deploy smoke test"
./tests/smoke.sh

echo
echo "✓ Deploy successful: v$VERSION"
```

- [ ] **Step 2: Make executable**

Run: `chmod +x ~/code/ssi-endoskeleton/scripts/deploy.sh`

- [ ] **Step 3: DRY-RUN the rsync**

Run:
```bash
cd ~/code/ssi-endoskeleton && rsync -avn --delete \
  --exclude='.git/' --exclude='tests/' --exclude='scripts/' \
  --exclude='docs/' --exclude='node_modules/' \
  --exclude='.playwright-mcp/' --exclude='demo-current.html' \
  --exclude='.env' --exclude='*.log' \
  ./ server7:/home/pawbot/projects/endoskeleton/ | head -40
```
Expected: Lists files to transfer. Verify no `.git/`, no `tests/`, no `.playwright-mcp/`.

- [ ] **Step 4: Commit deploy script**

Run:
```bash
cd ~/code/ssi-endoskeleton && git add scripts/deploy.sh && git commit -m "ci: deploy script with pre-flight + post-deploy smoke test

Pre-flight: git clean, on main, VERSION known
Deploy:     rsync --delete to S7 (excludes tests/scripts/docs/.env)
Post:       Cloudflare purge + tests/smoke.sh"
git push
```

---

## Task 12: Move spec + plan into ssi-endoskeleton repo

**Files:**
- Move: `~/code/docs/superpowers/specs/2026-04-11-web-generator-foundation-design.md` → `~/code/ssi-endoskeleton/docs/superpowers/specs/`
- Move: `~/code/docs/superpowers/plans/2026-04-11-web-generator-foundation.md` → `~/code/ssi-endoskeleton/docs/superpowers/plans/`

- [ ] **Step 1: Create target directories**

Run: `mkdir -p ~/code/ssi-endoskeleton/docs/superpowers/{specs,plans}`

- [ ] **Step 2: Move spec and plan**

Run:
```bash
mv ~/code/docs/superpowers/specs/2026-04-11-web-generator-foundation-design.md \
   ~/code/ssi-endoskeleton/docs/superpowers/specs/
mv ~/code/docs/superpowers/plans/2026-04-11-web-generator-foundation.md \
   ~/code/ssi-endoskeleton/docs/superpowers/plans/
```

- [ ] **Step 3: Verify source directory is empty, clean up**

Run: `find ~/code/docs -type f && rmdir ~/code/docs/superpowers/{specs,plans,} ~/code/docs 2>/dev/null; ls ~/code/`
Expected: No files left under `~/code/docs`, directory removed. `~/code/` now contains only `ssi-scanner/` and `ssi-endoskeleton/`.

- [ ] **Step 4: Commit**

Run:
```bash
cd ~/code/ssi-endoskeleton && git add docs/ && git commit -m "docs: foundation spec + implementation plan

Spec: 2026-04-11-web-generator-foundation-design.md
Plan: 2026-04-11-web-generator-foundation.md

Documents the migration of scanner + endoskeleton into
their own git repos under ~/code/, separated from pawbot."
git push
```

---

## Task 13: Rotate leaked GitHub token

**Files:**
- No local file changes; GitHub settings update only.

- [ ] **Step 1: Find where the leaked token is referenced**

Run: `grep -rE "ghp_[A-Za-z0-9]{36,}" ~/pawbot/ ~/.gitconfig ~/.config/git/config 2>/dev/null | head -5`
Expected: Locations where the old PAT `ghp_<REDACTED>` is still embedded. Likely in `~/pawbot/core/templates/endoskeleton/` or similar.

- [ ] **Step 2: Revoke the old token on GitHub**

Open in browser: https://github.com/settings/tokens
Find the token starting with `ghp_BDs7u` (compare the full value with the grep output from Step 1). Click "Delete".
*(Claude cannot do this programmatically — requires human action.)*

- [ ] **Step 3: Verify gh CLI still works (uses a different, OAuth-keyring token)**

Run: `gh auth status && gh repo view mmollay/ssi-scanner --json name`
Expected: Both commands succeed. Current `gh` auth is via `gho_` keyring token, unaffected by PAT deletion.

- [ ] **Step 4: Clean token references from old config**

For each location found in Step 1, edit the file and replace the embedded token URL with a clean URL:
```
BEFORE: https://ghp_BDs7...@github.com/mmollay/pawbot.git
AFTER:  https://github.com/mmollay/pawbot.git
```
Specifically check `~/pawbot/.git/config` if it exists, or any `.gitconfig` files in the pawbot tree. **Do not modify `~/pawbot/` working files — only git config.**

- [ ] **Step 5: Verify no PAT remains anywhere**

Run: `grep -rE "ghp_[A-Za-z0-9]{36,}" ~/pawbot/ ~/code/ ~/.gitconfig 2>/dev/null | head -5`
Expected: No matches.

---

## Task 14: Final verification

- [ ] **Step 1: Both repos have clean git state**

Run:
```bash
for d in ~/code/ssi-scanner ~/code/ssi-endoskeleton; do
  echo "=== $d ==="
  cd "$d"
  git status -s
  git log --oneline -5
  git tag --list
  echo
done
```
Expected: Both repos clean, both have initial + scaffolding commits, both have their version tag.

- [ ] **Step 2: Both smoke tests pass**

Run: `~/code/ssi-scanner/tests/smoke.sh && ~/code/ssi-endoskeleton/tests/smoke.sh`
Expected: Both print `✓ All smoke tests passed`.

- [ ] **Step 3: PawBot unverändert**

Run: `ls -la ~/pawbot/bot/commands/endo.py ~/pawbot/core/templates/endoskeleton/VERSION`
Expected: Files exist, mtime unchanged since session start.

- [ ] **Step 4: Success summary**

Print:
```
✓ ssi-scanner v1.3.0 — ~/code/ssi-scanner/ → github.com/mmollay/ssi-scanner
✓ ssi-endoskeleton v3.31.2 — ~/code/ssi-endoskeleton/ → github.com/mmollay/endoskeleton
✓ Both smoke tests green
✓ Both deploy scripts ready (dry-run tested)
✓ Leaked PAT revoked
✓ PawBot unchanged
```

---

## Success Criteria (from spec)

- [x] Task 4: `~/code/ssi-scanner/` is a Git repo, pushed to GitHub, tagged v1.3.0
- [x] Task 9: `~/code/ssi-endoskeleton/` updated to v3.31.2, pushed, tagged
- [x] Task 6, 11: Both repos have `scripts/deploy.sh` with pre-flight checks
- [x] Task 5, 10: Both repos have `tests/smoke.sh` that passes
- [x] Task 13: Leaked PAT revoked
- [x] Task 14 Step 3: PawBot unchanged
- [x] Task 12: Spec + plan committed under `~/code/ssi-endoskeleton/docs/superpowers/`
