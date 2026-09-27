#!/usr/bin/env bash
# Legt einen getaggten Stand schreibgeschützt unter endoskeleton-versionen/<X.Y.Z>/ ab (nur was ein Bau braucht:
# lp/, VERSION, base.css). Bestehende Landingpages bauen immer mit dem Stand ihrer Marke (Datei ENDOSKELETON):
#   /home/pawbot/projects/endoskeleton-versionen/<Marke>/lp/bauen.php <seite>
# Aufruf nach jedem Tag, als pawbot (nie root):   scripts/version-ablegen.sh 3.51.0
set -euo pipefail
version="${1:-}"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Aufruf: scripts/version-ablegen.sh X.Y.Z" >&2; exit 2; }
[[ "$(id -u)" != "0" ]] || { echo "Nicht als root ausführen (Git auf den Servern nur als pawbot)." >&2; exit 2; }
cd "$(dirname "$0")/.."
git rev-parse -q --verify "refs/tags/v$version" >/dev/null || { echo "Tag v$version fehlt — erst taggen." >&2; exit 2; }
ziel="${ENDO_VERSIONEN:-/home/pawbot/projects/endoskeleton-versionen}/$version"
[[ ! -e "$ziel" ]] || { echo "$ziel gibt es schon — Versionsordner werden nie überschrieben." >&2; exit 2; }
# Erst in einen Zwischenordner, dann umbenennen: scheitert etwas, bleibt nichts Halbes liegen.
zwischen="$(dirname "$ziel")/.ablegen-$version-$$"
trap 'if [[ -e "$zwischen" ]]; then chmod -R u+w "$zwischen"; find "$zwischen" -delete; fi' EXIT
mkdir -p "$zwischen"
git archive "v$version" lp VERSION base.css | tar -x -C "$zwischen"
[[ "$(cat "$zwischen/VERSION")" == "$version" ]] || { echo "VERSION im Tag passt nicht zu $version." >&2; exit 2; }
chmod -R a-w "$zwischen"
mv "$zwischen" "$ziel"
echo "abgelegt: $ziel"
