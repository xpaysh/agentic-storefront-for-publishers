#!/usr/bin/env bash
# Publish the current build/agentic-storefront-for-publishers.zip to the
# install.xpay.sh distribution mirror with the right Content-Disposition
# headers so browsers save the file as
#   agentic-storefront-for-publishers-<version>.zip
# regardless of whether the URL is /<slug>-<version>.zip or /latest.zip.
#
# Why: when an admin downloads via the WP admin "Plugins → Add New →
# Upload" flow from a generic /latest.zip URL, the saved filename was
# previously just "latest.zip" which is unhelpful when they have ten
# downloads stacked in their Downloads folder. Set
# Content-Disposition: attachment; filename="..." so the saved name is
# always the canonical versioned name.
#
# Run after scripts/build-zip.sh. Requires the AWS CLI configured with
# the `agentically` profile.
#
# Usage:
#   bash scripts/release-to-install-xpay.sh
#
# Optional env overrides:
#   PROFILE=agentically               (AWS profile)
#   BUCKET=xpay-install               (S3 bucket)
#   DIST=E17RH4LQHPUH1Q               (CloudFront distribution id for install.xpay.sh)
#   PREFIX=wordpress-publishers       (S3 prefix)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="xpay-agentic-commerce-for-publishers"
ZIP="${ROOT}/build/${SLUG}.zip"

PROFILE="${PROFILE:-agentically}"
BUCKET="${BUCKET:-xpay-install}"
DIST="${DIST:-E17RH4LQHPUH1Q}"
PREFIX="${PREFIX:-wordpress-publishers}"

if [ ! -f "${ZIP}" ]; then
	echo "❌ Build artifact missing: ${ZIP}"
	echo "   Run scripts/build-zip.sh first."
	exit 1
fi

# Pull the version from the plugin header so the release manifest +
# Content-Disposition filename stay in lockstep with what's in the ZIP.
VERSION=$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "${ROOT}/${SLUG}.php" | head -1 | sed -E 's/.*Version:[[:space:]]+//; s/[[:space:]]*$//')
if [ -z "${VERSION}" ]; then
	echo "❌ Could not parse Version: from ${SLUG}.php"
	exit 1
fi

DOWNLOAD_NAME="${SLUG}-${VERSION}.zip"
S3_VERSIONED="s3://${BUCKET}/${PREFIX}/${DOWNLOAD_NAME}"
S3_LATEST="s3://${BUCKET}/${PREFIX}/latest.zip"

echo "▸ Uploading versioned ZIP → ${S3_VERSIONED}"
aws s3 cp "${ZIP}" "${S3_VERSIONED}" \
	--content-type 'application/zip' \
	--content-disposition "attachment; filename=\"${DOWNLOAD_NAME}\"" \
	--metadata-directive REPLACE \
	--profile "${PROFILE}" \
	>/dev/null
echo "  ✓"

echo "▸ Uploading latest.zip → ${S3_LATEST} (Content-Disposition → ${DOWNLOAD_NAME})"
aws s3 cp "${ZIP}" "${S3_LATEST}" \
	--content-type 'application/zip' \
	--content-disposition "attachment; filename=\"${DOWNLOAD_NAME}\"" \
	--metadata-directive REPLACE \
	--profile "${PROFILE}" \
	>/dev/null
echo "  ✓"

echo "▸ Invalidating CloudFront paths under /${PREFIX}/*"
INV=$(aws cloudfront create-invalidation \
	--distribution-id "${DIST}" \
	--paths "/${PREFIX}/latest.zip" "/${PREFIX}/${DOWNLOAD_NAME}" \
	--profile "${PROFILE}" \
	--query 'Invalidation.Id' --output text)
echo "  ✓ ${INV}"

echo ""
echo "Released v${VERSION}:"
echo "  Versioned:  https://install.xpay.sh/${PREFIX}/${DOWNLOAD_NAME}"
echo "  Latest:     https://install.xpay.sh/${PREFIX}/latest.zip"
echo "  (Both serve the same bytes; both download as ${DOWNLOAD_NAME}.)"
echo ""
echo "Next: update manifest.json + CHANGELOG.md + privacy.html if they changed,"
echo "then 'aws s3 cp …' those separately."
