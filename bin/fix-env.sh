#!/bin/bash
# Fix PSM2 environment variable mismatch
# Run this script to apply the fix

echo "Fixing PSM2 environment variable mismatch..."

# Fix Database.php to use correct env variable names
sed -i "s/\$_ENV\['DB_DATABASE'\]/\$_ENV['DB_NAME']/g" /var/www/PSM2/src/Database.php
sed -i "s/\$_ENV\['DB_USERNAME'\]/\$_ENV['DB_USER']/g" /var/www/PSM2/src/Database.php

echo "Fix applied. Restart Apache with: sudo systemctl restart apache2"
