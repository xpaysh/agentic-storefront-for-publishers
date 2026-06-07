#!/usr/bin/env bash
# Package the plugin into a clean WP.org-uploadable ZIP.
set -euo pipefail

SLUG="agentic-storefront-for-publishers"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${ROOT}/build"
STAGE="${OUT}/${SLUG}"

rm -rf "${OUT}"
mkdir -p "${STAGE}"

rsync -a --delete \
	--exclude '.git/' \
	--exclude '.gitignore' \
	--exclude 'node_modules/' \
	--exclude 'vendor/' \
	--exclude 'build/' \
	--exclude 'scripts/' \
	--exclude 'tests/' \
	--exclude 'phpcs.xml.dist' \
	--exclude '*.zip' \
	--exclude '.phpcs-cache' \
	--exclude 'INSTAWP-TEST.md' \
	--exclude 'README.md' \
	"${ROOT}/" "${STAGE}/"

( cd "${OUT}" && zip -rq "${SLUG}.zip" "${SLUG}" )
echo "Built: ${OUT}/${SLUG}.zip"
