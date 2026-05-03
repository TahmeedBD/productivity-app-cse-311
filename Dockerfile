FROM php:8.5-apache

# Install system packages needed by PHP extensions used in tests
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev libxml2-dev pkg-config \
	&& rm -rf /var/lib/apt/lists/*

# Install PDO, MySQL, SQLite, and common testing extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql pdo_sqlite mbstring xml

# Enable Apache rewrite module
RUN a2enmod rewrite

# Change Apache to listen on 8080
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf
EXPOSE 8080

# Update default Apache configuration to allow overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
