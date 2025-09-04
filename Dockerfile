# Use official PHP with Apache
FROM php:8.1-apache

# Install PostgreSQL client libraries (needed for pdo_pgsql)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy your project into the Apache document root
COPY . /var/www/html/

# Expose port 80 for Render
EXPOSE 80
