FROM php:8.2-apache

# Install PostgreSQL extensions for Supabase
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Copy and make the startup script executable
COPY docker-start.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-start.sh

# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Remove old "Listen" at build time (not needed)
# DO NOT set Listen ${PORT} here, it’s set at runtime in the script

# Use startup script as entrypoint
CMD ["/usr/local/bin/docker-start.sh"]
