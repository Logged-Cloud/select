#!/usr/bin/env bash
#
# Regenerate the README screenshots for logged-cloud/select.
#
# Driven by snake.logged.cloud's Dusk harness — it ships a sandbox renderer
# at /lab/select/{variant} that the test visits to capture one PNG per
# variant. The PNGs land in tests/Browser/screenshots/select/ inside the
# snake-logged source tree, then this script copies them into docs/images/
# here.
#
#   bin/screenshots.sh           # full run: dusk capture + copy
#   bin/screenshots.sh --skip    # reuse the existing dusk output, just copy
#
# Requires:
#   - snake-logged-dusk container running (docker compose up -d in
#     /var/www/snake-logged/)
#   - snake-logged-app container has the latest package files in vendor/
#     (composer update logged-cloud/select OR copy resources/ + config/ in
#     for an unpushed local build)
#
# Run this from the host, not inside any container.

set -euo pipefail

SNAKE_SRC=${SNAKE_SRC:-/var/www/snake-logged/src}
PKG_DIR=${PKG_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
SHOTS_SRC="$SNAKE_SRC/tests/Browser/screenshots/select"
SHOTS_DST="$PKG_DIR/docs/images"

mkdir -p "$SHOTS_DST"

if [[ "${1:-}" != "--skip" ]]; then
    echo "→ Syncing local package files into snake-logged's vendor (for unpushed work)..."
    cp -r "$PKG_DIR/resources/"* "$SNAKE_SRC/vendor/logged-cloud/select/resources/"
    cp -r "$PKG_DIR/config/"*    "$SNAKE_SRC/vendor/logged-cloud/select/config/"

    echo "→ Clearing the Blade view cache..."
    docker exec snake-logged-app php artisan view:clear >/dev/null

    echo "→ Running Dusk capture..."
    docker exec snake-logged-dusk php artisan dusk --filter=SelectVariantScreenshotsTest
fi

echo "→ Copying PNGs into $SHOTS_DST"
for png in "$SHOTS_SRC"/*.png; do
    base=$(basename "$png")
    cp "$png" "$SHOTS_DST/$base"
    echo "  ✓ $base"
done

echo
echo "Done. Review the new images then:"
echo "  cd $PKG_DIR && git add docs/images/ && git commit + tag"
