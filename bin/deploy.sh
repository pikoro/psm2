#!/usr/bin/env bash
# PHPSwapMeet 2 — Full Deployment Script
# Usage: bash bin/deploy.sh
#
# This script:
#   1. Copies all project files to /var/www/PSM2/
#   2. Runs composer install
#   3. Runs the database migration for new columns
#   4. Sets proper file permissions
#   5. Provides Apache configuration guidance

set -euo pipefail

SOURCE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DEST_DIR="/var/www/PSM2"

echo "========================================="
echo " PHPSwapMeet 2 — Deployment"
echo "========================================="
echo "Source: $SOURCE_DIR"
echo "Target: $DEST_DIR"
echo ""

# Step 1: Copy files
echo "[1/4] Copying files..."
rsync -av --delete \
    --exclude='vendor/' \
    --exclude='.env' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    "$SOURCE_DIR/" "$DEST_DIR/"
echo "      Files copied."

# Step 2: Run composer install
echo "[2/4] Running composer install..."
cd "$DEST_DIR"
if [ ! -d "$DEST_DIR/vendor" ]; then
    composer install --no-interaction --optimize-autoloader
    echo "      Composer install complete."
else
    echo "      Vendor directory exists, updating..."
    composer update --no-interaction --optimize-autoloader
    echo "      Composer update complete."
fi

# Step 3: Run database migration
echo "[3/4] Running database migration..."
if command -v mysql &> /dev/null; then
    mysql -u root -p'oversight' phpswapmeet < "$DEST_DIR/migrations/001_add_language_and_name_fields.sql" 2>/dev/null && \
        echo "      Migration applied." || \
        echo "      Migration failed — run manually:"
    echo "      mysql -u root -p phpswapmeet < $DEST_DIR/migrations/001_add_language_and_name_fields.sql"
else
    echo "      MySQL client not found. Run migration manually:"
    echo "      mysql -u root -p phpswapmeet < $DEST_DIR/migrations/001_add_language_and_name_fields.sql"
fi

# Step 4: Set permissions
echo "[4/4] Setting permissions..."
chmod -R 755 "$DEST_DIR"
chmod -R 775 "$DEST_DIR/uploads"
chmod -R 775 "$DEST_DIR/sessions"
chown -R www-data:www-data "$DEST_DIR/uploads" 2>/dev/null || true
chown -R www-data:www-data "$DEST_DIR/sessions" 2>/dev/null || true
echo "      Permissions set."

echo ""
echo "========================================="
echo " Deployment complete!"
echo "========================================="
echo ""
echo "Next steps:"
echo "  1. Ensure .env file exists at $DEST_DIR/.env"
echo "  2. Ensure APP_DEBUG is set appropriately"
echo "  3. Verify Apache config:"
echo "     - AllowOverride All for $DEST_DIR/public/"
echo "     - mod_rewrite, mod_headers, mod_authz_core enabled"
echo "  4. Visit http://localhost/ in your browser"
echo ""
