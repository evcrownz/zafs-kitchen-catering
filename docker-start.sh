#!/bin/bash
set -e

# Configure Apache to use Railway's PORT environment variable
PORT=${PORT:-80}

# Update ports.conf
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

# Update default site configuration - handle both *:80 and :80 patterns
sed -i "s/*:80/*:${PORT}/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/<VirtualHost :80>/<VirtualHost :${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Start Apache
exec apache2-foreground