#!/usr/bin/env bash
# Run from this project. Creates local archives only; never sends or imports data.
set -euo pipefail
cd "$(dirname "$0")/.."
output=".handoff/transfer-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$output"
ddev drush php:script scripts/handoff-inventory.php > "$output/inventory.json"
ddev export-db --file="$output/site.sql.gz"
# Archive originals, including files referenced by URL without a file entity.
# Exclude regenerable caches and the obsolete public config export.
tar -czf "$output/public-files.tar.gz" \
  --exclude='./css' --exclude='./js' --exclude='./php' \
  --exclude='./styles' --exclude='./sync' \
  -C web/sites/default/files .
(
  cd "$output"
  sha256sum site.sql.gz public-files.tar.gz inventory.json > SHA256SUMS
)
printf 'Local transfer bundle: %s\n' "$output"
printf 'Review inventory.json for private files. Transfer securely; do not commit archives.\n'
