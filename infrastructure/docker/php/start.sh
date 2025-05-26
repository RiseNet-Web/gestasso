#!/bin/bash

# Script de démarrage pour Symfony 7.2 - GestAsso Infrastructure

echo "🚀 Démarrage de l'infrastructure Symfony GestAsso..."

# Attendre que PostgreSQL soit prêt
echo "⏳ Attente de PostgreSQL..."
while ! nc -z postgres 5432; do
  sleep 1
done
echo "✅ PostgreSQL est prêt"

# Attendre que Redis soit prêt
echo "⏳ Attente de Redis..."
while ! nc -z redis 6379; do
  sleep 1
done
echo "✅ Redis est prêt"

# Créer les répertoires nécessaires
mkdir -p /var/www/symfony/var/log
mkdir -p /var/www/symfony/var/cache
mkdir -p /var/www/symfony/public/uploads
mkdir -p /var/www/symfony/config/jwt

# Vérifier que le répertoire backend existe
if [ ! -d "/var/www/symfony" ]; then
    echo "❌ Le répertoire backend n'existe pas. Veuillez créer le dossier ../backend depuis infrastructure/"
    exit 1
fi

# Vérifier si Symfony est installé
if [ ! -f "/var/www/symfony/composer.json" ]; then
    echo "📦 Installation de Symfony 7.2..."
    cd /var/www/symfony
    symfony new . --version="7.2.*" --webapp --no-git
    
    echo "📦 Installation des bundles supplémentaires..."
    composer require api-platform/api-platform
    composer require lexik/jwt-authentication-bundle
    composer require doctrine/doctrine-bundle
    composer require doctrine/orm
    composer require symfony/redis-bundle
    composer require symfony/messenger
    composer require symfony/mailer
    composer require symfony/security-bundle
    composer require symfony/validator
    composer require symfony/serializer
    composer require nelmio/cors-bundle
    composer require symfony/monolog-bundle
    composer require --dev symfony/debug-bundle
    composer require --dev symfony/web-profiler-bundle
    composer require --dev doctrine/doctrine-fixtures-bundle
    composer require --dev symfony/maker-bundle
fi

# Installer les dépendances si nécessaire
if [ ! -d "/var/www/symfony/vendor" ]; then
    echo "📦 Installation des dépendances Composer..."
    cd /var/www/symfony
    composer install --optimize-autoloader
fi

# Générer les clés JWT si elles n'existent pas
if [ ! -f "/var/www/symfony/config/jwt/private.pem" ]; then
    echo "🔐 Génération des clés JWT..."
    cd /var/www/symfony
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

# Vider le cache
echo "🗑️ Vidage du cache Symfony..."
cd /var/www/symfony
php bin/console cache:clear --env=dev

# Exécuter les migrations de base de données
echo "🗄️ Exécution des migrations..."
cd /var/www/symfony
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

# Charger les fixtures en développement
if [ "$APP_ENV" = "dev" ]; then
    echo "🌱 Chargement des fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction --append
fi

# Corriger les permissions
echo "🔐 Correction des permissions..."
chown -R www-data:www-data /var/www/symfony/var
chown -R www-data:www-data /var/www/symfony/public/uploads
chmod -R 755 /var/www/symfony/var
chmod -R 755 /var/www/symfony/public/uploads

echo "✅ Infrastructure Symfony GestAsso prête!"
echo "🌐 API disponible sur http://localhost:8080/api"
echo "📊 Profiler disponible sur http://localhost:8080/_profiler"

# Démarrer PHP-FPM
exec php-fpm 