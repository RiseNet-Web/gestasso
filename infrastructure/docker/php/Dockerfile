FROM php:8.3-fpm

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmagickwand-dev \
    libssl-dev \
    zip \
    unzip \
    supervisor \
    cron \
    netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# Installation des extensions PHP pour Symfony
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        sockets

# Installation des extensions PECL
RUN pecl install redis imagick xdebug \
    && docker-php-ext-enable redis imagick xdebug

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installation de Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get update \
    && apt-get install -y symfony-cli

# Configuration du répertoire de travail
WORKDIR /var/www/symfony

# Copie des fichiers de configuration
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Création de l'utilisateur www-data
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Création des répertoires nécessaires
RUN mkdir -p /var/www/symfony/var/log \
    && mkdir -p /var/www/symfony/var/cache \
    && mkdir -p /var/www/symfony/public/uploads \
    && mkdir -p /var/www/symfony/config/jwt

# Script de démarrage
COPY docker/php/start-simple.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Permissions
RUN chown -R www-data:www-data /var/www/symfony

USER www-data

EXPOSE 9000

CMD ["/usr/local/bin/start.sh"] 