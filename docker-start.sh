#!/bin/bash
set -e

echo "🚀 Starting Zaf's Kitchen Application..."

# Check if DATABASE_URL is set
if [ -z "$DATABASE_URL" ]; then
    echo "❌ ERROR: DATABASE_URL environment variable not set"
    exit 1
fi

echo "✅ DATABASE_URL is configured"

# Check environment variables
echo "Checking environment variables..."
echo "SENDGRID_API_KEY: $([ -n "$SENDGRID_API_KEY" ] && echo "Set" || echo "Missing")"
echo "SENDER_EMAIL: $([ -n "$SENDER_EMAIL" ] && echo "Set" || echo "Missing")"
echo "SENDER_NAME: $([ -n "$SENDER_NAME" ] && echo "Set" || echo "Missing")"

# Check PostgreSQL PDO driver
php -m | grep -i pdo_pgsql > /dev/null
if [ $? -eq 0 ]; then
    echo "✅ PostgreSQL PDO driver loaded"
else
    echo "❌ PostgreSQL PDO driver NOT found"
    exit 1
fi

# Verify vendor directory
if [ ! -d "vendor" ]; then
    echo "❌ Vendor directory not found. Running composer install..."
    composer install --no-dev --optimize-autoloader
fi

# Test PHP files
echo "Testing PHP files..."
php -l connection.php || echo "❌ connection.php has syntax errors"
php -l sendmail.php || echo "❌ sendmail.php has syntax errors"
php -l controllerUserData.php || echo "❌ controllerUserData.php has syntax errors"

# Set correct permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Enable FULL error display for debugging
echo "✅ Enabling full error logging..."
echo "display_errors = On" > /usr/local/etc/php/conf.d/error-logging.ini
echo "display_startup_errors = On" >> /usr/local/etc/php/conf.d/error-logging.ini
echo "log_errors = On" >> /usr/local/etc/php/conf.d/error-logging.ini
echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/error-logging.ini

echo "✅ Application ready"
echo "🌐 Starting Apache on port ${PORT:-8080}"

# Start Apache in foreground
exec apache2-foreground