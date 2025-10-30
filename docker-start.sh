#!/bin/bash
set -e

echo "🚀 Starting Zaf's Kitchen Application..."

# Check if DATABASE_URL is set
if [ -z "$DATABASE_URL" ]; then
    echo "❌ ERROR: DATABASE_URL environment variable not set"
    exit 1
fi

echo "✅ DATABASE_URL is configured"

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

# Set correct permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "✅ Application ready"
echo "🌐 Starting Apache on port ${PORT:-8080}"

# Start Apache in foreground
exec apache2-foreground