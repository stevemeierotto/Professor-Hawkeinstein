FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install curl for health checks
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache to pass environment variables to PHP
RUN echo "PassEnv DB_NAME" >> /etc/apache2/conf-available/environment.conf && \
    echo "PassEnv DB_USER" >> /etc/apache2/conf-available/environment.conf && \
    echo "PassEnv DB_PASS" >> /etc/apache2/conf-available/environment.conf && \
    echo "PassEnv DB_HOST" >> /etc/apache2/conf-available/environment.conf && \
    a2enconf environment

#  Update DocumentRoot to /var/www/html (root is now app/)
RUN sed -i 's#/var/www/html#/var/www/html#g' /etc/apache2/sites-available/000-default.conf

# Copy app directory contents
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

CMD ["apache2-foreground"]
