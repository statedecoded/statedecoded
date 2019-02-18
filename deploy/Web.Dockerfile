FROM php:5.6.39-apache
RUN apt-get install -y libcurl4
RUN docker-php-ext-install pdo_mysql && docker-php-ext-install curl &&docker-php-ext-install mysql && a2enmod rewrite && a2enmod expires && a2enmod headers && a2enmod env

# Install our packages
RUN apt --fix-broken install
RUN apt-get update
RUN apt-get install -y git zip

# Copy over the deploy scripts
WORKDIR /var/www/
COPY . deploy/

EXPOSE 80

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
