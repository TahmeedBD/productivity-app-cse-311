FROM php:8.5-apache

# Install PDO, MySQL, and common testing extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql mbstring xml

# Enable Apache rewrite module
RUN a2enmod rewrite

# Change Apache to listen on 8080
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf
EXPOSE 8080

# Update default Apache configuration to allow overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
