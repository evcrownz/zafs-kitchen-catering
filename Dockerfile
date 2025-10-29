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

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install dependencies with verbose output
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader -vvv && \
    composer dump-autoload --optimize && \
    echo "✅ Composer dependencies installed" && \
    ls -la vendor/

# Copy application files
COPY . .

# Verify vendor directory exists and has Google client
RUN if [ ! -d "vendor/google/apiclient" ]; then \
        echo "❌ Google API Client not found! Reinstalling..." && \
        composer require google/apiclient --no-interaction; \
    fi

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

# Health check
RUN php -r "require 'vendor/autoload.php'; echo '✅ Autoload works\n';" || echo "❌ Autoload failed"

EXPOSE ${PORT}

# Start Apache
CMD ["apache2-foreground"]
