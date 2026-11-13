#!/bin/sh
# Crea module_webcams-<versione>.zip, da caricare in Dolibarr da
# Impostazioni -> Moduli -> Distribuisci/installa un modulo esterno.
set -e
cd "$(dirname "$0")"
V="$(cat VERSION)"
OUT="$PWD/module_webcams-$V.zip"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir "$TMP/webcams"
git archive HEAD | tar -x -C "$TMP/webcams"
rm -rf "$TMP/webcams/.github" "$TMP/webcams/build.sh" "$TMP/webcams/.gitignore"
rm -f "$OUT"
(cd "$TMP" && zip -qr "$OUT" webcams)
echo "$OUT"
