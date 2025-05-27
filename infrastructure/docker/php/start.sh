#!/bin/bash

echo "🚀 Démarrage du conteneur PHP..."

# Fonction pour attendre un service
wait_for_service() {
    local host=$1
    local port=$2
    local service_name=$3
    
    echo "⏳ Attente de $service_name..."
    while ! nc -z $host $port; do
        sleep 1
    done
    echo "✅ $service_name est prêt"
}

# Attendre les services
wait_for_service postgres 5432 "PostgreSQL"
wait_for_service redis 6379 "Redis"

# Obtenir l'UID/GID de l'utilisateur hôte depuis les fichiers montés
HOST_UID=$(stat -c "%u" /var/www/symfony 2>/dev/null || echo "1000")
HOST_GID=$(stat -c "%g" /var/www/symfony 2>/dev/null || echo "1000")

echo "🔧 Configuration des permissions avec UID:$HOST_UID et GID:$HOST_GID"

# Ajuster l'utilisateur www-data pour correspondre à l'utilisateur hôte
if [ "$HOST_UID" != "0" ]; then
    usermod -u $HOST_UID www-data 2>/dev/null || true
    groupmod -g $HOST_GID www-data 2>/dev/null || true
fi

# Créer les répertoires nécessaires
echo "📁 Création des répertoires..."
mkdir -p /var/www/symfony/var/log
mkdir -p /var/www/symfony/var/cache
mkdir -p /var/www/symfony/public/uploads
mkdir -p /var/www/symfony/config/jwt
mkdir -p /var/www/symfony/migrations
mkdir -p /var/www/.cache/composer
mkdir -p /var/www/.composer

# Corriger les permissions pour tous les répertoires Symfony
echo "🔧 Correction des permissions..."
chown -R www-data:www-data /var/www/symfony
chmod -R 755 /var/www/symfony

# Permissions spéciales pour les répertoires sensibles
chmod -R 777 /var/www/symfony/var
chmod -R 775 /var/www/symfony/migrations
chmod -R 755 /var/www/symfony/public
chmod -R 755 /var/www/symfony/config

# Permissions pour les fichiers de configuration
find /var/www/symfony/config -type f -exec chmod 644 {} \; 2>/dev/null || true

# Corriger les permissions pour Composer
chown -R www-data:www-data /var/www/.cache
chown -R www-data:www-data /var/www/.composer
chmod -R 755 /var/www/.cache
chmod -R 755 /var/www/.composer

# S'assurer que les fichiers Composer sont accessibles
if [ -f /var/www/symfony/composer.json ]; then
    chown www-data:www-data /var/www/symfony/composer.json
    chmod 664 /var/www/symfony/composer.json
fi

if [ -f /var/www/symfony/composer.lock ]; then
    chown www-data:www-data /var/www/symfony/composer.lock
    chmod 664 /var/www/symfony/composer.lock
fi

# Permissions pour les fichiers de migration existants
if [ -d /var/www/symfony/migrations ]; then
    find /var/www/symfony/migrations -type f -name "*.php" -exec chmod 664 {} \; 2>/dev/null || true
    find /var/www/symfony/migrations -type f -name "*.php" -exec chown www-data:www-data {} \; 2>/dev/null || true
fi

# Permissions pour le répertoire bin
if [ -d /var/www/symfony/bin ]; then
    chmod -R 755 /var/www/symfony/bin
    chown -R www-data:www-data /var/www/symfony/bin
fi

# Vérifier si Symfony est installé
if [ ! -f /var/www/symfony/composer.json ]; then
    echo "⚠️  Symfony n'est pas encore installé dans ce conteneur"
    echo "📝 Vous pouvez l'installer avec: docker-compose exec php composer install"
else
    echo "✅ Symfony détecté"
    
    # Installer les dépendances si vendor n'existe pas
    if [ ! -d /var/www/symfony/vendor ]; then
        echo "📦 Installation des dépendances Composer..."
        cd /var/www/symfony
        gosu www-data composer install --no-interaction --optimize-autoloader
    fi
    
    # Créer les clés JWT si elles n'existent pas
    if [ ! -f /var/www/symfony/config/jwt/private.pem ]; then
        echo "🔐 Génération des clés JWT..."
        mkdir -p /var/www/symfony/config/jwt
        cd /var/www/symfony
        gosu www-data openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:${JWT_PASSPHRASE:-gestasso_jwt_passphrase}
        gosu www-data openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE:-gestasso_jwt_passphrase}
        chmod 600 /var/www/symfony/config/jwt/private.pem
        chmod 644 /var/www/symfony/config/jwt/public.pem
        chown www-data:www-data /var/www/symfony/config/jwt/private.pem
        chown www-data:www-data /var/www/symfony/config/jwt/public.pem
    fi
fi

# Configuration xDebug conditionnelle
if [ "${XDEBUG_MODE:-off}" != "off" ]; then
    echo "🐛 Activation de xDebug..."
    docker-php-ext-enable xdebug
else
    echo "🚫 xDebug désactivé"
fi

# Créer un fichier de configuration PHP-FPM personnalisé
echo "🔧 Configuration de PHP-FPM..."
cat > /usr/local/etc/php-fpm.d/www.conf << 'EOF'
[www]
user = www-data
group = www-data
listen = 9000
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
clear_env = no
catch_workers_output = yes
decorate_workers_output = no
EOF

echo "✅ Conteneur PHP prêt!"
echo "🌐 Vous pouvez maintenant utiliser les commandes Symfony"
echo "📋 Commandes utiles:"
echo "   - docker-compose exec php composer install"
echo "   - docker-compose exec php php bin/console make:migration"
echo "   - docker-compose exec php php bin/console doctrine:migrations:migrate"
echo "   - docker-compose exec php php bin/console doctrine:database:create"

# Démarrer PHP-FPM en mode foreground
echo "🔄 Démarrage de PHP-FPM..."
exec php-fpm -F 