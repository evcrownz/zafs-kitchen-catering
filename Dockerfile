FROM php:8.2-apache

# Install PostgreSQL extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Copy startup script
COPY docker-start.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-start.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

# Use startup script instead of direct apache2-foreground
CMD ["/usr/local/bin/docker-start.sh"]