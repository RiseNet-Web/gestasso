#!/bin/bash

echo "🚀 Démarrage du conteneur PHP..."

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

# Corriger les permissions
chown -R www-data:www-data /var/www/symfony/var 2>/dev/null || true
chown -R www-data:www-data /var/www/symfony/public 2>/dev/null || true
chmod -R 755 /var/www/symfony/var 2>/dev/null || true
chmod -R 755 /var/www/symfony/public 2>/dev/null || true

echo "✅ Conteneur PHP prêt!"
echo "🌐 Vous pouvez maintenant installer Symfony manuellement"

# Démarrer PHP-FPM
exec php-fpm -F 