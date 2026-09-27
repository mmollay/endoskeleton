#!/usr/bin/env bash
# Überträgt den Zweig lp-fabrik aus dem Klon (server6) in die Arbeitskopie auf server7
# und führt dort die PHP-Tests aus (PHP 8.3). Git läuft auf server7 als pawbot, nie als root.
set -euo pipefail
cd "$(dirname "$0")/.."
git bundle create /tmp/endo-lp.bundle lp-fabrik >/dev/null 2>&1
scp -q /tmp/endo-lp.bundle server7:/tmp/endo-lp.bundle
ssh -o BatchMode=yes server7 'chmod 644 /tmp/endo-lp.bundle && sudo -iu pawbot bash -s' <<'EOF'
set -euo pipefail
cd /home/pawbot/projects/endoskeleton-arbeit
git fetch -q /tmp/endo-lp.bundle lp-fabrik
git merge -q --ff-only FETCH_HEAD
php tests/run.php
EOF
