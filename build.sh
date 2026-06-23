#!/bin/bash

# Build script for Rational SEO WordPress plugin
# Creates a clean zip file for WordPress.org submission

set -e

# Configuration
PLUGIN_SLUG="rationalseo"
VERSION=$(grep -m1 "Version:" rationalseo.php | sed 's/.*Version: *//' | tr -d '[:space:]')
BUILD_DIR="build"
ZIP_FILE="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean up previous builds
rm -rf "${BUILD_DIR}"
rm -f "${ZIP_FILE}"

# Create build directory
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

# Copy plugin files, excluding everything listed in .distignore. This is the
# single source of truth for distribution exclusions, shared with the
# WordPress.org SVN deploy, so the local zip matches what actually ships.
# Comments and blank lines are stripped; build artifacts and VCS metadata are
# always excluded as a safety net.
EXCLUDE_FILE="$(mktemp)"
if [ -f .distignore ]; then
	grep -vE '^[[:space:]]*(#|$)' .distignore > "${EXCLUDE_FILE}"
else
	echo "Warning: .distignore not found; falling back to minimal exclusions." >&2
fi
printf '%s\n' '.svn' '.git' 'build' '*.zip' >> "${EXCLUDE_FILE}"

rsync -av --exclude-from="${EXCLUDE_FILE}" . "${BUILD_DIR}/${PLUGIN_SLUG}/"

rm -f "${EXCLUDE_FILE}"

# Create the zip file
cd "${BUILD_DIR}"
zip -r "../${ZIP_FILE}" "${PLUGIN_SLUG}"
cd ..

# Clean up build directory
rm -rf "${BUILD_DIR}"

echo ""
echo "Build complete!"
echo "Created: ${ZIP_FILE}"
echo ""
echo "File size: $(du -h "${ZIP_FILE}" | cut -f1)"
echo ""
echo "Contents:"
unzip -l "${ZIP_FILE}" | head -30
