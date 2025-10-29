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

# Add ServerName and dynamic port for Railway
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "Listen ${PORT}" >> /etc/apache2/ports.conf

# (optional but helps Apache find right root)
RUN sed -i 's|/var/www/html|/var/www/html|g' /etc/apache2/sites-available/000-default.conf

# No need to expose a fixed port; Railway sets it automatically
# EXPOSE 80   <-- remove this line

# Use startup script
CMD ["/usr/local/bin/docker-start.sh"]
