FROM php:5.6.39-apache
RUN docker-php-ext-install pdo_mysql && docker-php-ext-install mysql && docker-php-ext-install tidy && a2enmod rewrite && a2enmod expires && a2enmod headers && a2enmod env

# Install our packages
RUN apt --fix-broken install
RUN apt-get update
RUN apt-get install -y zip libxml2-utils

# Copy over the deploy scripts
WORKDIR /var/www/
COPY . deploy/

RUN deploy/docker-setup-server.sh 

EXPOSE 80

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
