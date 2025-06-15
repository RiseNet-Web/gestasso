#!/bin/sh

echo "🚀 Démarrage du conteneur Symfony..."

# Fonction pour attendre qu'un service soit disponible
wait_for_service() {
    local host=$1
    local port=$2
    local service_name=$3
    
    echo "Attente de $service_name..."
    while ! nc -z $host $port; do
        sleep 1
    done
    echo "$service_name est prêt!"
}

# Attendre PostgreSQL
wait_for_service postgres 5432 "PostgreSQL"

# Attendre Redis
wait_for_service redis 6379 "Redis"

# Création du cache Symfony
echo "Création du cache Symfony..."
php bin/console cache:clear --env=dev
php bin/console cache:warmup --env=dev

# Exécution des migrations
echo "Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

# Génération des clés JWT si elles n'existent pas
if [ ! -f config/jwt/private.pem ]; then
    echo "Génération des clés JWT..."
    mkdir -p config/jwt
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkcs8 -pass pass:your-jwt-passphrase
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:your-jwt-passphrase
    chown -R www-data:www-data config/jwt
    chmod 600 config/jwt/private.pem
    chmod 644 config/jwt/public.pem
fi

# Permissions finales
chown -R www-data:www-data var/
chmod -R 775 var/

echo "Démarrage des services..."
# Démarrage de Supervisor qui gère Nginx et PHP-FPM
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf 