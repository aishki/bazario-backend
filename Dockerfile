# Use an official PHP image with Apache
FROM php:8.1-apache

# Enable PDO and PostgreSQL extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Copy your project into the Apache document root
COPY . /var/www/html/

# Expose port 80
EXPOSE 80
