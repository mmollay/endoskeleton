#!/usr/bin/env bash
set -euo pipefail

# Deploy SSI Endoskeleton to S7
# Pre-flight: git clean, on main, VERSION known
# Deploy:     rsync --delete + Cloudflare purge
# Post:       tests/smoke.sh

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
  --exclude='.claude/' \
  --exclude='.env' \
  --exclude='*.log' \
  --exclude='/after-fix*.png' \
  --exclude='/nav-check.png' \
  --exclude='/preset-tech.png' \
  --exclude='/before-*.png' \
  --exclude='api/data/generated/' \
  --exclude='api/data/rate-limits/' \
  ./ "$REMOTE"

echo
echo "→ Cloudflare cache purge"
ssh server7 "bash -c 'source ~/.env 2>/dev/null || true; curl -sS -X POST \"https://api.cloudflare.com/client/v4/zones/$CF_ZONE/purge_cache\" -H \"X-Auth-Email: office@ssi.at\" -H \"X-Auth-Key: \$CLOUDFLARE_GLOBAL_API_KEY\" -H \"Content-Type: application/json\" --data \"{\\\"purge_everything\\\":true}\"'" | jq -r '.success // "unknown"' | head -1

echo
echo "→ Wait 5s for cache propagation"
sleep 5

echo
echo "→ Post-deploy smoke test"
"$REPO_DIR/tests/smoke.sh"

echo
echo "✓ Deploy successful: v$VERSION"
