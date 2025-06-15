FROM php:8.3-fpm-alpine

# Installation des dépendances système
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    supervisor \
    nginx \
    netcat-openbsd

# Installation des extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Installation de Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Configuration Nginx
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Configuration Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Définition du répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers de configuration Composer
COPY composer.json composer.lock ./

# Installation des dépendances Composer
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copie du code source
COPY . .

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Création des répertoires nécessaires
RUN mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

# Génération des clés JWT (si elles n'existent pas)
RUN mkdir -p config/jwt \
    && if [ ! -f config/jwt/private.pem ]; then \
        openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkcs8 -pass pass:your-jwt-passphrase; \
    fi \
    && if [ ! -f config/jwt/public.pem ]; then \
        openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:your-jwt-passphrase; \
    fi \
    && chown -R www-data:www-data config/jwt \
    && chmod 600 config/jwt/private.pem \
    && chmod 644 config/jwt/public.pem

# Exposition du port
EXPOSE 8000

# Script de démarrage
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"] 