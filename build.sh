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

# Copy plugin files (excluding development files)
rsync -av --exclude-from=- . "${BUILD_DIR}/${PLUGIN_SLUG}/" << 'EOF'
.git
.git/**
.gitignore
.DS_Store
CLAUDE.md
README.md
build.sh
build
build/**
*.zip
node_modules
node_modules/**
vendor
vendor/**
.idea
.idea/**
.vscode
.vscode/**
.claude
.claude/**
Sites
Sites/**
*.swp
*.swo
*~
Thumbs.db
.env
.env.*
tests
tests/**
phpunit.xml
phpcs.xml
composer.json
composer.lock
package.json
package-lock.json
webpack.config.js
EOF

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
