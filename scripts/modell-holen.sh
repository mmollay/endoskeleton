#!/usr/bin/env bash
# Holt die Dateien der Kamera-Vorführung (Baustein „bewegung“, Variante live) und prüft jede Datei gegen scripts/modell-summen.txt.
#
#   scripts/modell-holen.sh [Zielordner]
#
# Zielordner (Standard: ../endoskeleton-modelle neben dem Kit) bekommt einen Unterordner modell/. Er darf NICHT im Kit liegen
# (auch nicht über einen Link): die Dateien (rund 38 MB) gehören nicht ins Git. Beim Veröffentlichen einer Seite mit
# Kamera-Vorführung wird dieser Ordner modell/ in den Seitenordner kopiert (docs/bewegung-format.md, „Modelldateien“).
#
# Quellen, feste Versionen — beides Apache-2.0 (Google LLC):
#   - npm-Paket @mediapipe/tasks-vision 0.10.35 (Skript und WASM der Erkennung), Integrität sha512 unten
#   - Modell pose_landmarker_lite (float16, Version 1)
# Es wird nichts installiert und kein Paketskript ausgeführt: das Paket ist nur ein Archiv, daraus werden sieben Dateien
# mit festem Namen entpackt. Alles wird zuerst in einem temporären Ordner geladen und geprüft; ins Ziel kommt modell/ erst,
# wenn jede Datei die Prüfsumme aus modell-summen.txt hat. Bei jedem Fehler bleibt das Ziel, wie es war.
set -euo pipefail

KIT="$(cd "$(dirname "$0")/.." && pwd -P)"
ZIEL="${1:-$KIT/../endoskeleton-modelle}"
PAKET_URL="https://registry.npmjs.org/@mediapipe/tasks-vision/-/tasks-vision-0.10.35.tgz"
PAKET_SHA512="HOvadwVRE6JC+45nyYhmnywnr5h/J8KZvOeUNVOG9q/0875pZgItznFB9bRTvLc264YSJqiZ1NsIpCStJw/egg=="
PAKET_MAX=41943040        # 40 MB (das Archiv hat rund 10 MB)
MODELL_URL="https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task"
MODELL_MAX=12582912       # 12 MB (die Modelldatei hat rund 5,8 MB)

fehler() { echo "$1" >&2; exit 1; }

# Zielordner prüfen, BEVOR etwas angelegt wird: weder er noch sein Unterordner modell/ darf (auch über einen Link) im Kit landen.
for pfad in "$ZIEL" "$ZIEL/modell"; do
  case "$(realpath -m -- "$pfad")/" in
    "$KIT"/*) fehler "Der Zielordner darf nicht im Kit liegen ($pfad)." ;;
  esac
done

TMP="$(mktemp -d)"
trap 'rm -r "$TMP"' EXIT

holen() {   # holen URL GRÖSSE DATEI — nur https (auch bei Weiterleitungen), mit Größengrenze
  curl -fsSL --proto '=https' --proto-redir '=https' --max-time 300 --max-filesize "$2" -o "$3" "$1"
}

echo "Paket holen …"
holen "$PAKET_URL" "$PAKET_MAX" "$TMP/paket.tgz"
[ "$(openssl dgst -sha512 -binary "$TMP/paket.tgz" | base64 -w0)" = "$PAKET_SHA512" ] || fehler "Paket: Prüfsumme stimmt nicht."

mkdir -p "$TMP/paket" "$TMP/modell/wasm"
tar -xzf "$TMP/paket.tgz" -C "$TMP/paket" \
  package/vision_bundle.mjs \
  package/wasm/vision_wasm_internal.js package/wasm/vision_wasm_internal.wasm \
  package/wasm/vision_wasm_module_internal.js package/wasm/vision_wasm_module_internal.wasm \
  package/wasm/vision_wasm_nosimd_internal.js package/wasm/vision_wasm_nosimd_internal.wasm
cp "$TMP/paket/package/vision_bundle.mjs" "$TMP/modell/vision_bundle.js"   # .js, weil nicht jeder Webserver .mjs als Skript ausliefert
cp "$TMP"/paket/package/wasm/* "$TMP/modell/wasm/"

echo "Modell holen …"
holen "$MODELL_URL" "$MODELL_MAX" "$TMP/modell/pose_landmarker_lite.task"

echo "Prüfsummen prüfen …"
(cd "$TMP/modell" && sha256sum --strict --quiet -c "$KIT/scripts/modell-summen.txt") || fehler "Eine Datei hat die falsche Prüfsumme — im Ziel wurde nichts verändert."

mkdir -p "$ZIEL"
[ ! -e "$ZIEL/modell" ] && [ ! -L "$ZIEL/modell" ] || mv "$ZIEL/modell" "$TMP/alt"   # ein früherer Stand wandert in den Papierkorb des Laufs
mv "$TMP/modell" "$ZIEL/modell" || { [ ! -e "$TMP/alt" ] || mv "$TMP/alt" "$ZIEL/modell"; fehler "Verschieben ins Ziel gescheitert — früherer Stand zurückgelegt."; }
echo "Fertig: $(cd "$ZIEL" && pwd -P)/modell"
