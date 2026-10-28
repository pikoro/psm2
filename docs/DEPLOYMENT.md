# PHPSwapMeet 2 — Deployment Guide

## Quick Deploy

```bash
# From the project root
bash bin/deploy.sh
```

This script will:
1. Copy all files to `/var/www/PSM2/`
2. Run `composer install`
3. Run the database migration
4. Set proper file permissions

## Manual Deploy Steps

### 1. Copy Files

```bash
rsync -av --exclude='vendor/' --exclude='.env' \
    /home/anderson/Projects/PSM2/ /var/www/PSM2/
```

### 2. Install Dependencies

```bash
cd /var/www/PSM2
composer install --no-dev --optimize-autoloader
```

### 3. Run Database Migration

The migration adds `first_name`, `last_name`, and `language` columns to the `users` table:

```bash
mysql -u root -p phpswapmeet < /var/www/PSM2/migrations/001_add_language_and_name_fields.sql
```

### 4. Configure .env

Ensure `/var/www/PSM2/.env` exists with:

```
APP_NAME="PHPSwapMeet 2"
APP_DEBUG=false
APP_THEME=ebay

DB_HOST=localhost
DB_PORT=3306
DB_NAME=phpswapmeet
DB_USER=root
DB_PASSWORD=your_password

SESSION_SECURE=false
SESSION_LIFETIME=120

SEED_PASSWORD=password123
```

### 5. Set Permissions

```bash
chown -R www-data:www-data /var/www/PSM2/uploads
chown -R www-data:www-data /var/www/PSM2/sessions
chmod -R 755 /var/www/PSM2
chmod -R 775 /var/www/PSM2/uploads
chmod -R 775 /var/www/PSM2/sessions
```

### 6. Apache Configuration

Enable required modules:
```bash
sudo a2enmod rewrite headers authz_core
```

Add virtual host config (see `docs/apache-vhost.conf`):
```bash
sudo cp docs/apache-vhost.conf /etc/apache2/sites-available/psm2.conf
sudo a2ensite psm2.conf
sudo systemctl reload apache2
```

**Key Apache requirements:**
- `AllowOverride All` for `/var/www/PSM2/public/` — without this, `.htaccess` routing won't work and you'll get 404s
- `mod_rewrite` enabled — required for front controller routing
- `mod_headers` enabled — required for security headers
- `mod_authz_core` enabled — required for `Require all granted/denied` directives in `.htaccess`

## Troubleshooting

### 403 Forbidden
- Check Apache error log: `sudo tail -30 /var/log/apache2/error.log`
- Ensure `AllowOverride All` is set for the public directory
- Ensure `mod_authz_core` is enabled

### 404 on all routes
- Check that `AllowOverride All` is set in Apache config
- Ensure `.htaccess` exists in `/var/www/PSM2/public/`
- Check Apache error log for rewrite errors

### 500 on profile page
- Run the database migration: `mysql -u root -p phpswapmeet < /var/www/PSM2/migrations/001_add_language_and_name_fields.sql`
- Check Apache error log: `sudo tail -30 /var/log/apache2/error.log`

### Language selector not working
- The cookie key was fixed from `'expire'` to `'expires'`
- Clear your browser cookies for the site
- Verify `APP_DEBUG=true` in `.env` to see any PHP errors

## Default Credentials

After running `bin/seed.php`:
- **Admin**: `admin` / `password123`
- **User**: `johndoe` / `password123`

## Themes

Available themes:
- `ebay` — eBay-inspired layout
- `yahoo_auctions` — Yahoo Auctions-inspired layout

Switch themes via `/theme/switch?theme=ebay` or `/theme/switch?theme=yahoo_auctions`
