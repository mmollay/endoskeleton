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
KONF_HTML=$(curl -sS -m 10 "$KONF_URL")
if ! echo "$KONF_HTML" | grep -F "content-injector.js?v=$EXPECTED_VERSION" > /dev/null; then
  echo "FAIL (expected version string not found in HTML)"
  exit 1
fi
echo "OK"

# 3) Preset list has at least 22 entries
echo -n "3) GET /presets has >=22 entries ... "
PRESETS_JSON=$(curl -sS -m 10 "$API/presets")
PRESET_COUNT=$(echo "$PRESETS_JSON" | jq '
  if type == "array" then length
  elif has("presets") then (.presets | length)
  else (keys | length) end')
if [ "$PRESET_COUNT" -lt 22 ]; then
  echo "FAIL (count=$PRESET_COUNT, expected >=22)"
  exit 1
fi
echo "OK ($PRESET_COUNT presets)"

# 4) Recommend API responds with expected shape
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
