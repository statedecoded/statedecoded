FROM php:8.3-apache

# System deps needed before PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        libxslt-dev \
        libpng-dev \
        libjpeg-dev \
        libtidy-dev \
        libmemcached-dev \
        zlib1g-dev \
        default-mysql-client \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# xml, dom, simplexml, and mbstring are already compiled into the base image.
# gd requires JPEG support declared via configure before the install step.
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        xsl \
        zip \
        gd \
        tidy \
        opcache

# memcached is a PECL extension — not in the PHP source tree
RUN pecl install memcached \
    && docker-php-ext-enable memcached

# Apache modules
RUN a2enmod env rewrite headers

# PHP runtime config
COPY docker/app/php.ini /usr/local/etc/php/conf.d/statedecoded.ini

# Apache vhost (disables the default site, enables ours)
COPY docker/app/apache-vhost.conf /etc/apache2/sites-available/statedecoded.conf
RUN a2dissite 000-default && a2ensite statedecoded

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy only composer files first so this layer is cached independently of source changes.
# Build context is the repo root (set in docker-compose.yml), so paths are relative to root.
COPY composer.json composer.lock ./

RUN composer install --no-interaction --no-scripts --optimize-autoloader

COPY . .

COPY docker/app/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
