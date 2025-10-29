FROM php:8.2-apache

# Install system dependencies and PostgreSQL dev libraries
RUN apt-get update && \
    apt-get install -y \
        libpq-dev \
        libzip-dev \
        zip \
        unzip \
        git \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Verify PostgreSQL extension is installed
RUN php -m | grep -i pdo_pgsql || (echo "❌ pdo_pgsql not installed!" && exit 1)

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files first
COPY . .

# Remove old vendor and lock file to start fresh
RUN rm -rf vendor composer.lock

# Install dependencies fresh (ignore lock file)
RUN composer require phpmailer/phpmailer:^7.0 --no-interaction --optimize-autoloader && \
    composer require google/apiclient:^2.15 --no-interaction --optimize-autoloader && \
    composer dump-autoload --optimize && \
    echo "✅ Composer dependencies installed" && \
    ls -la vendor/

# Verify Google Client is available
RUN php -r "require 'vendor/autoload.php'; \
    if (class_exists('Google_Client')) { \
        echo '✅ Google_Client class is available\n'; \
    } else { \
        echo '❌ Google_Client class NOT found\n'; \
        exit(1); \
    }"

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Configure Apache for Railway's PORT variable
ENV PORT=80
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf && \
    sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf

# Add directory configuration
RUN echo '<Directory /var/www/html/>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    Options Indexes FollowSymLinks' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    Require all granted' >> /etc/apache2/sites-available/000-default.conf && \
    echo '</Directory>' >> /etc/apache2/sites-available/000-default.conf

EXPOSE ${PORT}

# Start Apache
CMD ["apache2-foreground"]
