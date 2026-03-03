#!/usr/bin/env bash
#
# Theme Build Script — alchemize_forge
#
# Full asset pipeline: installs npm dependencies, compiles SCSS to CSS,
# and copies the Bootstrap JS bundle to the theme's js/ directory.
#
# Usage:
#   From inside DDEV container:
#     bash web/themes/custom/alchemize_forge/scripts/build.sh [--dev]
#
#   Via DDEV custom command:
#     ddev theme-build [--dev]
#
#   Flags:
#     --dev     Development build (source maps, not minified)
#     --watch   Watch mode (auto-rebuild on SCSS changes)
#     --clean   Clean compiled CSS before building
#     --ci      CI mode: skip npm install if node_modules exists
#
set -euo pipefail

THEME_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
THEME_NAME="$(basename "$THEME_DIR")"

# Defaults
MODE="production"
WATCH=false
CLEAN=false
CI=false

# Parse flags
for arg in "$@"; do
  case "$arg" in
    --dev)   MODE="development" ;;
    --watch) WATCH=true ;;
    --clean) CLEAN=true ;;
    --ci)    CI=true ;;
    --help|-h)
      echo "Usage: $0 [--dev] [--watch] [--clean] [--ci]"
      echo ""
      echo "  --dev    Development build (source maps, not minified)"
      echo "  --watch  Watch mode (auto-rebuild on SCSS changes)"
      echo "  --clean  Clean compiled CSS before building"
      echo "  --ci     CI mode: skip npm install if node_modules exists"
      exit 0
      ;;
    *)
      echo "Unknown flag: $arg"
      exit 1
      ;;
  esac
done

echo "=== Theme Build: $THEME_NAME ==="
echo "Mode: $MODE"
echo "Theme dir: $THEME_DIR"
echo ""

cd "$THEME_DIR"

# Step 1: Install npm dependencies
if [ "$CI" = true ] && [ -d "node_modules" ]; then
  echo "→ Skipping npm install (CI mode, node_modules exists)"
else
  echo "→ Installing npm dependencies..."
  npm install --no-audit --no-fund 2>&1
fi
echo ""

# Step 2: Clean compiled CSS if requested
if [ "$CLEAN" = true ]; then
  echo "→ Cleaning compiled CSS..."
  npm run clean
  echo ""
fi

# Step 3: Copy Bootstrap JS bundle to js/
BS_BUNDLE="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"
if [ -f "$BS_BUNDLE" ]; then
  echo "→ Copying Bootstrap JS bundle to js/..."
  cp "$BS_BUNDLE" js/bootstrap.bundle.min.js
  # Also copy the map file if it exists
  if [ -f "${BS_BUNDLE}.map" ]; then
    cp "${BS_BUNDLE}.map" js/bootstrap.bundle.min.js.map
  fi
  BS_VERSION=$(node -e "console.log(require('./node_modules/bootstrap/package.json').version)")
  echo "  Bootstrap JS version: $BS_VERSION"
else
  echo "WARNING: Bootstrap bundle not found at $BS_BUNDLE"
fi
echo ""

# Step 4: Compile SCSS → CSS
if [ "$WATCH" = true ]; then
  echo "→ Starting watch mode (Ctrl+C to stop)..."
  npx webpack --watch --mode="$MODE"
else
  echo "→ Compiling SCSS → CSS ($MODE)..."
  npx webpack --mode="$MODE"
fi

echo ""
echo "=== Theme build complete ==="
echo ""
echo "Output files:"
ls -lh css/*.css 2>/dev/null || echo "  (no CSS files found)"
echo ""
echo "JS files:"
ls -lh js/*.js 2>/dev/null || echo "  (no JS files found)"
echo ""
echo "Next step: ddev drush cr  (clear Drupal cache)"
