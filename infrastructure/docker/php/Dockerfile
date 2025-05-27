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
    sudo \
    gosu \
    nano \
    vim \
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
    && docker-php-ext-enable redis imagick

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installation de Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get update \
    && apt-get install -y symfony-cli

# Configuration de l'utilisateur www-data avec UID/GID 1000 par défaut
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Ajouter www-data au groupe sudo pour les permissions
RUN echo "www-data ALL=(ALL) NOPASSWD: /bin/chown, /bin/chmod, /bin/mkdir, /usr/bin/composer" >> /etc/sudoers

# Configuration du répertoire de travail
WORKDIR /var/www/symfony

# Copie des fichiers de configuration
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Création des répertoires nécessaires avec les bonnes permissions
RUN mkdir -p /var/www/symfony/var/log \
    && mkdir -p /var/www/symfony/var/cache \
    && mkdir -p /var/www/symfony/public/uploads \
    && mkdir -p /var/www/symfony/config/jwt \
    && mkdir -p /var/www/symfony/migrations \
    && mkdir -p /var/www/.cache/composer \
    && mkdir -p /var/www/.composer \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www

# Configuration de Composer pour www-data
ENV COMPOSER_HOME=/var/www/.composer
ENV COMPOSER_CACHE_DIR=/var/www/.cache/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Variables d'environnement pour Symfony
ENV APP_ENV=dev
ENV APP_DEBUG=1

# Script de démarrage
COPY docker/php/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Permissions finales
RUN chown -R www-data:www-data /var/www

# Démarrer en tant que root pour pouvoir corriger les permissions au runtime
USER root

EXPOSE 9000

CMD ["/usr/local/bin/start.sh"] 