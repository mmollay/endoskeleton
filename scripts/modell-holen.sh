#!/usr/bin/env bash
# Holt die Dateien der Kamera-Vorführung (Baustein „bewegung“, Variante live) und prüft jede Datei gegen scripts/modell-summen.txt.
#
#   scripts/modell-holen.sh [Zielordner]
#
# Zielordner (Standard: ../endoskeleton-modelle neben dem Kit) bekommt einen Unterordner modell/. Er darf NICHT im Kit liegen:
# die Dateien (rund 38 MB) gehören nicht ins Git. Beim Veröffentlichen einer Seite mit Kamera-Vorführung wird dieser Ordner modell/
# in den Seitenordner kopiert (docs/bewegung-format.md, „Modelldateien“).
#
# Quellen, feste Versionen — beides Apache-2.0 (Google LLC):
#   - npm-Paket @mediapipe/tasks-vision 0.10.35 (Skript und WASM der Erkennung), Integrität sha512 unten
#   - Modell pose_landmarker_lite (float16, Version 1)
# Es wird nichts installiert und kein Paketskript ausgeführt: das Paket ist nur ein Archiv, daraus werden sieben Dateien
# mit festem Namen entpackt. Jede Datei muss die Prüfsumme aus modell-summen.txt haben, sonst bricht das Skript ab.
set -euo pipefail

KIT="$(cd "$(dirname "$0")/.." && pwd)"
ZIEL="${1:-$KIT/../endoskeleton-modelle}"
PAKET_URL="https://registry.npmjs.org/@mediapipe/tasks-vision/-/tasks-vision-0.10.35.tgz"
PAKET_SHA512="HOvadwVRE6JC+45nyYhmnywnr5h/J8KZvOeUNVOG9q/0875pZgItznFB9bRTvLc264YSJqiZ1NsIpCStJw/egg=="
MODELL_URL="https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task"

mkdir -p "$ZIEL"
ZIEL="$(cd "$ZIEL" && pwd)"
case "$ZIEL/" in
  "$KIT"/*) echo "Der Zielordner darf nicht im Kit liegen ($ZIEL)." >&2; exit 1 ;;
esac

TMP="$(mktemp -d)"
trap 'rm -r "$TMP"' EXIT

echo "Paket holen …"
curl -fsSL --max-time 300 -o "$TMP/paket.tgz" "$PAKET_URL"
[ "$(openssl dgst -sha512 -binary "$TMP/paket.tgz" | base64 -w0)" = "$PAKET_SHA512" ] || { echo "Paket: Prüfsumme stimmt nicht." >&2; exit 1; }

mkdir -p "$ZIEL/modell/wasm"
tar -xzf "$TMP/paket.tgz" -C "$TMP" \
  package/vision_bundle.mjs \
  package/wasm/vision_wasm_internal.js package/wasm/vision_wasm_internal.wasm \
  package/wasm/vision_wasm_module_internal.js package/wasm/vision_wasm_module_internal.wasm \
  package/wasm/vision_wasm_nosimd_internal.js package/wasm/vision_wasm_nosimd_internal.wasm
cp "$TMP/package/vision_bundle.mjs" "$ZIEL/modell/vision_bundle.js"   # .js, weil nicht jeder Webserver .mjs als Skript ausliefert
cp "$TMP"/package/wasm/* "$ZIEL/modell/wasm/"

echo "Modell holen …"
curl -fsSL --max-time 300 -o "$ZIEL/modell/pose_landmarker_lite.task" "$MODELL_URL"

echo "Prüfsummen prüfen …"
(cd "$ZIEL/modell" && sha256sum --quiet -c "$KIT/scripts/modell-summen.txt") || { echo "Eine Datei hat die falsche Prüfsumme — Ordner $ZIEL/modell löschen und neu holen." >&2; exit 1; }
echo "Fertig: $ZIEL/modell"
